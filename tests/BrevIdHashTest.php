<?php

/*
 * Copyright (c) 2024 Stallon.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace BrevId\Tests;

use PHPUnit\Framework\TestCase;
use BrevId\BrevId;


class BrevIdHashTest extends TestCase
{
    private static \ReflectionMethod $method;

    private static int $maxDigitsLimit;

    public static function setUpBeforeClass(): void
    {
        $reflector = new \ReflectionClass(BrevId::class);
        self::$method = $reflector->getMethod('hashToFixedDigits');
        self::$method->setAccessible(true);

        self::$maxDigitsLimit = min(strlen(dechex(PHP_INT_MAX)), 15);
    }

    public function testNumDigitsMin(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("numDigits must be between 1 and " . self::$maxDigitsLimit);
        self::$method->invoke(null, 'abcd', 0);
    }

    public function testNumDigitsMax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("numDigits must be between 1 and " . self::$maxDigitsLimit);
        self::$method->invoke(null, 'abcd', 2555);
    }

    public function testHash(): void
    {
        $input = 'abcd';
        $numDigits = 2;
        $expected = 48;
        $this->assertEquals($expected, self::$method->invoke(null, $input, $numDigits));
    }
}
