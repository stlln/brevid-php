<?php

/*
 * Copyright (c) 2024 Stallon.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BrevId;

use Sqids\Sqids;

define('BREVID_MAX_HEX_INT_WIDTH', min(strlen(dechex(PHP_INT_MAX)), 15));

class BrevId
{

    public const DEFAULT_CHARACTER_SET = "abcdefghjkmnpqrstuwxyz123456789";
    final public const MIN_LENGTH_LOWER_LIMIT = 3;
    final public const MIN_LENGTH_UPPER_LIMIT = 255;
    final public const TIME_MAGNITUDE_LOWER_LIMIT = 1;
    final public const TIME_MAGNITUDE_UPPER_LIMIT = 5;
    final public const HOST_MAGNITUDE_LOWER_LIMIT = 1;
    final public const HOST_MAGNITUDE_UPPER_LIMIT = BREVID_MAX_HEX_INT_WIDTH;
    final public const RAND_MAGNITUDE_LOWER_LIMIT = 1;
    public const RAND_MAGNITUDE_UPPER_LIMIT = 10;
    final public const CHARACTER_SET_MIN_LENGTH = 3;

    protected int $startTimestamp = 0;
    protected int $minLength = 5;
    protected int $timeMagnitude = 1;
    protected int $hostMagnitude = 1;
    protected int $randMagnitude = 1;
    protected string $characterSet = self::DEFAULT_CHARACTER_SET;

    private int $timeExponent = 10;
    private int $startTime = 0;
    private int $randMax = 99;
    private int $hashedHostname;
    private int $pid;


    public function __construct(
        ?int    $startTimestamp = null,
        ?int    $minLength = null,
        ?int    $timeMagnitude = null,
        ?int    $hostMagnitude = null,
        ?int    $randMagnitude = null,
        ?string $characterSet = null
    )
    {
        if ($timeMagnitude !== null) {
            try {
                self::validateRange($timeMagnitude, self::TIME_MAGNITUDE_LOWER_LIMIT, self::TIME_MAGNITUDE_UPPER_LIMIT);
                $this->timeMagnitude = $timeMagnitude;
                $this->timeExponent = pow(10, $timeMagnitude - 1);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('Invalid timeMagnitude: ' . $e->getMessage());
            }
        }

        if ($startTimestamp !== null) {
            try {
                self::validateRange($startTimestamp, 0, time());
                $this->startTimestamp = $startTimestamp;
                $this->startTime = $startTimestamp * $this->timeExponent;
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('Invalid startTimestamp: ' . $e->getMessage());
            }
        }

        if ($minLength !== null) {
            try {
                self::validateRange($minLength, self::MIN_LENGTH_LOWER_LIMIT, self::MIN_LENGTH_UPPER_LIMIT);
                $this->minLength = $minLength;
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('Invalid minLength: ' . $e->getMessage());
            }
        }

        if ($hostMagnitude !== null) {
            try {
                self::validateRange($hostMagnitude, self::HOST_MAGNITUDE_LOWER_LIMIT, self::HOST_MAGNITUDE_UPPER_LIMIT);
                $this->hostMagnitude = $hostMagnitude;
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('Invalid hostMagnitude: ' . $e->getMessage());
            }
        }

        if ($randMagnitude !== null) {
            try {
                self::validateRange($randMagnitude, self::RAND_MAGNITUDE_LOWER_LIMIT, self::RAND_MAGNITUDE_UPPER_LIMIT);
                $this->randMagnitude = $randMagnitude;
                $this->randMax = pow(10, $randMagnitude) - 1;
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('Invalid randMagnitude: ' . $e->getMessage());
            }
        }

        if ($characterSet !== null) {
            try {
                self::validateCharacterSet($characterSet);
                $this->characterSet = $characterSet;
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('Invalid character set: ' . $e->getMessage());
            }
        }

        $hostname = gethostname();

        if ($hostname === false) {
            throw new \RuntimeException('Unable to determine hostname');
        } else {
            $this->hashedHostname = $this->hashToFixedDigits($hostname, $this->hostMagnitude);
        }

        $pid = getmypid();

        if ($pid === false) {
            throw new \RuntimeException('Unable to determine process ID');
        } else {
            $this->pid = $pid;
        }
    }

    protected static function validateRange(int $value, int $min, int $max): bool
    {
        if ($value < $min) {
            throw new \InvalidArgumentException("must be at least $min");
        }

        if ($value > $max) {
            throw new \InvalidArgumentException("cannot be greater than $max");
        }

        return true;
    }

    protected static function validateCharacterSet(string $characterSet): bool
    {
        if (strlen($characterSet) < self::CHARACTER_SET_MIN_LENGTH) {
            throw new \InvalidArgumentException('Character set length must be at least 3');
        }

        if (mb_strlen($characterSet) != strlen($characterSet)) {
            throw new \InvalidArgumentException('Character set cannot contain multibyte characters');
        }

        if (count(array_unique(str_split($characterSet))) !== strlen($characterSet)) {
            throw new \InvalidArgumentException('Character set must contain unique characters');
        }

        return true;
    }

    protected static function hashToFixedDigits(string $input, int $maxDigits): int
    {
        // Ensure numDigits is within a reasonable range
        if ($maxDigits < 1 || $maxDigits > BREVID_MAX_HEX_INT_WIDTH) {
            throw new \InvalidArgumentException("numDigits must be between 1 and " . BREVID_MAX_HEX_INT_WIDTH);
        }

        // Hash the input using a standard hash function
        $hash = hash('sha256', $input);

        // Extract a portion of the hash that will be used for numeric conversion
        $numericValue = hexdec(substr($hash, 0, BREVID_MAX_HEX_INT_WIDTH));

        // Reduce numericValue to the desired number of digits
        $reducedValue = $numericValue % pow(10, $maxDigits);

        // Adds the minimum value required to ensure numericValue has the desired digits
        $minValue = pow(10, $maxDigits - 1);

        if ($reducedValue < $minValue) {
            $reducedValue += $minValue;
        }

        return $reducedValue;
    }

    public function generate(): string
    {
        $sqids = new Sqids(alphabet: $this->characterSet, minLength: $this->minLength);

        $timestampFromStart = (int)round((microtime(true) * $this->timeExponent) - $this->startTime);

        $random = rand(0, $this->randMax);

        return $sqids->encode([$timestampFromStart, $this->hashedHostname, $this->pid, $random]);
    }
}
