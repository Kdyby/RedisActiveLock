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

	public function testLockExpired()
	{
		Assert::exception(function () {
			$first = new AdvisoryLock('foo:bar', $this->createClient());

			Assert::true($first->lock(1));
			sleep(3);

			$first->increaseDuration();

		}, 'Kdyby\RedisActiveLock\LockException', 'Process ran too long. Increase lock duration, or extend lock regularly.');
	}



	public function testDeadlockHandling()
	{
		$first = new AdvisoryLock('foo:bar', $this->createClient());
		$second = new AdvisoryLock('foo:bar', $this->createClient());

		Assert::true($first->lock(1));
		sleep(3); // first died?

		Assert::true($second->lock(1));
		Assert::true($second->release());
		Assert::false($first->release());
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
