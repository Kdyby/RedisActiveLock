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
class LockException extends \RuntimeException implements Exception
{

	const PROCESS_TIMEOUT = 1;
	const ACQUIRE_TIMEOUT = 2;



	/**
	 * @return LockException
	 */
	public static function highConcurrency()
	{
		return new static("Lock couldn't be acquired. Concurrency is way too high. I died of old age.", self::PROCESS_TIMEOUT);
	}



	/**
	 * @return LockException
	 */
	public static function acquireTimeout()
	{
		return new static("Lock couldn't be acquired. The locking mechanism is giving up. You should kill the request.", self::ACQUIRE_TIMEOUT);
	}



	/**
	 * @return LockException
	 */
	public static function durabilityTimedOut()
	{
		return new static("Process ran too long. Increase lock duration, or extend lock regularly.");
	}



	/**
	 * @return LockException
	 */
	public static function invalidDuration()
	{
		return new static("Some rude client have messed up the lock duration.");
	}

}
