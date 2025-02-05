<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use S2\S1Angle;
use S2\S2;
use S2\S2Cell;
use S2\S2CellId;
use S2\S2CellUnion;
use S2\S2LatLng;
use S2\S2Point;
use S2\S2Region;

/** Common code for geometry tests. */
class GeometryTestCase extends TestCase {
    /**
     * How many ULP's (Units in the Last Place) we want to tolerate when comparing two numbers. The
     * gtest framework for C++ also uses 4, and documents why in gtest-internal.h.
     */
    public const int MAX_ULPS = 4;

    /**
     * A not very accurate value for the radius of the Earth. For testing only, matches the value
     * in the C++ implementation S2Testing::kEarthRadiusKm.
     */
    public const float APPROXIMATE_EARTH_RADIUS_KM = 6371.01;

    function setUp(): void {
    }

    /**
     * Returns the next (more positive) representable number after x.
     */
    public static function nextUp(float $x): float {
// huh?
        return ++$x;
    }

    /*
    * Returns the adjacent, more negative, representable number before x.
    */
    public static function nextDown(float $x): float {
        return --$x;
    }

    /**
     * Tests that two float values have the same sign and are within 'maxUlps' of each other.
     */
    public static function assertDoubleUlpsWithin(string $message, float $a, float $b, int $maxUlps): void {
// The IEEE standard says that any comparison operation involving a NAN must return false.
        if (is_nan($a)) {
            self::fail("'a' is NaN. " . $message);
        }
        if (is_nan($b)) {
            self::fail(/**/ "'b' is NaN. " . $message);
        }

// Handle the exact equality case fast, as well as special cases like +0 == -0, and infinity.
        if ($a == $b) {
            return;
        }

// If the signs are different, don't compare by ULP.
        if (self::copySign(1.0, $a) != self::copySign(1.0, $b)) {
            self::fail($a . " and " . $b . " are not equal and have different signs. " . $message);
        }

        $uA = self::fromLongBits(self::doubleToLongBits($b));
        $uB = self::fromLongBits(self::doubleToLongBits($b));
        $ulpsDiff = self::unsignedSub($uA, $uB);
        self::assertTrue(gmp_abs($ulpsDiff) <= $maxUlps, $a . " and " . $b . " differ by " . $ulpsDiff . " units in the last place, expected <= "
            . $maxUlps . ". " . $message);
    }

    /**
     * Returns the magnitude value with the sign of the sign number
     *
     * @param float $magnitude
     * @param float $sign
     *
     * @return float $magnitude with the sign of $sign
     */
    public static function copySign(float $magnitude, float $sign): float {
        return $sign >= 0
            ? abs($magnitude)
            : -abs($magnitude);
    }

    public static function doubleToLongBits(float $value) {
        if (is_nan($value)) {
            // FIXME it is probably better to return NaN
            return gmp_init("0x7ff8000000000000", 16); // ""Java's canonical NaN""
        }
        $packed = pack("d", $value);
        $unpacked = unpack("Q", $packed)[1];
        return gmp_init($unpacked);
    }


    public static function doubleToRawLongBits(float $value) {
        $packed = pack("d", $value);
        $unpacked = unpack("Q", $packed)[1];
        return gmp_init($unpacked);
    }

    public static function unsignedSub($a, $b) {
        return gmp_sub($a, $b); // GMP subtraction
    }

    public static function fromLongBits($long): GMP {
        if ($long < 0) {
            // Convert signed negative to unsigned using bitwise & with 0xFFFFFFFFFFFFFFFF
            return gmp_and(gmp_init($long, 10), gmp_init("0xFFFFFFFFFFFFFFFF", 16));
        }
        return gmp_init($long, 10);
    }

