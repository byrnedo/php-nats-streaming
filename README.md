# PHP Nats Streaming Server Client

**Travis**

| Master  | Develop |
| ------------- | ------------- |
| [![Build Status](https://travis-ci.org/byrnedo/php-nats-streaming.svg?branch=master)](https://travis-ci.org/byrnedo/php-nats-streaming)  | [![Build Status](https://travis-ci.org/byrnedo/php-nats-streaming.svg?branch=develop)](https://travis-ci.org/byrnedo/php-nats-streaming)  |


## Intro

A php client for [Nats Streaming Server](https://nats.io/documentation/streaming/nats-streaming-intro/).


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
php composer.phar require byrnedo/nats-streaming-server:dev-master
```

## Usage

```php
// Connect
$options = new ConnectionOptions();
$options->setClientID("test");
$options->setClusterID("test-cluster");
$c = new Connection($options);

// Publish
$c->publish('special.subject', 'some serialized payload...');


// Subscribe

$subOptions = new \NatsStreaming\SubscriptionOptions();

$subOptions->setStartAt(\pb\StartPosition::First());

$c->subscribe('special.subject', function ($message) {
    // TODO -implement
}, $subOptions);

$c->wait(1);
```

## License

MIT, see [LICENSE](LICENSE)

