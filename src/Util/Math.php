<?php
declare(strict_types=1);
namespace Util;

class Math {
    const TWO_1023 = 8.98846567431158e307; // Long bits 0x7fe0000000000000L.

    /**
     * Get the IEEE 754 floating point remainder on two numbers. This is the
     * value of <code>x - y * <em>n</em></code>, where <em>n</em> is the closest
     * double to <code>x / y</code> (ties go to the even n); for a zero
     * remainder, the sign is that of <code>x</code>. If either argument is NaN,
     * the first argument is infinite, or the second argument is zero, the result
     * is NaN; if x is finite but y is infinite, the result is x.
     *
     * @param float $x the dividend (the top half)
     * @param float $y the divisor (the bottom half)
     * @return float the IEEE 754-defined floating point remainder of x/y
     * @see #rint(double)
     */
    public static function IEEEremainder(float $x, float $y): float {
        if (is_nan($x) || is_nan($y) || $x===-INF || !($x < INF) || $y===0.) {
            return NAN;
        }
        return $x - $y * (round($x / $y));
    }
}
