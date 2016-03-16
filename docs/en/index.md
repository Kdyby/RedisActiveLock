# Quickstart

This extension is here to provide a locking mechanism for [Redis](http://redis.io)

The locking mechanism is implemented using [a best practise for Redis](http://redis.io/commands/setnx), however, it's not even remotely perfect.

The problem is that Redis doesn't have native locks, and they have to be emulated (hence this library).
You may (or may not) run into problems when you experience extreme traffic,
some of the threads die and the lock was not released and it's released after the timeout.
While the timeout is running, other users have to wait and the system may just collapse.

## Installation

* Install [latest stable Redis](http://redis.io/download)
* Install [latest redis pecl extension](https://pecl.php.net/package/redis)

Then you can install the package using this command

```sh
$ composer require kdyby/redis-active-lock
```


## Usage

```php
$redis = new \Redis();
$redis->connect();

$lock = new \Kdyby\RedisActiveLock\AdvisoryLock($redis);
$lock->acquireLock('someKey');
```
