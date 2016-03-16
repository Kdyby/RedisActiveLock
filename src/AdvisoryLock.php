<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\RedisActiveLock;

use Kdyby;
use Kdyby\StrictObjects\Scream;



/**
 * @author Ondřej Nešpor
 * @author Filip Procházka <filip@prochazka.su>
 */
class AdvisoryLock implements Lock
{

	use Scream;

	/**
	 * @var string
	 */
	private $key;

	/**
	 * @var \Redis
	 */
	private $redis;

	/**
	 * @var integer|NULL
	 */
	private $lockTimeout;



	public function __construct($key, \Redis $redis)
	{
		$this->key = $key;
		$this->redis = $redis;
	}



	public function __destruct()
	{
		$this->release();
	}



	/**
	 * {@inheritdoc}
	 */
	public function lock($duration = self::DEFAULT_TIMEOUT, $acquireTimeout = NULL)
	{
		if ($this->lockTimeout !== NULL) {
			throw LockException::alreadyLocked($this->key);
		}

		if ($duration <= 0) {
			throw InvalidArgumentException::invalidDuration($duration);
		}
		if ($acquireTimeout !== NULL) {
			if ($acquireTimeout <= 0) {
				throw InvalidArgumentException::invalidAcquireTimeout($acquireTimeout);
			}
			if ($duration < $acquireTimeout) {
				throw InvalidArgumentException::acquireTimeoutTooBig();
			}
		}

		$start = microtime(TRUE);

		$lockKey = $this->formatLock($this->key);
		$maxAttempts = 10;
		do {
			$sleepTime = 5000;
			do {
				$timeout = $this->calculateTimeout($duration);
				if ($this->redis->set($lockKey, $timeout, ['NX', 'PX' => $timeout])) {
					$this->lockTimeout = $timeout;
					return TRUE;
				}

				if ($acquireTimeout !== NULL && (microtime(TRUE) - $start) >= $acquireTimeout) {
					throw AcquireTimeoutException::acquireTimeout();
				}

				$lockExpiration = $this->redis->get($lockKey);
				$sleepTime += 2500;

			} while (empty($lockExpiration) || ($lockExpiration >= time() && !usleep($sleepTime)));

			$oldExpiration = $this->redis->getSet($lockKey, $timeout = $this->calculateTimeout($duration));
			if ($oldExpiration === $lockExpiration) {
				$this->lockTimeout = $timeout;
				return TRUE;
			}

		} while (--$maxAttempts > 0);

		throw AcquireTimeoutException::highConcurrency();
	}



	/**
	 * {@inheritdoc}
	 */
	public function release()
	{
		if ($this->lockTimeout === NULL) {
			return FALSE;
		}

		if ($this->lockTimeout <= time()) {
			$this->lockTimeout = NULL;
			return FALSE;
		}

		$this->redis->del($this->formatLock($this->key));
		$this->lockTimeout = NULL;
		return TRUE;
	}



	/**
	 * {@inheritdoc}
	 */
	public function increaseDuration($duration = self::DEFAULT_TIMEOUT)
	{
		if ($duration <= 0) {
			throw InvalidArgumentException::invalidDuration($duration);
		}

		if ($this->lockTimeout === NULL) {
			throw LockException::notLocked($this->key);
		}

		if ($this->lockTimeout <= time()) {
			throw LockException::durabilityTimedOut();
		}

		$oldTimeout = $this->redis->getSet($this->formatLock($this->key), $timeout = $this->calculateTimeout($duration));
		if ((int) $oldTimeout !== (int) $this->lockTimeout) {
			throw LockException::invalidDuration();
		}
		$this->lockTimeout = $timeout;
		return TRUE;
	}



	/**
	 * @return int
	 */
	public function calculateRemainingTimeout()
	{
		return $this->lockTimeout !== NULL ? $this->lockTimeout - time() : 0;
	}



	/**
	 * @param int $duration
	 * @return int
	 */
	protected function calculateTimeout($duration)
	{
		return time() + ((int) $duration) + 1;
	}



	/**
	 * @param string $key
	 * @return string
	 */
	protected function formatLock($key)
	{
		return $key . ':lock';
	}

}
