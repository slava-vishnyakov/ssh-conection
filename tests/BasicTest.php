<?php

namespace tests;

use PHPUnit\Framework\TestCase;
use SlavaVishnyakov\SshConnection\SshConnection;

class BasicTest extends TestCase
{
    /** @test */
    public function basic()
    {
        $this->assertEquals(true, true);
    }

    /** @test */
    public function basic_connect()
    {
        $ssh = $this->connectToVagrant();
        $ssh->sshWrite("whoami\n");
        $this->assertEquals("vagrant", $ssh->readUntilPrompt());
    }

    /** @test */
    public function basic_sudo()
    {
        $ssh = $this->connectToVagrant();
        $result = $ssh->runSudoRaw("whoami");
        $this->assertEquals("root", $result);
    }

    /** @test */
    public function basic_sudo_removes_password_file()
    {
        $ssh = $this->connectToVagrant();
        $result0 = $ssh->runRaw('ls -1a ~');
        $ssh->runSudoRaw("whoami");
        $result1 = $ssh->runRaw('ls -1a ~');
        $this->assertEquals($result0, $result1, "Failed to clean up home");
    }

    /** @test */
    public function sudo_quoting()
    {
        $ssh = $this->connectToVagrant();
        $result = $ssh->runSudoRaw("echo \"1\"");
        $this->assertEquals("1", $result);
    }

    /** @test */
    public function sudo_with_real_password()
    {
        $ssh = $this->connectToVagrant();

        // password: vagrant
        $hash = '$6$0GkaeSeC$D4KDx.aT1uwSMjJwlGwU/uHJqs6jkHPZ884L6LEo3.yK5WNeskNOeRQ37f6uateDYzsg6yqaxKIW5K.FW04R71';

        $ssh->runSudoRaw("(
            deluser sudo_with_pass --remove-home;
            useradd sudo_with_pass --create-home --password '{$hash}' --shell '/bin/bash';
            cp -R /home/vagrant/.ssh /home/sudo_with_pass/;
            chown -R sudo_with_pass:sudo_with_pass /home/sudo_with_pass/.ssh;
            usermod sudo_with_pass -a -G sudo
        ) 2> /dev/null");
        // ignore if already exists

        $ssh = $this->connectToVagrant(false, 'sudo_with_pass');
        $ssh->sudoPassword = 'vagrant';

        $cmd = 'whoami';
        $result = $ssh->runSudoRaw($cmd);

        $this->assertEquals('root', $result);

    }

    /** @test */
    public function unhappy_sudo()
    {
        $ssh = $this->connectToVagrant(false);

        // password: vagrant
        $hash = '$6$0GkaeSeC$D4KDx.aT1uwSMjJwlGwU/uHJqs6jkHPZ884L6LEo3.yK5WNeskNOeRQ37f6uateDYzsg6yqaxKIW5K.FW04R71';

        $ssh->runSudoRaw("(
            deluser sudo_with_pass --remove-home;
            useradd sudo_with_pass --create-home --password '{$hash}' --shell '/bin/bash';
            mkdir -p /home/sudo_with_pass/.ssh;
            cp -R /home/vagrant/.ssh/authorized_keys /home/sudo_with_pass/.ssh/;
            chown sudo_with_pass:sudo_with_pass /home/sudo_with_pass
            chown -R sudo_with_pass:sudo_with_pass /home/sudo_with_pass/.ssh
            ) 2> /dev/null
        ");
        // ignore if already exists

        $this->assertContains($hash, $ssh->runSudoRaw('tail -1 /etc/shadow'));
        $this->assertContains('uid=', $ssh->runSudoRaw('id sudo_with_pass'));
        $this->assertContains('ssh-rsa', $ssh->runSudoRaw('cat /home/vagrant/.ssh/*'));

        $ssh = $this->connectToVagrant(false, 'sudo_with_pass');
        $ssh->sudoPassword = 'vagrant';

        $this->assertNotContains('vagrant', $ssh->runSudoRaw('echo'));

        $cmd = 'sleep 3';
        $result = $ssh->runSudoRaw($cmd);

        $this->assertEquals('sudo_with_pass is not in the sudoers file.  This incident will be reported.', $result);

    }

    /** @test */
    public function it_escapes_sudo()
    {
        $ssh = $this->connectToVagrant(false);

        $this->assertEquals('d6=$6', $ssh->runSudoRaw("echo 'd6=$6'"));
    }

    /** @test */
    public function it_escapes()
    {
        $ssh = $this->connectToVagrant(false);

        $this->assertEquals('d6=$6', $ssh->runRaw("echo 'd6=$6'"));
    }

    /** @test */
    public function cmd_result()
    {
        $ssh = $this->connectToVagrant();

        $result0 = $ssh->runRaw('ls -1a ~');

        $result = $ssh->run('bruh');

        $this->assertEquals(127, $result->exitCode);
        $this->assertEquals('bruh: command not found', $result->stderr);
        $this->assertEquals('', $result->stdout);

        $result = $ssh->run('echo 1');
        $this->assertEquals(0, $result->exitCode);
        $this->assertEquals('', $result->stderr);
        $this->assertEquals('1', $result->stdout);

        $result1 = $ssh->runRaw('ls -1a ~');
        $this->assertEquals($result0, $result1, "Failed to clean up home");
    }

    /** @test */
    public function sudo_cmd_result()
    {
        $ssh = $this->connectToVagrant();

        $result0 = $ssh->runRaw('ls -1a ~');

        $result = $ssh->sudoRun('bruh');

        $this->assertEquals(127, $result->exitCode);
        $this->assertEquals('bash: bruh: command not found', $result->stderr);
        $this->assertEquals('', $result->stdout);

        $result = $ssh->sudoRun('echo 1');
        $this->assertEquals(0, $result->exitCode);
        $this->assertEquals('', $result->stderr);
        $this->assertEquals('1', $result->stdout);

        $result1 = $ssh->runRaw('ls -1a ~');
        $this->assertEquals($result0, $result1, "Failed to clean up home");
    }


    private function connectToVagrant($verbose = false, $username = 'vagrant')
    {
        if ($verbose) {
            print date('d.m.Y H:i:s') . ": start\n";
        }

        $ssh = new SshConnection($username, '127.0.0.1:2222');
        $ssh->privateKeyPath = __DIR__ . '/../.vagrant/machines/vm1/virtualbox/private_key';
        $ssh->verbose = $verbose;

        $ssh->connectAndReadMotd();

        return $ssh;
    }

}
