<?php

/**
 * @testCase
 */

namespace KdybyTests\RedisActiveLock;

use Kdyby\RedisActiveLock\AcquireTimeoutException;
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

	public function testHoldsLock()
	{
		$client = $this->createClient();
		$first = new AdvisoryLock('foo:bar', [$client]);
		$second = new AdvisoryLock('foo:bar', [$client], null);

		$startTime = microtime(TRUE);
		$first->lock(3000);
		$second->lock(1000);
		Assert::true((microtime(TRUE) - $startTime) >= 3);

		Assert::true($first->release());
		Assert::true($second->release());
	}


	public function testAcquireExpiredLock()
	{
		$client = $this->createClient();
		$first = new AdvisoryLock('foo:bar', [$client]);
		$second = new AdvisoryLock('foo:bar', [$client]);

		$first->lock(100);
		sleep(3); // first died?

		$second->lock(100);
		Assert::true($second->release());
		Assert::true($first->release());
	}


	public function testLockingLockedKeyAcquireTimeoutException()
	{
		$client = $this->createClient();
		$first = new AdvisoryLock('foo:bar', [$client]);
		$second = new AdvisoryLock('foo:bar', [$client], 3);

		$first->lock(10000);
		Assert::exception(function () use ($second) {
			$second->lock(5000);
		}, 'Kdyby\RedisActiveLock\AcquireTimeoutException', null, AcquireTimeoutException::ACQUIRE_TIMEOUT);
		Assert::false($second->release());
		Assert::true($first->release());
	}


	public function testLockingLockedKeyAllAttemptsUsedException()
	{
		$client = $this->createClient();
		$first = new AdvisoryLock('foo:bar', [$client]);
		$second = new AdvisoryLock('foo:bar', [$client], null, 1);

		$first->lock(10000);
		Assert::exception(function () use ($second) {
			$second->lock(5000);
		}, 'Kdyby\RedisActiveLock\AcquireTimeoutException', null, AcquireTimeoutException::ALL_ATTEMPTS_USED);
		Assert::false($second->release());
		Assert::true($first->release());
	}


	public function testReleaseNotLocked()
	{
		$lock = new AdvisoryLock('foo:bar', [$this->createClient()]);
		Assert::false($lock->release());
	}



	public function testInvalidConstructorArguments()
	{
		Assert::exception(function () {
			new AdvisoryLock('key', [
				$this->createClient(),
				new \stdClass,
			]);
		}, 'Kdyby\RedisActiveLock\InvalidArgumentException', 'Given connections must be array of connected \\Redis instances');

		Assert::exception(function () {
			new AdvisoryLock('key', []);
		}, 'Kdyby\RedisActiveLock\InvalidArgumentException', 'At leasts one server is required');

		Assert::exception(function () {
			new AdvisoryLock(1, [$this->createClient()]);
		}, 'Kdyby\RedisActiveLock\InvalidArgumentException', 'Given key is not a string');

		Assert::exception(function () {
			new AdvisoryLock('key', [$this->createClient()], NULL, -1);
		}, 'Kdyby\RedisActiveLock\InvalidArgumentException', 'Max attempts must be a positive whole number');

		Assert::exception(function () {
			new AdvisoryLock('key', [$this->createClient()], -1);
		}, 'Kdyby\RedisActiveLock\InvalidArgumentException', 'Acquire timeout must be a positive whole number of seconds');
	}



	public function testLockWithInvalidTtl()
	{
		$lock = new AdvisoryLock('key', [$this->createClient()]);

		Assert::exception(function () use ($lock) {
			$lock->lock(-1);
		}, 'Kdyby\RedisActiveLock\InvalidArgumentException', 'Time to live must a positive whole number');
	}



	public function testLockRepeatedly()
	{
		$lock = new AdvisoryLock('key', [$this->createClient()]);
		$lock->lock(1000);

		Assert::exception(function () use ($lock) {
			$lock->lock(1000);
		}, 'Kdyby\RedisActiveLock\LockException', 'The key "key" is already locked');
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
