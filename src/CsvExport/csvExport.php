<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\CsvExport;

class csvExport
{
    protected csvExportTable $table;

    public function __construct(?csvExportTable $table = null)
    {
        $this->table = $table ?: new csvExportTable();
    }

    public function getCsvExport(string $delimiter = ";", string $enclosure = '"'): void
    {
        // output headers so that the file is downloaded rather than displayed
        header('Content-Encoding: UTF-8');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=data.csv');

        // create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');

        foreach ($this->getTable()->getTableAsArray() as $row) {
            /**
             * $utf8_row = [];
             * foreach ($row as $entry) {
             * $utf8_row[] = $this->convertExcelUtf8($entry);
             * }
             **/
            fputcsv($output, $row, $delimiter, $enclosure, escape: '\\');
        }
    }

    protected function convertExcelUtf8(string $string): array|string
    {
        $string = str_replace("Ä", mb_convert_encoding("Ä", 'UTF-16LE', 'UTF-8'), $string);
        $string = str_replace("Ü", mb_convert_encoding("Ü", 'UTF-16LE', 'UTF-8'), $string);
        $string = str_replace("Ö", mb_convert_encoding("Ö", 'UTF-16LE', 'UTF-8'), $string);
        $string = str_replace("ä", mb_convert_encoding("ä", 'UTF-16LE', 'UTF-8'), $string);
        $string = str_replace("ü", mb_convert_encoding("ü", 'UTF-16LE', 'UTF-8'), $string);
        return str_replace("ö", mb_convert_encoding("ö", 'UTF-16LE', 'UTF-8'), $string);
    }

    public function setTable(csvExportTable $table): void
    {
        $this->table = $table;
    }

    public function getTable(): csvExportTable
    {
        return $this->table;
    }
}
