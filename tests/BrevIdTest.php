<?php

/*
 * Copyright (c) 2024 Stallon.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BrevId\Tests;

use BrevId\BrevId;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SlopeIt\ClockMock\ClockMock;


class BrevIdTest extends TestCase
{
    use PHPMock;

    #[DataProvider('badConstructorIntParams')]
    public function testConstructorBadIntParams($startTimestamp, $minLength, $timeMagnitude, $hostMagnitude, $randMagnitude, $exceptionMessage)
    {
        ClockMock::freeze(new \DateTime('2024-04-01'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);

        new BrevId($startTimestamp, $minLength, $timeMagnitude, $hostMagnitude, $randMagnitude);
    }

    public static function badConstructorIntParams(): array
    {
        return [
            'startTimestamp below range' => [-1, 5, 1, 1, 1, 'Invalid startTimestamp: must be at least 0'],
            'startTimestamp above range' => [2713203137, 5, 1, 1, 1, 'Invalid startTimestamp: cannot be greater than 1711929600'],
            'minLength below range' => [0, 2, 1, 1, 1, 'Invalid minLength: must be at least ' . BrevId::MIN_LENGTH_LOWER_LIMIT],
            'minLength above range' => [0, 256, 1, 1, 1, 'Invalid minLength: cannot be greater than ' . BrevId::MIN_LENGTH_UPPER_LIMIT],
            'timeMagnitude below range' => [0, 5, 0, 1, 1, 'Invalid timeMagnitude: must be at least ' . BrevId::TIME_MAGNITUDE_LOWER_LIMIT],
            'timeMagnitude above range' => [0, 5, 10, 1, 1, 'Invalid timeMagnitude: cannot be greater than ' . BrevId::TIME_MAGNITUDE_UPPER_LIMIT],
            'hostMagnitude below range' => [0, 5, 1, 0, 1, 'Invalid hostMagnitude: must be at least ' . BrevId::HOST_MAGNITUDE_LOWER_LIMIT],
            'hostMagnitude above range' => [0, 5, 1, 16, 1, 'Invalid hostMagnitude: cannot be greater than ' . BrevId::HOST_MAGNITUDE_UPPER_LIMIT],
            'randMagnitude below range' => [0, 5, 1, 1, 0, 'Invalid randMagnitude: must be at least ' . BrevId::RAND_MAGNITUDE_LOWER_LIMIT],
            'randMagnitude above range' => [0, 5, 1, 1, 100, 'Invalid randMagnitude: cannot be greater than ' . BrevId::RAND_MAGNITUDE_UPPER_LIMIT],
        ];
    }

    public function testConstructorCharacterSetBelowRange()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Character set length must be at least 3');
        new BrevId(0, 5, 1, 1, 1, 'a');
    }

    public function testConstructorCharacterSetNonUnique()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Character set must contain unique characters');
        new BrevId(0, 5, 1, 1, 1, 'abcabc');
    }

    #[DataProvider('generationParamCombinations')]
    public function testGenerate($startTimestamp, $minLength, $timeMagnitude, $hostMagnitude, $randMagnitude, $expectedValue)
    {
        ClockMock::freeze(new \DateTime('2024-04-01 12:00:00'));

        $random = $this->getFunctionMock('BrevId', "rand");
        $random->expects($this->once())->willReturn(5);

        $brevId = new BrevId($startTimestamp, $minLength, $timeMagnitude, $hostMagnitude, $randMagnitude);
        $reflection = new \ReflectionClass($brevId);
        $reflection->getProperty('hashedHostname')->setValue($brevId, 500);
        $reflection->getProperty('pid')->setValue($brevId, 123);

        $this->assertEquals($expectedValue, $brevId->generate());
    }

    public static function generationParamCombinations(): array
    {
        return [
            'startTimestamp 0, minLength 5, timeMagnitude 1, hostMagnitude 1, randMagnitude 1' => [0, 5, 1, 1, 1, '5y1wpm669718pwrk'],
            'startTimestamp 1711843200, minLength 10, timeMagnitude 5, hostMagnitude 3, randMagnitude 5' => [1711843200, 10, 5, 3, 5, 'yuef4444sbg1xmpz'],
        ];
    }
}
