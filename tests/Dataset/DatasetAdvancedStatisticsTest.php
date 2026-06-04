<?php

declare(strict_types=1);

include_once "DatasetHelperTrait.php";

use PHPUnit\Framework\TestCase;
use ilub\plugin\SelfEvaluation\Dataset\Dataset;

final class DatasetAdvancedStatisticsTest extends TestCase
{
    use DatasetHelperTrait;

    private Dataset $dataset;
    protected ilDBInterface $db;

    protected function setUp(): void
    {
        $this->db = Mockery::mock("\ilDBInterface");
        $this->dataset = new Dataset($this->db);
        $this->dataset = $this->setUpDatasetWithThreeBlocks($this->dataset);
    }

    public function testGetOverallPercentage(): void
    {
        $this->assertEquals($this->getOverallPercentage(), $this->dataset->getOverallPercentage());
    }

    public function testGetOverallPercentageVarianz(): void
    {
        $this->assertEquals($this->getOverallPercentageVarianz(), $this->dataset->getOverallPercentageVarianz());
    }

    public function testGetOverallPercentageStandardabweichung(): void
    {
        $this->assertSame(sqrt($this->getOverallPercentageVarianz()), $this->dataset->getOverallPercentageStandardabweichung());
    }

    public function testGetPercentageStandardAbweichungPerBlock(): void
    {
        $this->assertEquals($this->getSdPerBlock(), $this->dataset->getPercentageStandardabweichungPerBlock());
    }
}
