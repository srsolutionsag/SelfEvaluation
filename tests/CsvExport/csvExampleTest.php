<?php

declare(strict_types=1);

use ilub\plugin\SelfEvaluation\CsvExport\csvExportTable;
use ilub\plugin\SelfEvaluation\CsvExport\csvExport;
use ilub\plugin\SelfEvaluation\CsvExport\csvExportRow;
use PHPUnit\Framework\TestCase;

/*
 * @author       Timon Amstutz <timon.amstutz@ilub.unibe.ch>
 * @version $Id$
 */

final class csvExampleTest extends TestCase
{
    private array $columns = ["column1", "column2", "column3"];
    private array $rows_values = [
        ["ce1r1c1", "e1r1c2", "e1r1c3"],
        ["ae1r2c1", "e1r2c2", "e1r2c3"],
        ["be1r3c1", "e1r3c2", "e1r3c3"]
    ];
    private array $rows_paired = [
        ["column1" => "e2r1c1", "column2" => "e2r1c2", "column3" => "e2r1c3"],
        ["column1" => "e2r2c1", "column3" => "e2r2c3"],
        ["column3" => "e2r3c3"],
        ["columnX" => "e2r4cX", "column1" => "e2r4c1"]
    ];

    private csvExport $csvExport;

    protected function setUp(): void
    {
        $this->csvExport = new csvExport();
    }

    public function testInitTable(): void
    {
        $this->assertSame(0, $this->csvExport->getTable()->getColumns()->count());
        $this->assertSame(0, $this->csvExport->getTable()->getColumns()->count());
    }

    /**
     * @depends testInitTable
     */
    public function testAddFromArray(): void
    {
        $this->csvExport->getTable()->addColumnsAndValuesFromArrays($this->columns, $this->rows_values);
        $this->assertSame(3, $this->csvExport->getTable()->getColumns()->count());
        $this->assertSame(3, $this->csvExport->getTable()->getColumns()->count());
        $this->assertSame(['column1' => 'column1', 'column2' => 'column2', 'column3' => 'column3'], $this->csvExport->getTable()->getColumns()->getColumnNamesAsArray());
        $expected_table = [
            0 => ["column1" => "column1", "column2" => "column2", "column3" => "column3"],
            1 => [0 => "ce1r1c1", 1 => "e1r1c2", 2 => "e1r1c3"],
            2 => [0 => "ae1r2c1", 1 => "e1r2c2", 2 => "e1r2c3"],
            3 => [0 => "be1r3c1", 1 => "e1r3c2", 2 => "e1r3c3"]
        ];
        $this->assertEquals($this->csvExport->getTable()->getTableAsArray(), $expected_table);
    }

    /**
     * @depends testInitTable
     */
    public function testPositioningOfColumns(): csvExportTable
    {
        $this->csvExport->getTable()->addColumnsAndValuesFromArrays($this->columns, $this->rows_values);
        $this->csvExport->getTable()->setPositionOfColumn('column1', 3);
        $this->csvExport->getTable()->setPositionOfColumn('column2', 1);
        $this->csvExport->getTable()->setPositionOfColumn('column3', 2);

        $expectd_table = [
            0 => ["column2" => "column2", "column3" => "column3", "column1" => "column1"],
            1 => [0 => "e1r1c2", 1 => "e1r1c3", 2 => "ce1r1c1"],
            2 => [0 => "e1r2c2", 1 => "e1r2c3", 2 => "ae1r2c1"],
            3 => [0 => "e1r3c2", 1 => "e1r3c3", 2 => "be1r3c1"]
        ];
        $this->assertEquals($this->csvExport->getTable()->getTableAsArray(), $expectd_table);
        return $this->csvExport->getTable();
    }

    /**
     * @depends testInitTable
     */
    public function testOrderingOfRows(): void
    {
        $this->csvExport->getTable()->addColumnsAndValuesFromArrays($this->columns, $this->rows_values);
        $this->csvExport->getTable()->setSortColumn("column1");

        $expected_table = [
            0 => ["column1" => "column1", "column2" => "column2", "column3" => "column3"],
            1 => [0 => "ae1r2c1", 1 => "e1r2c2", 2 => "e1r2c3"],
            2 => [0 => "be1r3c1", 1 => "e1r3c2", 2 => "e1r3c3"],
            3 => [0 => "ce1r1c1", 1 => "e1r1c2", 2 => "e1r1c3"]
        ];
        $this->assertEquals($this->csvExport->getTable()->getTableAsArray(), $expected_table);
    }

