<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\RedisActiveLock;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
interface Lock
{

	const DEFAULT_TIMEOUT = 15;



	/**
	 * Tries to acquire a key lock, otherwise waits until it's released and repeats.
	 *
	 * @param int $duration in seconds
	 * @param int $acquireTimeout in seconds
	 * @throws AcquireTimeoutException
	 * @return bool
	 */
	public function lock($duration = self::DEFAULT_TIMEOUT, $acquireTimeout = NULL);



	/**
	 * Releases lock.
	 */
	public function release();



	/**
	 * Increases the duration of lock by given amount, or by default value.
	 *
	 * @param int $duration
	 * @throws LockException
	 * @return bool
	 */
	public function increaseDuration($duration = self::DEFAULT_TIMEOUT);

}
