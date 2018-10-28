# Symfony Messenger Redis Adapter

This provides custom Redis Integration with the Symfony Messenger 4.1 system. There already exists an implementation for 4.0, but that is not compatabile with the 4.1 integration (due to Messenger's BC policy).

It also includes some Enhancers for AutoScaling which provide receivers the ability to properly auto scale requests depending on metrics provided from the Queue.

## Installation

Currently, this is best installed by cloning the repo as a submodule then using [path type composer repository](https://getcomposer.org/doc/05-repositories.md#path). This is under the `krak/symfony-messenger-redis`.

Until this is in a composer repository, you'll probably need to manually include the bundle in your `config/bundles.php` file like so:


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

### Available Options

Here are the available options that can be provided to the transport options array or as query parameters:

- **blocking_timeout:**
  - *required:* no
  - *default:* 30
  - *description:* The number of seconds for the redis bRpopLpush command to block. **DO NOT** set this value over or around60 seconds or you will experience `redis failed to read` type errors due to a bug in the redis extension.
- **queue:**
  - *required:* yes
  - *default:* N/A
  - *description:* The internal list name to use for storing the messages in redis. The system will also create a processing queue named `{queue}_processing` for storing the processed messages.

### Processed Queue

This library uses the [rpoplpush reliable queue pattern](https://redis.io/commands/rpoplpush#pattern-reliable-queue). It's important to note however that this library does not make any attempt to clean up that processed queue.

We've made great strides to make sure that when everything is working right, that the processing queue is always cleaned up, even when using the autoscaling receiver.

However, if for some reason a worker is killed via `SIGKILL` (aka `kill -9`), there are chances the worker process could leave a processing item in the queue because it didn't finish it's process. Overtime, this might cause the `_processing` to accrue messages that don't need to be there.

It won't hurt anything other than storage to have those `_processing` lists take up space. It also *shouldn't* hurt anything to periodically just wipe out those lists using the `DEL` command. But that would need to be verified that it wouldn't hurt any running workers (which I don't think it will).

### ReQueing Failed Messages

This library makes absolutely no attempt to requeue a message once it's failed. I've found in practice that if a message fails once, it will almost always fail again if re-tried shortly after.

Furthermore, The consequences for having code that can retry after failure mean that all operations must be idempotent, but typically, queued jobs by nature are not that way because they might be integrating with external vendors or payment processing systems that might cause duplicate charges or orders being made (due to an error and then retrying the same message).

I imagine in the future we will support better logging of failed messages and the possibility to store failed messages in a db table, but that would be handled via a Receiver decorator and not from the actual redis queing system.

It's important to note that currently, this library does not handle any cleaning of the processed queue.

## Testing

You can run the unit tests with: `make unit-test`

You can run the integration tests with: `make integration-test`. Keep in mind that you will need to have the redis-ext installed on your local php cli, and will need to start up the redis instance in docker via `docker-compose`.

We do have functional tests as well that were used to stress test which are found in the tests/functional folder. Those have the same system requirements as the integration tests.
