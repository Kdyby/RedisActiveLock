<?php

/**
 * @testCase
 */

namespace KdybyTests\RedisActiveLock;

use Kdyby\RedisActiveLock\ExclusiveLock;
use Nette;
use Nette\Utils\AssertionException;
use Tester;
use Tester\Assert;



require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExclusiveLockTest extends Tester\TestCase
{

	public function testLockExpired()
	{
		Assert::exception(function () {
			$first = new ExclusiveLock($this->createClient());
			$first->duration = 1;

			Assert::true($first->acquireLock('foo:bar'));
			sleep(3);

			$first->increaseLockTimeout('foo:bar');

		}, 'Kdyby\Redis\LockException', 'Process ran too long. Increase lock duration, or extend lock regularly.');
	}



	public function testDeadlockHandling()
	{
		$first = new ExclusiveLock($this->createClient());
		$first->duration = 1;

		$second = new ExclusiveLock($this->createClient());
		$second->duration = 1;

		Assert::true($first->acquireLock('foo:bar'));
		sleep(3); // first died?

		Assert::true($second->acquireLock('foo:bar'));
	}



	/**
	 * @return \Redis
	 */
	private function createClient()
	{
		$client = new \Redis();
		if (!$client->connect('127.0.0.1')) {
			throw new \RuntimeException(sprintf('Connection error: %s', $client->getLastError()));
		}

		return $client;
	}

}



(new ExclusiveLockTest())->run();
