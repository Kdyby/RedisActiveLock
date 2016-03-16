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
interface Exception
{

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class InvalidArgumentException extends \InvalidArgumentException implements Exception
{

	/**
	 * @param mixed $duration
	 * @return InvalidArgumentException
	 */
	public static function invalidDuration($duration)
	{
		return new static(sprintf('Durability must be positive whole number, but "%s" was given', $duration));
	}

	/**
	 * @param mixed $timeout
	 * @return InvalidArgumentException
	 */
	public static function invalidAcquireTimeout($timeout)
	{
		return new static(sprintf('Acquire timeout must be positive whole number or NULL, but "%s" was given', $timeout));
	}

	/**
	 * @return InvalidArgumentException
	 */
	public static function acquireTimeoutTooBig()
	{
		return new static('Acquire timeout should be lower than lock duration');
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LockException extends \RuntimeException implements Exception
{

	/**
	 * @return LockException
	 */
	public static function durabilityTimedOut()
	{
		return new static('Process ran too long. Increase lock duration, or extend lock regularly.');
	}



	/**
	 * @return LockException
	 */
	public static function invalidDuration()
	{
		return new static('Some rude client have messed up the lock duration.');
	}



	/**
	 * @param string $key
	 * @return LockException
	 */
	public static function notLocked($key)
	{
		return new static(sprintf('The key "%s" has not yet been locked', $key));
	}



	/**
	 * @param string $key
	 * @return LockException
	 */
	public static function alreadyLocked($key)
	{
		return new static(sprintf('The key "%s" is already locked', $key));
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class AcquireTimeoutException extends LockException
{

	const PROCESS_TIMEOUT = 1;
	const ACQUIRE_TIMEOUT = 2;



	/**
	 * @return AcquireTimeoutException
	 */
	public static function highConcurrency()
	{
		return new static(
			'Lock couldn\'t be acquired. Concurrency is way too high. I died of old age.',
			self::PROCESS_TIMEOUT
		);
	}



	/**
	 * @return AcquireTimeoutException
	 */
	public static function acquireTimeout()
	{
		return new static(
			'Lock couldn\'t be acquired in reasonable time. The locking mechanism is giving up. You should kill the request.',
			self::ACQUIRE_TIMEOUT
		);
	}

}