    /**
     * @depends testInitTable
     */
    public function testAddFromPairedArray(): void
    {
        foreach ($this->rows_paired as $row_paired) {
            $row = new csvExportRow();
            $row->addValuesFromPairedArray($row_paired);
            $this->csvExport->getTable()->addRow($row);
        }
        $expected_table = [
            0 => ["column1" => "column1", "column2" => "column2", "column3" => "column3", "columnX" => "columnX"],
            1 => [0 => "e2r1c1", 1 => "e2r1c2", 2 => "e2r1c3", 3 => null],
            2 => [0 => "e2r2c1", 1 => null, 2 => "e2r2c3", 3 => null],
            3 => [0 => null, 1 => null, 2 => "e2r3c3", 3 => null],
            4 => [0 => "e2r4c1", 1 => null, 2 => null, 3 => "e2r4cX"],
        ];
        $this->assertEquals($this->csvExport->getTable()->getTableAsArray(), $expected_table);
    }

    /**
     * @depends testInitTable
     */
    public function testJoinTable(): void
    {
        foreach ($this->rows_paired as $row_paired) {
            $row = new csvExportRow();
            $row->addValuesFromPairedArray($row_paired);
            $this->csvExport->getTable()->addRow($row);
        }
        $this->csvExport->getTable()->addColumnsAndValuesFromArrays($this->columns, $this->rows_values);
        $expected_table = [
            0 => ["column1" => "column1", "column2" => "column2", "column3" => "column3", "columnX" => "columnX"],
            1 => [0 => "e2r1c1", 1 => "e2r1c2", 2 => "e2r1c3", 3 => null],
            2 => [0 => "e2r2c1", 1 => null, 2 => "e2r2c3", 3 => null],
            3 => [0 => null, 1 => null, 2 => "e2r3c3", 3 => null],
            4 => [0 => "e2r4c1", 1 => null, 2 => null, 3 => "e2r4cX"],
            5 => [0 => "ce1r1c1", 1 => "e1r1c2", 2 => "e1r1c3", 3 => null],
            6 => [0 => "ae1r2c1", 1 => "e1r2c2", 2 => "e1r2c3", 3 => null],
            7 => [0 => "be1r3c1", 1 => "e1r3c2", 2 => "e1r3c3", 3 => null]
        ];
        $this->assertEquals($this->csvExport->getTable()->getTableAsArray(), $expected_table);
    }

    /**
     * @depends testInitTable
     */
    public function testJoinTableReversed(): void
    {
        $this->csvExport->getTable()->addColumnsAndValuesFromArrays($this->columns, $this->rows_values);

        foreach ($this->rows_paired as $row_paired) {
            $row = new csvExportRow();
            $row->addValuesFromPairedArray($row_paired);
            $this->csvExport->getTable()->addRow($row);
        }
        $expected_table = [
            0 => ["column1" => "column1", "column2" => "column2", "column3" => "column3", "columnX" => "columnX"],
            1 => [0 => "ce1r1c1", 1 => "e1r1c2", 2 => "e1r1c3", 3 => null],
            2 => [0 => "ae1r2c1", 1 => "e1r2c2", 2 => "e1r2c3", 3 => null],
            3 => [0 => "be1r3c1", 1 => "e1r3c2", 2 => "e1r3c3", 3 => null],
            4 => [0 => "e2r1c1", 1 => "e2r1c2", 2 => "e2r1c3", 3 => null],
            5 => [0 => "e2r2c1", 1 => null, 2 => "e2r2c3", 3 => null],
            6 => [0 => null, 1 => null, 2 => "e2r3c3", 3 => null],
            7 => [0 => "e2r4c1", 1 => null, 2 => null, 3 => "e2r4cX"],
        ];
        $this->assertEquals($this->csvExport->getTable()->getTableAsArray(), $expected_table);
    }

    /**
     * @depends testInitTable
     */
    public function testJoinSortOrderTable(): void
    {
        $this->csvExport->getTable()->addColumnsAndValuesFromArrays($this->columns, $this->rows_values);

        foreach ($this->rows_paired as $row_paired) {
            $row = new csvExportRow();
            $row->addValuesFromPairedArray($row_paired);
            $this->csvExport->getTable()->addRow($row);
        }

        $this->csvExport->getTable()->setSortColumn("column2");
        $this->csvExport->getTable()->setPositionOfColumn("column2", -1);

        $expected_table = [
            0 => ["column2" => "column2", "column1" => "column1", "column3" => "column3", "columnX" => "columnX"],
            1 => [0 => null, 1 => "e2r2c1", 2 => "e2r2c3", 3 => null],
            2 => [0 => null, 1 => null, 2 => "e2r3c3", 3 => null],
            3 => [0 => null, 1 => "e2r4c1", 2 => null, 3 => "e2r4cX"],
            4 => [0 => "e1r1c2", 1 => "ce1r1c1", 2 => "e1r1c3", 3 => null],
            5 => [0 => "e1r2c2", 1 => "ae1r2c1", 2 => "e1r2c3", 3 => null],
            6 => [0 => "e1r3c2", 1 => "be1r3c1", 2 => "e1r3c3", 3 => null],
            7 => [0 => "e2r1c2", 1 => "e2r1c1", 2 => "e2r1c3", 3 => null],

        ];
        $this->assertEquals($this->csvExport->getTable()->getTableAsArray(), $expected_table);
    }
}
