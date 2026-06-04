<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ilub\plugin\SelfEvaluation\Dataset\Statistics;

final class StatisticsTest extends TestCase
{
    private Statistics $statistics;

    public function setUp(): void
    {
        $this->statistics = new Statistics();
    }

    public function testConstruct(): void
    {
        $this->assertSame(Statistics::class, $this->statistics::class);
    }

    public function testGetMeanFromDataOnEmpty(): void
    {
        $this->assertEquals(null, $this->statistics->getMeanFromData([]));
    }

    public function testGetMeanFromDataOnOneEntry(): void
    {
        $this->assertEquals(0, $this->statistics->getMeanFromData([0]));
        $this->assertEquals(1, $this->statistics->getMeanFromData([1]));
    }

    public function testGetMeanFromDataFromMultipleEntries(): void
    {
        $this->assertEqualsWithDelta(0.5, $this->statistics->getMeanFromData([0, 1]), PHP_FLOAT_EPSILON);
        $this->assertEquals((1 + 2 + 30) / 3, $this->statistics->getMeanFromData([1, 2, 30]));
    }

    public function testValueToPercentage(): void
    {
        $this->assertEquals(0, $this->statistics->valueToPercentage(0));
        $this->assertEquals(100, $this->statistics->valueToPercentage(1));
        $this->assertEquals(30, $this->statistics->valueToPercentage(0.3));
        $this->assertEquals(200, $this->statistics->valueToPercentage(2));
        $this->assertEquals(100, $this->statistics->valueToPercentage(-1));
    }

    public function testFractionOfZero(): void
    {
        $this->assertEquals(0, $this->statistics->fractionOf(0, 0));
        $this->expectException(Exception::class);
        $this->assertNull($this->statistics->fractionOf(1, 0));
    }

    public function testFractionOf(): void
    {
        $this->assertEquals(1, $this->statistics->fractionOf(1, 1));
        $this->assertSame(1 / 3, $this->statistics->fractionOf(1, 3));
    }

    public function testPercentageOf(): void
    {
        $this->assertEquals(100, $this->statistics->percentageOf(1, 1));
        $this->assertSame(1 / 3 * 100, $this->statistics->percentageOf(1, 3));
    }

    public function testArraySumFractionOfMaxSumPossible(): void
    {
        $this->assertEquals(0, $this->statistics->arraySumFractionOfMaxSumPossible([0], 1));
        $this->assertEquals(1, $this->statistics->arraySumFractionOfMaxSumPossible([1], 1));
        $this->assertSame((1 + 2) / (2 * 2), $this->statistics->arraySumFractionOfMaxSumPossible([1, 2], 2));
        $this->assertSame((1 + 2 + 30) / (3 * 100), $this->statistics->arraySumFractionOfMaxSumPossible([1, 2, 30], 100));
    }

    public function testGetMinKeyAndValueFromArray(): void
    {
        $this->assertSame([0, 0], $this->statistics->getMinKeyAndValueFromArray([0]));
        $this->assertSame([0, 1], $this->statistics->getMinKeyAndValueFromArray([1]));
        $this->assertSame([1, 3], $this->statistics->getMinKeyAndValueFromArray([5, 3, 7]));
    }

    public function testGetMinKeyAndValueFromAssArray(): void
    {
        $this->assertSame(["id1", 0], $this->statistics->getMinKeyAndValueFromArray(["id1" => 0]));
        $this->assertSame(["id1", 1], $this->statistics->getMinKeyAndValueFromArray(["id1" => 1]));
        $this->assertSame(["id2", 3], $this->statistics->getMinKeyAndValueFromArray(["id1" => 5, "id2" => 3, "id3" => 7]));
    }

    public function testGetMaxKeyAndValueFromArray(): void
    {
        $this->assertSame([0, 0], $this->statistics->getMaxKeyAndValueFromArray([0]));
        $this->assertSame([0, 1], $this->statistics->getMaxKeyAndValueFromArray([1]));
        $this->assertSame([2, 7], $this->statistics->getMaxKeyAndValueFromArray([5, 3, 7]));
    }

    public function testGetMaxKeyAndValueFromAssArray(): void
    {
        $this->assertSame(["id1", 0], $this->statistics->getMaxKeyAndValueFromArray(["id1" => 0]));
        $this->assertSame(["id1", 1], $this->statistics->getMaxKeyAndValueFromArray(["id1" => 1]));
        $this->assertSame(["id3", 7], $this->statistics->getMaxKeyAndValueFromArray(["id1" => 5, "id2" => 3, "id3" => 7]));
    }

    public function testGetVarianzFromValues(): void
    {
        $this->assertEquals(0, $this->statistics->getVarianzFromValues([1]));
        $this->assertSame(((1 - 1.5) ** 2 + (2 - 1.5) ** 2) / 2, $this->statistics->getVarianzFromValues([1, 2]));
        $this->assertSame(((1 - 11) ** 2 + (2 - 11) ** 2 + (30 - 11) ** 2) / 3, $this->statistics->getVarianzFromValues([1, 2, 30]));
    }

    public function testGetStandardDeviationFromValuesAndAverage(): void
    {
        $this->assertSame(sqrt(0), $this->statistics->getStandardDeviation([1]));
        $this->assertSame(sqrt(((1 - 1.5) ** 2 + (2 - 1.5) ** 2) / 2), $this->statistics->getStandardDeviation([1, 2]));
        $this->assertSame(sqrt(((1 - 11) ** 2 + (2 - 11) ** 2 + (30 - 11) ** 2) / 3), $this->statistics->getStandardDeviation([1, 2, 30]));
    }
}