    /**
     * Tests that two float values are almost equal. Uses ULP-based comparison to automatically pick
     * an error bound that is appropriate for the operands. "Almost equal" here means "a is at most
     * MAX_ULPS (which is 4) ULP's away from b".
     *
     * <p>This is similar to the implementation of "AlmostEquals" which underlies the gtest floating
     * point comparison macros. So, our Java unit tests can use assertDoubleEquals(a, b) and have the
     * same behavior as our equivalent C++ unit tests using EXPECT_DOUBLE_EQ(a, b).
     *
     * <p>There is one exception for comparing by ULPs: If a and b are both non-zero, and have
     * different signs, they are never considered almost equals, as a ULP based comparison of floating
     * point numbers with different signs doesn't make sense. For details, read:
     * <a>https://randomascii.wordpress.com/2012/02/25/comparing-floating-point-numbers-2012-edition/</a>
     */
    public static function assertDoubleEquals(string|float $message, float $a, ?float $b): void {
        if (!is_string($message) || empty($b)) {
            $b = $a;
            $a = $message;
            $message = null;
        }
        self::assertDoubleUlpsWithin($message, $a, $b, self::MAX_ULPS);
    }

    /** Returns true if and only if 'x' is less than or equal to 'y'. */
    public static function assertLessOrEqual($x, $y): void {
        self::assertTrue($x <= $y, "Expected " . $x . " <= " . $y . " but it is not.");
    }

    /** Returns true if and only if 'x' is greater than or equal to 'y'. */
    public static function assertGreaterOrEqual($x, $y): void {
        self::assertTrue($x >= $y, "Expected " . $x . " >= " . $y . " but it is not.");
    }

    /**
     * Assert that {@code val1} and {@code val2} are within the given {@code absError} of each other.
     *
     * <p>This implementation matches that of DoubleNearPredFormat() in gtest.cc, so our Java unit
     * tests can use assertDoubleNear() and get the same behaviour as C++ tests using EXPECT_NEAR().
     */
    public static function assertDoubleNear(float $val1, float $val2, float $absError = 1e-9): void {
        $diff = abs($val1 - $val2);
        if ($diff <= $absError) {
            return;
        }

// Find the value which is closest to zero.
        $minAbs = min(abs($val1), abs($val2));
        // Find the distance to the next float from that value.
        $epsilon = self::nextUp($minAbs) - $minAbs;

        // Detect the case where absError is so small that "near" is effectively the same as "equal",
        // and give an informative error message so that the situation can be more easily understood.
        // Don't do an epsilon check if absError is zero because that implies that an equality check
        // was actually intended.
        if (!is_nan($val1) && !is_nan($val2) && $absError > 0 && $absError < $epsilon) {
            self::fail("The difference between val1 (" . $val1 . ") and val2 (" . $val2 . ") is " . $diff
                . ".\nThe absError parameter (" . $absError . ") is smaller than the minimum "
                . "distance between doubles for numbers of this magnitude, which is " . $epsilon
                . ", thus making this 'Near' check equivalent to an exact equality check.");
        }
        self::fail("The difference between " . $val1 . " and " . $val2 . " is " . $diff . ", which exceeds "
            . $absError . " by " . ($diff - $absError) . ".");
    }


    /**
     * Checks that two doubles are exactly equal, although note that 0.0 exactly equals -0.0: use
     * assertIdentical to differentiate positive and negative zero.
     *
     * <p>(JUnit 3 allows leaving off the third parameter of assertEquals, with a default of zero, but
     * JUnit4 does not. In S2 we often want to check that two doubles are exactly equal, so this is a
     * bit cleaner.)
     */
    public static function assertExactly($message, mixed $expected, mixed $actual = null): void {
        if (!is_string($message) || empty($actual)) {
            self::assertEquals($message, $expected);
        } else {
            self::assertEquals($expected, $actual, $message);
        }
    }

    // /**
    // * Assert that the S1ChordAngle {@code actual} is exactly equal to {@code expected}.
    // */
    //public static function assertExactly(S1ChordAngle $expected, S1ChordAngle $actual) {
    //    assertEquals($expected->getLength2(), $actual->getLength2(), 0.0);
    //}

