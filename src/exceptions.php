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

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LockException extends \RuntimeException implements Exception
{

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

	const ALL_ATTEMPTS_USED = 1;
	const ACQUIRE_TIMEOUT = 2;



	/**
	 * @return AcquireTimeoutException
	 */
	public static function allAttemptsUsed()
	{
		return new static('Lock couldn\'t be acquired. all attempts were used.', self::ALL_ATTEMPTS_USED);
	}



	/**
	 * @return AcquireTimeoutException
	 */
	public static function acquireTimeout()
	{
		return new static('Lock couldn\'t be acquired in reasonable time.', self::ACQUIRE_TIMEOUT);
	}

}
