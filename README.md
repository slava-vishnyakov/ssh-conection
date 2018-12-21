# Install

```bash
composer require slava-vishnyakov/ssh-connection
```

# Use

```php
use SlavaVishnyakov\SshConnection\SshConnection;

$ssh = new SshConnection($username, '127.0.0.1:2222');
$ssh->privateKeyPath = 'my_private_key';
// $ssh->privateKeyPassword = '';
// $ssh->useAgent = true;

$ssh->connectAndReadMotd();

$result = $ssh->run('echo Hello');
print($result->stdout); // "Hello"
print($result->stderr); // ""
print($result->exitCode); // 0

$ssh->sudoPassword = 'vagrant';
print($ssh->sudoRun('whoami'));
```

# Testing

```bash
vagrant up
phpunit
```

# TODO 
- Test escaping in run / sudoRun
