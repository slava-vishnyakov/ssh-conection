<?php

namespace SlavaVishnyakov\SshConnection;

use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;
use phpseclib\System\SSH\Agent;

class SshConnection
{
    public $host;
    public $username;

    public $privateKeyPath;
    public $privateKeyPassword;

    public $useAgent = false;

    /** @var SSH2 */
    public $ssh;

    public $verbose = false;

    public $sudoPassword;

    private $prompt;

    /**
     * SshConnection constructor.
     * @param $host
     * @param $username
     */
    public function __construct($username, $host)
    {
        $this->host = $host;
        $this->username = $username;
    }

    public function connectAndReadMotd()
    {
        $this->connect();
        $this->prompt = md5(random_bytes(32)) . '-' . md5(random_bytes(32));
        $this->ssh->write("stty -echo\nexport PS1=\"{$this->prompt}\"\n");
        $this->ssh->read('stty -echo');
        $this->readUntilPrompt();
        $this->readUntilPrompt();
    }

    public function connect()
    {
        $this->ssh = new SSH2($this->host);

        if ($this->privateKeyPath) {
            $auth = new RSA();
            if ($this->privateKeyPassword) {
                $auth->setPassword($this->privateKeyPassword);
            }
            $auth->loadKey(file_get_contents($this->privateKeyPath));
        }

        if ($this->useAgent) {
            $auth = new Agent();
        }

        if (!$this->ssh->login($this->username, $auth)) {
            $using = $this->authMethodString($auth);
            throw new \RuntimeException('Login to ' . $this->username . '@' . $this->host . ' failed using ' . $using);
        }

    }

    public function runSudoRaw($cmd)
    {
        $sudoFile = './pw_' . md5(random_bytes(32)) . '.sh';
        $this->sshWrite("cd ~; touch $sudoFile; chmod 0700 $sudoFile; cat > $sudoFile <<EOF\n");
        $this->sshWrite("#!/bin/bash\necho {$this->sudoPassword}\n");
        $this->sshWrite("EOF\n");
        $this->readUntilPrompt();

        $cmd = escapeshellarg($cmd);
        // above command should self-remove the file, but rm it just in case
        $this->sshWrite("SUDO_ASKPASS=$sudoFile sudo --askpass bash -lc $cmd; (rm -f $sudoFile 2>/dev/null)\n");

        $result = $this->readUntilPrompt();

        return $result;
    }

    public function runRaw($cmd)
    {
        $cmd = escapeshellarg($cmd);
        $this->sshWrite("bash -lc $cmd\n");

        return $this->readUntilPrompt();
    }

    public function readUntilPrompt()
    {
        $regexE = '#\r?\n?' . $this->prompt . '$#';
        $read = $this->sshRead($this->prompt);

        return preg_replace($regexE, '', $read);
    }

    /**
     * @param $auth
     * @return string
     */
    private function authMethodString($auth): string
    {
        $using = 'No Password';
        if (is_string($auth)) {
            $using = 'Password';
        }
        if ($auth instanceof RSA) {
            $using = 'Private Key';
        }
        if ($auth instanceof Agent) {
            $using = 'Agent';
        }

        return $using;
    }

    public function sshWrite($line)
    {
        $this->ssh->write($line);
        if ($this->verbose) {
            print "----------\n";
            print date('d.m.Y H:i:s') . " -> " . $this->limit(rtrim($line), 10240) . "\n";
            print "----------\n";
        }
    }

    public function sshRead($until = '', $mode = SSH2::READ_SIMPLE)
    {
        $line = $this->ssh->read($until, $mode);
        if ($this->verbose) {
            print "----------\n";
            print date('d.m.Y H:i:s') . " <- " . $this->limit(rtrim($line), 10240) . "\n";
            print "----------\n";
        }

        return $line;
    }

    private function limit(string $s, int $limit): string
    {
        if (mb_strlen($s) > $limit) {
            return mb_substr($s, 0, $limit);
        }

        return $s;
    }

}
