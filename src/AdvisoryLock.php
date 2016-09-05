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
 * @see http://redis.io/topics/distlock
 * @author Ondřej Nešpor
 * @author Filip Procházka <filip@prochazka.su>
 */
class AdvisoryLock
{

	const DEFAULT_MAX_ATTEMPTS = 10;
	const CLOCK_DRIFT_FACTORY = 0.01;
	const DEFAULT_TIMEOUT_MILLISECONDS = 15000;

	use Scream;

	/** @var string */
	private $key;

	/** @var string|NULL */
	private $lockToken;

	/** @var int|NULL */
	private $lockTimeoutMilliseconds;

	/** @var \Redis[] */
	private $servers;

	/** @var int */
	private $maxAttempts;

	/** @var int|null */
	private $acquireTimeout;

	/** @var int */
	private $quorum;



	/**
	 * @param string $key
	 * @param \Redis[] $servers
	 * @param int|NULL $acquireTimeout in seconds
	 * @param int $maxAttempts
	 */
	public function __construct($key, $servers, $acquireTimeout = NULL, $maxAttempts = self::DEFAULT_MAX_ATTEMPTS)
	{
		foreach ($servers as $connection) {
			if (!$connection instanceof \Redis) {
				throw new InvalidArgumentException('Given connections must be array of connected \\Redis instances');
			}
		}
		if (count($servers) <= 0) {
			throw new InvalidArgumentException('At leasts one server is required');
		}
		if (!is_string($key)) {
			throw new InvalidArgumentException('Given key is not a string');
		}
		if (!is_int($maxAttempts) || $maxAttempts <= 0) {
			throw new InvalidArgumentException('Max attempts must be a positive whole number');
		}
		if ($acquireTimeout !== NULL && (!is_int($acquireTimeout) || $acquireTimeout <= 0)) {
			throw new InvalidArgumentException('Acquire timeout must be a positive whole number of seconds');
		}

		$this->key = $key;
		$this->servers = $servers;
		$this->maxAttempts = $maxAttempts;
		$this->acquireTimeout = $acquireTimeout;
		$this->quorum = min(count($servers), count($servers) / 2 + 1);
	}



	public function __destruct()
	{
		$this->release();
	}



	/**
	 * Tries to acquire a key lock, otherwise waits until it's released and repeats.
	 * Returns remaining ttl in milliseconds.
	 *
	 * @param int $ttlMilliseconds
	 * @throws \Kdyby\RedisActiveLock\AcquireTimeoutException
	 * @return int
	 */
	public function lock($ttlMilliseconds = self::DEFAULT_TIMEOUT_MILLISECONDS)
	{
		if ($this->lockTimeoutMilliseconds !== NULL) {
			throw LockException::alreadyLocked($this->key);
		}

		if ($ttlMilliseconds <= 0) {
			throw new InvalidArgumentException('Time to live must a positive whole number');
		}

		$this->lockToken = $this->generateRandomToken();

		$lockingStartTime = microtime(TRUE);

		for ($retry = 1 ; $retry <= $this->maxAttempts ; $retry++) {
			if ($this->acquireTimeout !== NULL && (microtime(TRUE) - $lockingStartTime) >= $this->acquireTimeout) {
				throw AcquireTimeoutException::acquireTimeout();
			}

			$lockedInstances = 0;
			$attemptStartTime = microtime(TRUE) * 1000;
			foreach ($this->servers as $server) {
				if ($this->lockInstance($server, $ttlMilliseconds)) {
					$lockedInstances++;
				}
			}
			$attemptFinishTime = microtime(TRUE) * 1000;

			// Add 2 milliseconds to the drift to account for Redis expires precision,
			// which is 1 millisecond, plus 1 millisecond min drift for small TTLs.
			// thx https://github.com/ronnylt/redlock-php
			$drift = ($ttlMilliseconds * self::CLOCK_DRIFT_FACTORY) + 2;
			$validForMilliSeconds = $ttlMilliseconds - ($attemptFinishTime - $attemptStartTime) - $drift;

			if ($lockedInstances >= $this->quorum && $validForMilliSeconds > 0) {
				return $this->lockTimeoutMilliseconds = $attemptFinishTime + $validForMilliSeconds;
			}

			foreach ($this->servers as $server) {
				$this->unlockInstance($server);
			}

			if ($retry < $this->maxAttempts) {
				// Wait a random delay before to retry
				$minDelay = 100 * pow(2, $retry);
				usleep(1000 * mt_rand($minDelay, $minDelay * 2));
			}
		}

		throw AcquireTimeoutException::allAttemptsUsed();
	}



	/**
	 * Releases lock.
	 */
	public function release()
	{
		if ($this->lockTimeoutMilliseconds === NULL) {
			return FALSE;
		}

		if ($this->lockTimeoutMilliseconds <= microtime(TRUE) * 1000) {
			$this->lockTimeoutMilliseconds = NULL; // released by Redis timeout
			return TRUE;
		}

		foreach ($this->servers as $server) {
			$this->unlockInstance($server);
		}
		$this->lockTimeoutMilliseconds = NULL;
		return TRUE;
	}



	/**
	 * @param string $key
	 * @return string
	 */
	protected function formatLock($key)
	{
		return $key . ':lock';
	}



	/**
	 * @return string
	 */
	protected function generateRandomToken()
	{
		return bin2hex(random_bytes(10));
	}



	/**
	 * @param \Redis $server
	 * @param int $ttlMilliseconds
	 * @return bool
	 */
	private function lockInstance(\Redis $server, $ttlMilliseconds)
	{
		return $server->set($this->formatLock($this->key), $this->lockToken, ['NX', 'PX' => $ttlMilliseconds]);
	}



	/**
	 * @param \Redis $server
	 */
	private function unlockInstance(\Redis $server)
	{
		$script = <<<LUA
			if redis.call("GET", KEYS[1]) == ARGV[1] then
				return redis.call("DEL", KEYS[1])
			else
				return 0
			end
LUA;
		$server->eval($script, [$this->formatLock($this->key), $this->lockToken], 1);
	}

}