    /** Checks that two doubles are exactly equal and have the same sign. */
    public static function assertIdentical(float $a, float $b): void {
        self::assertEquals(self::doubleToRawLongBits($a), self::doubleToRawLongBits($b));
    }

//    /**
//     * Checks that the 3D distance between {@code expected} and {@code actual} is at most {@code eps}
//     * units.
//     */
//    public static function assertEquals(S2Point $expected, S2Point $actual, float $eps): void {
//        self::assertTrue(
//            "expected: " . $expected . " but was: " . $actual, $expected->getDistance2($actual) < $eps * $eps);
//    }

    /**
     * Checks that the distance between {@code expected} and {@code actual} is at most
     * {@code maxErrorRadians}.
     */
    public static function assertApproxEquals(S2Point $expected, S2Point $actual, float $maxErrorRadians): void {
        self::assertTrue(S2::approxEquals($expected, $actual, $maxErrorRadians), "expected: " . $expected . " but was: " . $actual);
    }

//    /** Assets that the actual shape {@link S2ShapeUtil#equals equals} the expected shape. */
//    public static function assertShapesEqual(S2Shape $expected, S2Shape $actual): void {
//        self::assertTrue(S2ShapeUtil::equals($expected, $actual));
//    }

    /**
     * Returns an S1Angle approximately equal to the given distance in meters. Use S2Earth where
     * accuracy is important.
     */
    public static function metersToAngle(float $meters): S1Angle {
        return self::kmToAngle(0.001 * $meters);
    }

    /**
     * Returns an S1Angle approximately equal to the given distance in kilometers. Use S2Earth where
     * accuracy is important.
     */
    public static function kmToAngle(float $km): S1Angle {
        return S1Angle::sradians($km / self::APPROXIMATE_EARTH_RADIUS_KM);
    }

//    /** A helper for testing {@link S2Coder} implementations. */
//    static function roundtrip(S2Coder $coder, mixed $value) {
//        $output = new ByteArrayOutputStream();
//        try {
//            $coder->encode($value, $output);
//            return $coder->decode(Bytes::fromByteArray($output->toByteArray()));
//        } catch (Throwable $e) {
//            throw new AssertionError($e);
//        }
//    }

    /** A concise expression to get an S2Point from a lat,lng in degrees. */
    protected function ll(float $lat, float $lng): S2Point {
        return S2LatLng::fromDegrees($lat, $lng)->toPoint();
    }

//    /**
//     * As {@link #checkCovering(S2Region, S2CellUnion, boolean, S2CellId)}, but creates a default and
//     * invalid S2CellId for the last argument.
//     */
//    function checkCovering(S2Region $region, S2CellUnion $covering, bool $checkTight): void {
//        checkCovering($region, $covering, $checkTight, new S2CellId());
//    }

