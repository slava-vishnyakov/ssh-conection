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

    private $home;
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

    public function run($cmd, $args = []): CommandResult
    {
        $cmd2 = escapeshellcmd($cmd) . " " . join(' ', array_map('escapeshellarg', $args));

        $stderr = $this->getHome() . '/.stderr_' . md5(random_bytes(32));

        $stdout = $this->runRaw("$cmd2 2>$stderr");
        $exitCode = $this->runRaw("echo $?");

        $stderr = $this->runRaw("cat $stderr; rm -f $stderr");

        return new CommandResult($stdout, $stderr, $exitCode);
    }

    public function sudoRun($cmd, $args = []): CommandResult
    {
        $cmd2 = escapeshellcmd($cmd) . " " . join(' ', array_map('escapeshellarg', $args));

        $stderr = $this->getHome() . '/.stderr_' . md5(random_bytes(32));

        $stdout = $this->runSudoRaw("$cmd2 2>$stderr; echo \$?");

        $exitCodeRegex = '#\r?\n?(\d+)$#';
        preg_match($exitCodeRegex, $stdout, $m);
        $exitCode = (int)$m[0];

        $stdout = preg_replace($exitCodeRegex, '', $stdout);

        $stderr = $this->runRaw("cat $stderr; rm -f $stderr");

        return new CommandResult($stdout, $stderr, $exitCode);
    }

    public function runSudoRaw($cmd)
    {
        $home = $this->getHome();
        $rand = md5(random_bytes(32));
        $sudoFile = "$home/.pw_{$rand}.sh";
        $script = "#!/bin/bash\necho {$this->sudoPassword}";
        $this->createFile($sudoFile, $script, '0700');

        $cmd = escapeshellarg("rm -f $sudoFile;" . $cmd);

        $this->sshWrite("SUDO_ASKPASS=$sudoFile sudo --askpass bash -lc $cmd\n"); // (rm -f $sudoFile 2>/dev/null)

        $result = $this->readUntilPrompt();

        return $result;
    }

    public function createFile($filename, $content, $permissions = '0755')
    {
        if(!is_string($permissions)) {
            throw new \RuntimeException("Permissions must be a string");
        }
        $this->sshWrite("touch $filename; chmod $permissions $filename; cat > $filename <<EOF\n");
        $this->sshWrite("$content\n");
        $this->sshWrite("EOF\n");
        $this->readUntilPrompt();
    }

    public function runRaw($cmd)
    {
        $this->sshWrite("$cmd\n");
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

    /**
     * @return string
     */
    private function getHome()
    {
        if(!$this->home) {
            $this->home = $this->runRaw("(cd ~; pwd)");
            $this->home = rtrim($this->home, '/');
        }

        return $this->home;
    }

}
