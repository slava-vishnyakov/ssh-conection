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
        $s = $this->connectToVagrant();
        $s->sshWrite("whoami\n");
        $this->assertEquals("vagrant", $s->readUntilPrompt());
    }

    /** @test */
    public function basic_sudo()
    {
        $s = $this->connectToVagrant();
        $result = $s->runSudoRaw("whoami");
        $this->assertEquals("root", $result);
    }

    /** @test */
    public function sudo_quoting()
    {
        $s = $this->connectToVagrant();
        $result = $s->runSudoRaw("echo \"1\"");
        $this->assertEquals("1", $result);
    }

    /** @test */
    public function sudo_with_real_password()
    {
        $s = $this->connectToVagrant();

        // password: vagrant
        $hash = '$6$0GkaeSeC$D4KDx.aT1uwSMjJwlGwU/uHJqs6jkHPZ884L6LEo3.yK5WNeskNOeRQ37f6uateDYzsg6yqaxKIW5K.FW04R71';

        $s->runSudoRaw("(
            deluser sudo_with_pass --remove-home;
            useradd sudo_with_pass --create-home --password '{$hash}' --shell '/bin/bash';
            cp -R /home/vagrant/.ssh /home/sudo_with_pass/;
            chown -R sudo_with_pass:sudo_with_pass /home/sudo_with_pass/.ssh;
            usermod sudo_with_pass -a -G sudo
        ) 2> /dev/null");
        // ignore if already exists

        $c = $this->connectToVagrant(false, 'sudo_with_pass');
        $c->sudoPassword = 'vagrant';

        $cmd = 'whoami';
        $result = $c->runSudoRaw($cmd);

        $this->assertEquals('root', $result);

    }

    /** @test */
    public function unhappy_sudo()
    {
        $s = $this->connectToVagrant(false);

        // password: vagrant
        $hash = '$6$0GkaeSeC$D4KDx.aT1uwSMjJwlGwU/uHJqs6jkHPZ884L6LEo3.yK5WNeskNOeRQ37f6uateDYzsg6yqaxKIW5K.FW04R71';

        $s->runSudoRaw("(
            deluser sudo_with_pass --remove-home;
            useradd sudo_with_pass --create-home --password '{$hash}' --shell '/bin/bash';
            mkdir -p /home/sudo_with_pass/.ssh;
            cp -R /home/vagrant/.ssh/authorized_keys /home/sudo_with_pass/.ssh/;
            chown sudo_with_pass:sudo_with_pass /home/sudo_with_pass
            chown -R sudo_with_pass:sudo_with_pass /home/sudo_with_pass/.ssh
            ) 2> /dev/null
        ");
        // ignore if already exists

        $this->assertContains($hash, $s->runSudoRaw('tail -1 /etc/shadow'));
        $this->assertContains('uid=', $s->runSudoRaw('id sudo_with_pass'));
        $this->assertContains('ssh-rsa', $s->runSudoRaw('cat /home/vagrant/.ssh/*'));

        $s = $this->connectToVagrant(false, 'sudo_with_pass');
        $s->sudoPassword = 'vagrant';

        $this->assertNotContains('vagrant', $s->runSudoRaw('echo'));

        $cmd = 'sleep 3';
        $result = $s->runSudoRaw($cmd);

        $this->assertEquals('sudo_with_pass is not in the sudoers file.  This incident will be reported.', $result);

    }

    /** @test */
    public function it_escapes_sudo()
    {
        $c = $this->connectToVagrant(false);

        $this->assertEquals('d6=$6', $c->runSudoRaw("echo 'd6=$6'"));
    }

    /** @test */
    public function it_escapes()
    {
        $c = $this->connectToVagrant(false);

        $this->assertEquals('d6=$6', $c->runRaw("echo 'd6=$6'"));
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
