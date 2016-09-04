<?php

/**
 * @testCase
 */

namespace KdybyTests\RedisActiveLock;

use Kdyby\RedisActiveLock\AdvisoryLock;
use Redis;
use Tester;
use Tester\Assert;



require_once __DIR__ . '/bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class AdvisoryLockTest extends Tester\TestCase
{

	public function testIncreaseDuration()
	{
		$lock = new AdvisoryLock('foo:bar', $this->createClient());

		$lock->lock(2);
		$remainingTimeout = $lock->calculateRemainingTimeout();
		Assert::true($remainingTimeout >= 1 && $remainingTimeout <= 3);

		$lock->increaseDuration(5);
		$remainingTimeout = $lock->calculateRemainingTimeout();
		Assert::true($remainingTimeout >= 4 && $remainingTimeout <= 6);

		$lock->release();
	}



	public function testIncreaseDurationLockExpiredException()
	{
		$first = new AdvisoryLock('foo:bar', $this->createClient());
		$first->lock(1);
		sleep(3);

		Assert::exception(function () use ($first) {
			$first->increaseDuration();
		}, 'Kdyby\RedisActiveLock\LockException', 'Process ran too long. Increase lock duration, or extend lock regularly.');
	}



	public function testDeadlockHandling()
	{
		$first = new AdvisoryLock('foo:bar', $this->createClient());
		$second = new AdvisoryLock('foo:bar', $this->createClient());

		$first->lock(1);
		sleep(3); // first died?

		$second->lock(1);
		Assert::true($second->release());
		Assert::false($first->release());
	}



	public function testInvalidDurationException()
	{
		$lock = new AdvisoryLock('foo:bar', $this->createClient());

		Assert::exception(function () use ($lock) {
			$lock->lock(-1);
		}, 'Kdyby\RedisActiveLock\InvalidArgumentException');

		Assert::exception(function () use ($lock) {
			$lock->increaseDuration(-1);
		}, 'Kdyby\RedisActiveLock\InvalidArgumentException');
	}



	public function testIncreaseNotLockedKeyException()
	{
		$lock = new AdvisoryLock('foo:bar', $this->createClient());

		Assert::exception(function () use ($lock) {
			$lock->increaseDuration(1);
		}, 'Kdyby\RedisActiveLock\LockException', 'The key "foo:bar" has not yet been locked');
	}



	public function testReleaseNotLocked()
	{
		$lock = new AdvisoryLock('foo:bar', $this->createClient());
		Assert::false($lock->release());
	}



	/**
	 * @return Redis
	 */
	private function createClient()
	{
		$client = new Redis();
		if (!$client->connect('127.0.0.1')) {
			throw new \RuntimeException(sprintf('Connection error: %s', $client->getLastError()));
		}

		return $client;
	}

}



\run(new AdvisoryLockTest());
