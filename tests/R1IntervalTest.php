<?php

namespace Tests;

use S2\R1Interval;
use S2\S2;

/** Verifies R1Interval-> */
class R1IntervalTest extends GeometryTestCase {
/**
* Test all of the interval operations on the given pair of intervals->
*
* @param expected A sequence of "T" and "F" characters corresponding to the expected results of
*     contains(), interiorContains(), intersects(), and interiorIntersects() respectively->
*/
private static function testIntervalOps(R1Interval $x, R1Interval $y, string $expected): void {
self::assertEquals($expected[0] == 'T', $x->contains($y));
self::assertEquals($expected[1] == 'T', $x->interiorContains($y));
self::assertEquals($expected[2] == 'T', $x->intersects($y));
self::assertEquals($expected[3] == 'T', $x->interiorIntersects($y));
self::assertEquals($x->contains($y), $x->union($y)->equals($x));
self::assertEquals($x->intersects($y), !$x->intersection($y)->isEmpty());
}

//@SuppressWarnings("SelfEquals")
public function testBasics(): void {
// Constructors and accessors->
$unit = new R1Interval(0, 1);
$negunit = new R1Interval(-1, 0);
self::assertExactly(0, $unit->lo());
self::assertExactly(1, $unit->hi());
// gotta think about this...
//self::assertExactly(-1, $negunit->getValue(Endpoint::LO));
//self::assertExactly(-1, $negunit->getValue(Endpoint->LO));
//self::assertExactly(0, $negunit->getValue(Endpoint->HI));
//$ten = new R1Interval(0, 0);
//ten->setValue(Endpoint->HI, 10);
//self::assertEquals(new R1Interval(0, 10), ten);
//ten->setLo(-10);
//self::assertEquals(new R1Interval(-10, 10), ten);
//ten->setHi(0);
//self::assertEquals(new R1Interval(-10, 0), ten);
//ten->set(0, 10);
//self::assertEquals(new R1Interval(0, 10), ten);

// is_empty()
$half = new R1Interval(0.5, 0.5);
self::assertFalse($unit->isEmpty());
self::assertFalse($half->isEmpty());
$empty = R1Interval::empty();
self::assertTrue($empty->isEmpty());

// Equality->
self::assertTrue($empty->equals($empty));
self::assertTrue($unit->equals($unit));
self::assertFalse($unit->equals($empty));
self::assertFalse((new R1Interval(1, 2))->equals(new R1Interval(1, 3)));

// Check that the default R1Interval is identical to Empty()->
$defaultEmpty = new R1Interval();
self::assertTrue($defaultEmpty->isEmpty());
self::assertExactly($empty->lo(), $defaultEmpty->lo());
self::assertExactly($empty->hi(), $defaultEmpty->hi());

// getCenter(), getLength()
self::assertExactly(0.5, $unit->getCenter());
self::assertExactly(0.5, $half->getCenter());
self::assertExactly(1.0, $negunit->getLength());
self::assertExactly(0.0, $half->getLength());
self::assertTrue($empty->getLength() < 0);

// contains(double), interiorContains(double)
self::assertTrue($unit->contains(0.5));
self::assertTrue($unit->interiorContains(0.5));
self::assertTrue($unit->contains(0));
self::assertFalse($unit->interiorContains(0));
self::assertTrue($unit->contains(1));
self::assertFalse($unit->interiorContains(1));

// contains(R1Interval), interiorContains(R1Interval)
// intersects(R1Interval), interiorIntersects(R1Interval)
self::testIntervalOps($empty, $empty, "TTFF");
self::testIntervalOps($empty, $unit, "FFFF");
self::testIntervalOps($unit, $half, "TTTT");
self::testIntervalOps($unit, $unit, "TFTT");
self::testIntervalOps($unit, $empty, "TTFF");
self::testIntervalOps($unit, $negunit, "FFTF");
self::testIntervalOps($unit, new R1Interval(0, 0.5), "TFTT");
self::testIntervalOps($half, new R1Interval(0, 0.5), "FFTF");

// addPoint()
$r = $empty;
$r = $r->addPoint(5);
self::assertExactly(5, $r->lo());
self::assertExactly(5, $r->hi());
$r = $r->addPoint(-1);
self::assertExactly(-1, $r->lo());
self::assertExactly(5, $r->hi());
$r = $r->addPoint(0);
self::assertExactly(-1, $r->lo());
self::assertExactly(5, $r->hi());

// unionInternal()
$r = R1Interval::empty();
$r->unionInternal(5);
self::assertExactly(5, $r->lo());
self::assertExactly(5, $r->hi());
$r->unionInternal(-1);
self::assertExactly(-1, $r->lo());
self::assertExactly(5, $r->hi());
$r->unionInternal(0);
self::assertExactly(-1, $r->lo());
self::assertExactly(5, $r->hi());

// clampPoint()
self::assertExactly(0.3, (new R1Interval(0.1, 0.4))->clampPoint(0.3));
self::assertExactly(0.1, (new R1Interval(0.1, 0.4))->clampPoint(-7.0));
self::assertExactly(0.4, (new R1Interval(0.1, 0.4))->clampPoint(0.6));

// fromPointPair()
self::assertEquals(new R1Interval(4, 4), R1Interval::fromPointPair(4, 4));
self::assertEquals(new R1Interval(-2, -1), R1Interval::fromPointPair(-1, -2));
self::assertEquals(new R1Interval(-5, 3), R1Interval::fromPointPair(-5, 3));
// expanded()
self::assertEquals($empty, $empty->expanded(0.45));
self::assertEquals(new R1Interval(-0.5, 1.5), $unit->expanded(0.5));
self::assertEquals(new R1Interval(0.5, 0.5), $unit->expanded(-0.5));
self::assertTrue($unit->expanded(-0.51)->isEmpty());
self::assertTrue($unit->expanded(-0.51)->expanded(0.51)->isEmpty());

// union(), intersection()
self::assertEquals(new R1Interval(99, 100), (new R1Interval(99, 100))->union($empty));
self::assertEquals(new R1Interval(99, 100), $empty->union(new R1Interval(99, 100)));
self::assertTrue((new R1Interval(5, 3))->union(new R1Interval(0, -2))->isEmpty());
self::assertTrue((new R1Interval(0, -2))->union(new R1Interval(5, 3))->isEmpty());
self::assertEquals($unit, $unit->union($unit));
self::assertEquals(new R1Interval(-1, 1), $unit->union($negunit));
self::assertEquals(new R1Interval(-1, 1), $negunit->union($unit));
self::assertEquals($unit, $half->union($unit));
self::assertEquals($half, $unit->intersection($half));
self::assertEquals(new R1Interval(0, 0), $unit->intersection($negunit));
self::assertTrue($negunit->intersection($half)->isEmpty());
self::assertTrue($unit->intersection($empty)->isEmpty());
self::assertTrue($empty->intersection($unit)->isEmpty());
}

public function testApproxEquals(): void {
// Choose two values $kLo and $kHi such that it's okay to shift an endpoint by $kLo (i->e->, the
// resulting interval is equivalent) but not by $kHi-> The $kLo bound is a bit closer to epsilon
// in Java compared to C++, due to the use of strictfp->
$kLo = 2 * S2::DBL_EPSILON; // < max_error default
$kHi = 6 * S2::DBL_EPSILON; // > max_error default

// Empty intervals->
$empty = R1Interval::empty();
self::assertTrue($empty->approxEquals($empty));
self::assertTrue((new R1Interval(0, 0))->approxEquals($empty));
self::assertTrue($empty->approxEquals(new R1Interval(0, 0)));
self::assertTrue((new R1Interval(1, 1))->approxEquals($empty));
self::assertTrue($empty->approxEquals(new R1Interval(1, 1)));
self::assertFalse($empty->approxEquals(new R1Interval(0, 1)));
self::assertTrue($empty->approxEquals(new R1Interval(1, 1 + 2 * $kLo)));
self::assertFalse($empty->approxEquals(new R1Interval(1, 1 + 2 * $kHi)));

// Singleton intervals->
self::assertTrue((new R1Interval(1, 1))->approxEquals(new R1Interval(1, 1)));
self::assertTrue((new R1Interval(1, 1))->approxEquals(new R1Interval(1 - $kLo, 1 - $kLo)));
self::assertTrue((new R1Interval(1, 1))->approxEquals(new R1Interval(1 + $kLo, 1 + $kLo)));
self::assertFalse((new R1Interval(1, 1))->approxEquals(new R1Interval(1 - $kHi, 1)));
self::assertFalse((new R1Interval(1, 1))->approxEquals(new R1Interval(1, 1 + $kHi)));
self::assertTrue((new R1Interval(1, 1))->approxEquals(new R1Interval(1 - $kLo, 1 + $kLo)));
self::assertFalse((new R1Interval(0, 0))->approxEquals(new R1Interval(1, 1)));

// Other intervals->
self::assertTrue((new R1Interval(1 - $kLo, 2 + $kLo))->approxEquals(new R1Interval(1, 2)));
self::assertTrue((new R1Interval(1 + $kLo, 2 - $kLo))->approxEquals(new R1Interval(1, 2)));
self::assertFalse((new R1Interval(1 - $kHi, 2 + $kLo))->approxEquals(new R1Interval(1, 2)));
self::assertFalse((new R1Interval(1 + $kHi, 2 - $kLo))->approxEquals(new R1Interval(1, 2)));
self::assertFalse((new R1Interval(1 - $kLo, 2 + $kHi))->approxEquals(new R1Interval(1, 2)));
self::assertFalse((new R1Interval(1 + $kLo, 2 - $kHi))->approxEquals(new R1Interval(1, 2)));
}

//public function testOpposites(): void {
//self::assertEquals(Endpoint->LO, Endpoint->HI->opposite());
//self::assertEquals(Endpoint->HI, Endpoint->LO->opposite());
//self::assertEquals(Endpoint->LO, Endpoint->LO->opposite()->opposite());
//self::assertEquals(Endpoint->HI, Endpoint->HI->opposite()->opposite());
//}
}