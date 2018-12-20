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
print($ssh->runRaw('ls'));

$ssh->sudoPassword = 'vagrant';
print($ssh->sudoRunRaw('whoami'));
```

# Testing

```bash
vagrant up
phpunit
```
