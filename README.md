<p align="center">
  <img src="https://raw.githubusercontent.com/mailbridgeapi/mailbridgeapi/main/assets/logo-mark.png" alt="MailBridgeAPI logo" width="112">
</p>

# MailBridgeAPI PHP

Official dependency-light PHP client for all public MailBridgeAPI endpoints.

## Requirements

- PHP 7.4 or newer
- cURL and JSON extensions
- A MailBridgeAPI account key and mailbox key

## Installation

```bash
composer require mailbridgeapi/mailbridgeapi-php
```

Until the first Packagist release, install from this GitHub repository or copy the PSR-4 source into your project.

## Send an email

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use MailBridgeAPI\Client;

$client = new Client(
    getenv('MAILBRIDGE_ACCOUNT_KEY'),
    getenv('MAILBRIDGE_MAILBOX_KEY')
);

$result = $client->sendEmail([
    'recipient_email' => 'customer@example.com',
    'mail_subject' => 'Order update',
    'mail_body' => '<p>Your order is ready.</p>',
    'format' => 'html',
    'from_source' => 'php-application',
]);

echo $result['result']['id'];
```

## Read and monitor

```php
$status = $client->mailboxStatus();
$received = $client->listReceived(['per_page' => 10]);
$sentErrors = $client->listSent(['status' => 'error']);
$activity = $client->activity(30);
$search = $client->searchEmails('order 12345', ['direction' => 'all']);
```

## Available methods

`health`, `mailboxStatus`, `mailboxInsights`, `sendEmail`, `getEmail`, `listUnsubscribed`, `listSent`, `listReceived`, `getMessage`, `searchEmails`, `activity`, and `validateEmail`.

See the [complete API documentation](https://github.com/mailbridgeapi/mailbridgeapi).

## Security

Load keys from environment variables or a secret manager. Never expose them in browser code, public repositories, logs, or screenshots.

