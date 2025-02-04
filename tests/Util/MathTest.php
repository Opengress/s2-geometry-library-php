<?php
declare(strict_types=1);
namespace Util;

use PHPUnit\Framework\TestCase;

class MathTest extends TestCase {
    /**
     * @dataProvider providerRemainderSimple
     */
    public function testRemainderSimple(float $x, float $y, float $expected) {
        if (is_nan($expected)) {
            self::assertNan(Math::remainderSimple($x, $y));
        } else {
            // compute this perfectly and PHP will still eat it when you return the value
            self::assertEqualsWithDelta($expected, Math::remainderSimple($x, $y), 1e-14);
        }
    }

    public static function providerRemainderSimple(): array {
        return [
                [3, 2, -1],
                [4, 2, 0],
                [10, 3, 1],
                [1, 0, NAN],
                [-1, 0, NAN],
                [11, 3, -1],
                [27, 4, -1],
                [28, 5, -2],
                [17.8, 4, 1.8],
                [17.8, 4.1, 1.4],
                [-16.3, 4.1, 0.0999999999999979],
                [17.8, -4.1, 1.4],
                [-17.8, -4.1, -1.4],
                [31.34, 2.2, 0.5399999999999974],
                [-21, 7, -0.],
                [NAN, 0, NAN],
//                [-2.34, NAN, -2.34],
        ];
    }

    /**
     * @dataProvider providerRemainderIEEE
     */
    public function testRemainderIEEE(float $x, float $y, float $expected) {
        if (is_nan($expected)) {
            self::assertNan(Math::remainderIEEE($x, $y));
        } else {
            self::assertEquals($expected, Math::remainderIEEE($x, $y));
        }
    }

    public static function providerRemainderIEEE(): array {
        return [
                [31.34, 2.2, 0.5399999999999974],
                [-21, 7, -0.],
                [NAN, 0, NAN],
            // changed to match java and c++ versions and the comments
                [-2.34, NAN, NAN],
        ];
    }
}
