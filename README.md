# PHP Nats Streaming Server Client

## Build

| Master  | Develop |
| ------------- | ------------- |
| [![Build Status](https://travis-ci.org/byrnedo/php-nats-streaming.svg?branch=master)](https://travis-ci.org/byrnedo/php-nats-streaming)  | [![Build Status](https://travis-ci.org/byrnedo/php-nats-streaming.svg?branch=develop)](https://travis-ci.org/byrnedo/php-nats-streaming)  |

## Coverage

| Master  | Develop |
| ------------- | ------------- |
| [![Coverage Status](https://coveralls.io/repos/github/byrnedo/php-nats-streaming/badge.svg?branch=master)](https://coveralls.io/github/byrnedo/php-nats-streaming?branch=master)  | [![Coverage Status](https://coveralls.io/repos/github/byrnedo/php-nats-streaming/badge.svg?branch=develop)](https://coveralls.io/github/byrnedo/php-nats-streaming?branch=develop)  |



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
php composer.phar require 'byrnedo/php-nats-streaming:^0.2.4'
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
$r = $c->publish('special.subject', 'some serialized payload...');

// optionally wait for the ack
$gotAck = $r->wait();
if (!$gotAck) {
    ...
}

$c->close();

```

#### Note

If publishing many messages at a time, you might at first do this:

```php
foreach ($req as $data){
    $r = $c->publish(...);
    $gotAck = $r->wait();
    if (!$gotAck) {
        ...
    }
}
```

It's actually *much* faster to do the following:

```php
$rs = [];
foreach ($req as $data){
    $rs[] = $c->publish(...);
}

foreach ($rs as $r){
    $r->wait();
}
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

$sub->wait(1);

// not explicitly needed
$sub->unsubscribe(); // or $sub->close();

$c->close();

```

If you want to subscribe to multiple channels you can use `$c->wait()`:

```php
...

$c->connect();

...

$sub = $c->subscribe('special.subject', function ($message) {
    // implement
}, $subOptions);
$sub2 = $c->subscribe('special.subject', function ($message) {
    // implement
}, $subOptions);

$c->wait();
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


$sub->wait(1);

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

$sub->wait(1);

$c->close();

```

## License

MIT, see [LICENSE](LICENSE)

