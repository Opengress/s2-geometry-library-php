<?php
declare(strict_types=1);
namespace Util;

use PHPUnit\Framework\TestCase;

class MathTest extends TestCase {
    /**
     * @dataProvider providerIEEEremainder
     */
    public function testIEEEremainder(float $x, float $y, float $expected) {
        if (is_nan($expected)) {
            self::assertNan(Math::IEEEremainder($x, $y));
        } else {
            self::assertEquals($expected, Math::IEEEremainder($x, $y));
        }
    }

    public function providerIEEEremainder() {
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
        ];
    }
}
