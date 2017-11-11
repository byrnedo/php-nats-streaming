# PHP Nats Streaming Server Client

## Travis

| Master  | Develop |
| ------------- | ------------- |
| [![Build Status](https://travis-ci.org/byrnedo/php-nats-streaming.svg?branch=master)](https://travis-ci.org/byrnedo/php-nats-streaming)  | [![Build Status](https://travis-ci.org/byrnedo/php-nats-streaming.svg?branch=develop)](https://travis-ci.org/byrnedo/php-nats-streaming)  |


## Intro

A php client for [Nats Streaming Server](https://nats.io/documentation/streaming/nats-streaming-intro/).


Uses [phpnats](https://github.com/repejota/phpnats) under the hood and closesly resembles it's api.


## Requirements

* php 5.6+
* [stan](https://github.com/nats-io/nats-streaming-server)


## Installation

Get [composer](https://getcomposer.org/):
```bash
curl -O http://getcomposer.org/composer.phar && chmod +x composer.phar
```

Add php-nats-streaming as a dependency to your project

```bash
php composer.phar require byrnedo/php-nats-streaming:dev-master
```

## Usage

### Publish
```php
$options = new \NatsStreaming\ConnectionOptions();
$options->setClientID("test");
$options->setClusterID("test-cluster");
$c = new \NatsStreaming\Connection($options);

$c->connect();

// Publish
$c->publish('special.subject', 'some serialized payload...');

$c->close();

```
### Subscribe
```php
$options = new \NatsStreaming\ConnectionOptions();
$c = new \NatsStreaming\Connection($options);

$c->connect();

$subOptions = new \NatsStreaming\SubscriptionOptions();
$subOptions->setStartAt(\NatsStreamingProtos\StartPosition::First());

$sub = $c->subscribe('special.subject', function ($message) {
    // implement
}, $subOptions);

$c->wait(1);

// not explicitly needed
$sub->unsubscribe(); // or $sub->close();

$c->close();

```
### Queue Group Subscribe
```php
$options = new \NatsStreaming\ConnectionOptions();
$c = new \NatsStreaming\Connection($options);

$c->connect();

$subOptions = new \NatsStreaming\SubscriptionOptions();
$sub = $c->queueSubscribe('specialer.subject', 'workgroup', function ($message) {
    // implement
}, $subOptions);


$c->wait(1);

// not explicitly needed
$sub->close(); // or $sub->unsubscribe();

$c->close();

```

### Manual Ack
```php

$options = new \NatsStreaming\ConnectionOptions();
$c = new \NatsStreaming\Connection($options);

$c->connect();

$subOptions = new \NatsStreaming\SubscriptionOptions();
$subOptions->setManualAck(true);

$sub = $c->subscribe('special.subject', function ($message) {
    $message->ack();
}, $subOptions);

$c->wait(1);

$c->close();

```

## License

MIT, see [LICENSE](LICENSE)