    /**
     * Checks that "covering" completely covers the given region. If "checkTight" is true, also checks
     * that it does not contain any cells that do not intersect the given region. ("id" is only used
     * internally.)
     */
    function checkCovering(S2Region $region, S2CellUnion $covering, bool $checkTight, S2CellId $id = new S2CellId()): void {
        if ($id->isValid()) {
            for ($face = 0; $face < 6;
                 ++$face) {
                $this->checkCovering($region, $covering, $checkTight, S2CellId::fromFacePosLevel($face, 0, 0));
            }
            return;
        }

        if (!$region->mayIntersect(new S2Cell($id))) {
            // If region does not intersect id, then neither should the covering.
            if ($checkTight) {
                self::assertFalse($covering->intersects($id));
            }

        } else {
            if (!$covering->contains($id)) {
                // The region may intersect id, but we can't assert that the covering
                // intersects id because we may discover that the region does not actually
                // intersect upon further subdivision. (MayIntersect is not exact.)
                self::assertFalse($region->contains(new S2Cell($id)));
                self::assertFalse($id->isLeaf());
                $end = $id->childEnd();
                for ($child = $id->childBegin(); !$child->equals($end); $child = $child->next()) {
                    $this->checkCovering($region, $covering, $checkTight, $child);
                }
            }
        }
    }

//    /**
//     * Asserts that the first N calls to {@link S2Shape#getEdge} returns edges are equivalent to those
//     * specified in the edge list, described by the following format:
//     *
//     * <pre>edge1, edge2, ..., edgeN</pre>
//     *
//     * where edges are:
//     *
//     * <pre>point1 | point2</pre>
//     *
//     * and points are:
//     *
//     * <pre>lat:lng</pre>
//     *
//     * <p>Example:
//     *
//     * <table>
//     * <tr><td>Two edges</td><td><pre>1:2 | 2:3, 4:5 | 6:7</pre></td></tr>
//     * </table>
//     */
//    static function checkFirstNEdges(S2Shape $shape, string $edgeList): void {
//        $result = new MutableEdge();
//        consumeIndexedEdges(
//            $edgeList,
//            function (int $offset, MutableEdge $expected) use ($result, $shape) {
//                $shape->getEdge($edgeId, $result);
//                self::assertEquals($expected->getStart(), $result->getStart());
//                self::assertEquals($expected->getEnd(), $result->getEnd());
//            });
//    }
//
//    /**
//     * Similar to {@link #checkFirstNEdges}, except that {@link S2Shape#getChainEdge} is used with
//     * specified chain id to return the edges.
//     */
//    static function checkFirstNChainEdges(S2Shape $shape, int $chainId, string $edgeList): void {
//        $result = new MutableEdge();
//        consumeIndexedEdges(
//            $edgeList,
//            function (int $offset, MutableEdge $expected) use ($result, $chainId, $shape) {
//                $shape->getChainEdge($chainId, $offset, $result);
//                self::assertEquals($expected->getStart(), $result->getStart());
//                self::assertEquals($expected->getEnd(), $result->getEnd());
//            });
//    }
//
//private interface IndexedEdgeConsumer {
//    function apply(int $index, MutableEdge $edge): void;
//}

//    private static function consumeIndexedEdges(string edgeStrList, IndexedEdgeConsumer consumer): void {
//        MutableEdge edge = new MutableEdge();
//                                int i = 0;
//                                for (String edgeStr : Splitter . on(',') . split(edgeStrList)) {
//                                    int j = edgeStr . indexOf('|');
//                                edge . set(
//                                    makePoint(edgeStr . substring(0, j) . trim()), makePoint(edgeStr . substring(j + 1) . trim()));
//                                consumer . apply(i++, edge);
//                                }
//                                }
//
//                                /** Asserts that the first N chain starts are equivalent to those specified in the list. */
//                                static void checkFirstNChainStarts(S2Shape shape, int... starts) {
//    int chainId = 0;
//                                for (int start : starts) {
//                                    assertEquals(start, shape . getChainStart(chainId++));
//                                }
//}

///** Asserts that the first N chain lengths are equivalent to those specified in the list. */
//static void checkFirstNChainLengths(S2Shape shape, int... lengths) {
//    int chainId = 0;
//                                for (int length : lengths) {
//                                    assertEquals(length, shape . getChainLength(chainId++));
//                                }
//                                }


    /**
     * Returns a set of strings that are the "toToken" representation of the cell ids in the given
     * S2CellUnion or array of cell ids.
     */
    public static function toTokenSet(S2CellUnion|array $cells): array {
        $tokens = [];
        foreach ($cells as $cell) {
            $tokens[] = $cell->toToken();
        }
        return $tokens;
    }

