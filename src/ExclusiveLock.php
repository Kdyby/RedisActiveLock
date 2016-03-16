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
use Nette;



/**
 * @author Ondřej Nešpor
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExclusiveLock
{

	/**
	 * @var \Redis
	 */
	private $redis;

	/**
	 * @var array
	 */
	private $keys = [];

	/**
	 * Duration of the lock, this is time in seconds, how long any other process can't work with the row.
	 *
	 * @var int
	 */
	public $duration = 15;

	/**
	 * When there are too many requests trying to acquire the lock, you can set this timeout,
	 * to make them manually die in case they would be taking too long and the user would lock himself out.
	 *
	 * @var bool
	 */
	public $acquireTimeout = FALSE;



	public function __construct(\Redis $redis)
	{
		$this->redis = $redis;
	}



	/**
	 * @return int
	 */
	public function calculateTimeout()
	{
		return time() + abs((int)$this->duration) + 1;
	}



	/**
	 * Tries to acquire a key lock, otherwise waits until it's released and repeats.
	 *
	 * @param string $key
	 * @throws LockException
	 * @return bool
	 */
	public function acquireLock($key)
	{
		if (isset($this->keys[$key])) {
			return $this->increaseLockTimeout($key);
		}

		$start = microtime(TRUE);

		$lockKey = $this->formatLock($key);
		$maxAttempts = 10;
		do {
			$sleepTime = 5000;
			do {
				if ($this->redis->setnx($lockKey, $timeout = $this->calculateTimeout())) {
					$this->keys[$key] = $timeout;
					return TRUE;
				}

				if ($this->acquireTimeout !== FALSE && (microtime(TRUE) - $start) >= $this->acquireTimeout) {
					throw LockException::acquireTimeout();
				}

				$lockExpiration = $this->redis->get($lockKey);
				$sleepTime += 2500;

			} while (empty($lockExpiration) || ($lockExpiration >= time() && !usleep($sleepTime)));

			$oldExpiration = $this->redis->getSet($lockKey, $timeout = $this->calculateTimeout());
			if ($oldExpiration === $lockExpiration) {
				$this->keys[$key] = $timeout;
				return TRUE;
			}

		} while (--$maxAttempts > 0);

		throw LockException::highConcurrency();
	}



	/**
	 * @param string $key
	 * @return bool
	 */
	public function release($key)
	{
		if (!array_key_exists($key, $this->keys)) {
			return FALSE;
		}

		if ($this->keys[$key] <= time()) {
			unset($this->keys[$key]);
			return FALSE;
		}

		$this->redis->del($this->formatLock($key));
		unset($this->keys[$key]);
		return TRUE;
	}



	/**
	 * @param string $key
	 * @throws LockException
	 * @return bool
	 */
	public function increaseLockTimeout($key)
	{
		if (!array_key_exists($key, $this->keys)) {
			return FALSE;
		}

		if ($this->keys[$key] <= time()) {
			throw LockException::durabilityTimedOut();
		}

		$oldTimeout = $this->redis->getSet($this->formatLock($key), $timeout = $this->calculateTimeout());
		if ((int)$oldTimeout !== (int)$this->keys[$key]) {
			throw LockException::invalidDuration();
		}
		$this->keys[$key] = $timeout;
		return TRUE;
	}



	/**
	 * Updates the indexing locks timeout.
	 */
	public function increaseLocksTimeout()
	{
		foreach ($this->keys as $key => $timeout) {
			$this->increaseLockTimeout($key);
		}
	}



	/**
	 * Release all acquired locks.
	 */
	public function releaseAll()
	{
		foreach ((array)$this->keys as $key => $timeout) {
			$this->release($key);
		}
	}



	/**
	 * @param string $key
	 * @return int
	 */
	public function getLockTimeout($key)
	{
		if (!array_key_exists($key, $this->keys)) {
			return 0;
		}

		return $this->keys[$key] - time();
	}



	/**
	 * @param string $key
	 * @return string
	 */
	protected function formatLock($key)
	{
		return $key . ':lock';
	}



	public function __destruct()
	{
		$this->releaseAll();
	}

}
