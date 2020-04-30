# Symfony Messenger Redis Adapter

![PHP Tests](https://github.com/krakphp/symfony-messenger-redis/workflows/PHP%20Tests/badge.svg)

This provides custom Redis List Integration with the Symfony Messenger ^4.4 system.

The standard redis implementation requires redis 5.0 and utilizes the streams feature, this adapter uses redis lists to power the queue functionality.

## Installation

Install with composer at `krak/symfony-messenger-redis`.

If symfony's composer install doesn't automatically register the bundle, you can do so manually:

```php
<?php

return [
  //...
  Krak\SymfonyMessengerRedis\MessengerRedisBundle::class => ['all' => true],
];
```

## Usage

You can use this queue with the following config for the framework messenger:

```yaml
framework:
  messenger:
    transports:
      acme_redis:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        options: { queue: queue_acme }
```

Where `MESSENGER_TRANSPORT_DSN` env is like: `redis://localhost:6379`

This will register a transport named `acme_redis` which will properly use the configured Redis transport from this library.

### Unique Messages

If you ever need to ensure that you a specific job needs to be unique while waiting in the queue, you can use the UniqueStamp.

```php
use Symfony\Component\Messenger\Envelope;
use Krak\SymfonyMessengerRedis\Stamp\UniqueStamp;
Envelope::wrap($message)->with(new UniqueStamp('optional-unique-id'));
```

If you don't pass an identifier to enforce uniqueness, the transport will perform an md5 hash of the message body to create an identifier.

Example usage of this stamp:

- You dispatch a ProductUpdatedMessage every time a product is saved in one system.
- You have message handlers that then do some expensive operation based off of that product information
- In the event a single product gets saved rapidly without effectively changing, it makes no sense to enqueue the same job for that product 100 times
- Using the unique stamp ensures that if a message is already in the queue for that specific product, don't add in another message since the original message hasn't been processed yet.

### Delayed Messages

This library supports the DelayStamp provided by the core SF messenger.

### Available Options

Here are the available options that can be provided to the transport options array or as query parameters:

- **queue:**
  - *required:* yes
  - *default:* N/A
  - *description:* The internal list name to use for storing the messages in redis. The system will also create a processing queue named `{queue}_processing` for storing the processed messages.

### Processed Queue

This library uses the [rpoplpush reliable queue pattern](https://redis.io/commands/rpoplpush#pattern-reliable-queue). It's important to note however that this library does not make any attempt to clean up that processed queue.

We've made great strides to make sure that when everything is working right, that the processing queue is always cleaned up, even when using the autoscaling receiver.

However, if for some reason a worker is killed via `SIGKILL` (aka `kill -9`), there are chances the worker process could leave a processing item in the queue because it didn't finish it's process. Overtime, this might cause the `_processing` to accrue messages that don't need to be there.

It won't hurt anything other than storage to have those `_processing` lists take up space. It also *shouldn't* hurt anything to periodically just wipe out those lists using the `DEL` command. But that would need to be verified that it wouldn't hurt any running workers (which I don't think it will).

### Using Symfony's Redis Transport at the same time

Both symfony's redis and the krak redis transport register the dsn prefix: `redis://`. In the scenario that you want to support both transports, you'll need to use the `use_krak_redis` option to disable this libraries redis transport.

```yaml
framework:
  messenger:
    transports:
      krak_redis:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        options: { queue: queue_acme }
      sf_redis:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        options: { use_krak_redis: false } # this allows symfony's redis transport factory to take precedence
```

## Testing

You can run the test suite with: `composer test`

You'll need to start the redis docker container locally in order for the Feature test suite to pass.

Keep in mind that you will need to have the redis-ext installed on your local php cli, and will need to start up the redis instance in docker via `docker-compose`.