    /**
     * Returns a set of strings that are the "toToken" representation of the given set of cell ids.
     */
//    public
//    static HashSet < String> toTokenSet(Set < S2CellId> cells) {
//    HashSet < String> tokens = new HashSet <> ();
//                                                    cells .foreach(cell->tokens . add(cell . toToken()));
//                                                    return tokens;
//                                                    }

//    /**
//     * Returns a set of Strings that are the "toToken" representations of the cell id of every node in
//     * the given density tree.
//     */
//    public static function toTokenSet(S2DensityTree tree): array {
//        HashSet < String> tokens = new HashSet <> ();
//                                                            tree . visitCells(
//                                                                (S2CellId cellId, S2DensityTree . Cell node) -> {
//            tokens . add(cellId . toToken());
//            return S2DensityTree . CellVisitor . Action . ENTER_CELL;
//        });
//                                                            return tokens;
//                                                            }

//    /** Returns a String representation of the given tree. */
//    public static function toString(S2DensityTree tree): string {
//        return decodedTreeToString(tree . decode());
//    }

//    /**
//     * Returns a multi-line string representation of a decoded S2DensityTree, using indenting to show
//     * the tree structure. Useful for debugging and tests.
//     */
//    public static string decodedTreeToString(Map < S2CellId, Long > decodedTree){
//StringBuilder sb = new StringBuilder();
//                                                            dumpTree(Arrays . asList(S2CellId . FACE_CELLS), decodedTree, 0, sb);
//                                                            return sb . toString();
//                                                            }

///** Recursive helper for decodedTreeToString. Appends to the given StringBuilder. */
//    private
//    static void dumpTree(
//    Iterable < S2CellId> start, Map < S2CellId, Long > decoded, int indent, StringBuilder sb) {
//    for (S2CellId id : start) {
//        if (decoded . containsKey(id)) {
//            for (int i = 0; i < indent {
//                ;
//            }
//
//i++) {
//    sb . append("    ");
//}
//                                                                sb . append(Platform . formatString("Level %s: node[%s] %s with weight %s\n",
//                                                                        id . level(), id . toToken(), id, decoded . get(id)));
//                                                                dumpTree(id . children(), decoded, indent + 1, sb);
//                                                                }
//    }
//                                                                }


}

///**
// * Like S2Shape.MutableEdge, but immutable. Overrides hashCode() and equals() so it can be used
// * as a HashMap key. This is not very memory efficient, but is convenient in tests. Equality to
// * a MutableEdge is also defined and works as expected.
// */
//class ImmutableEdge {
//    private S2Point $a;
//    private S2Point $b;
//
//    public function __construct(S2Point|MutableEdge $a, S2Point $b) {
//        if ($a instanceof MutableEdge) {
//            $this->a = $a->a;
//            $this->b = $a->b;
//        } else {
//            $this->a = $a;
//            $this->b = $b;
//        }
//    }
//
/////** Constructs an ImmutableEdge with the same endpoints as the given S2Shape.MutableEdge. */
////    public function __construct(MutableEdge $e) {
////    this . a = e . a;
////    this . b = e . b;
////}
//
//    /** Returns true iff 'point' is either endpoint of this edge. */
//    public
//    function isEndpoint(S2Point point): bool {
//        return a . equalsPoint(point) || b . equalsPoint(point);
//    }
//
//    /**
//     * Returns true if this ImmutableEdge has the same endpoints as the given S2Shape.MutableEdge
//     * currently does.
//     */
//    public boolean isEqualTo(MutableEdge other) {
//    return a . equalsPoint(other . a) && b . equalsPoint(other . b);
//}
//
///**
// * Returns true if this ImmutableEdge has the same endpoints as the other ImmutableEdge.
// */
//public
//function isEqualTo(ImmutableEdge other): bool {
//    return a . equalsPoint(other . a) && b . equalsPoint(other . b);
//}
//
////                                                                @Override
//public
//function equals(object other): bool {
//    if (other instanceof ImmutableEdge) {
//        return isEqualTo((ImmutableEdge) other);
//                                                                }
//    if (other instanceof MutableEdge) {
//        return isEqualTo((MutableEdge) other);
//                                                                }
//    return false;
//}
//
////                                                                @Override
//public
//function hashCode(): int {
//    return a . hashCode() * 3 + b . hashCode();
//}
//
//public
//function toDegreesString(): string {
//    return a . toDegreesString() + "-" + b . toDegreesString();
//}
//
////                                                                @Override
//public
//function toString(): string {
//    return toDegreesString();
//}
//}