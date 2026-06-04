<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\UIHelper\Chart;

use ilChartGrid;
use ilChartDataBars;
use stdClass;

class BarChart extends ilChartGrid
{
    use ChartHelper;

    public const BAR_WIDTH = 0.5;
    protected bool $show_average_line = false;
    protected array $standardabweichung_data = [];
    protected array $values_for_standardabweichung = [];
    protected bool $show_varianz = false;
    protected float $average = 0;

    public function __construct(string $a_id)
    {
        parent::__construct($a_id);
        $this->setSize($this->getCanvasWidth(), $this->getCanvasHeight());
        $this->setColors($this->getChartColors());
        $this->setLegend($this->getLegend());
        $this->setAutoResize(true);

        $this->setYAxisToInteger(true);
    }

    #[\Override]
    public function getDataInstance($a_type = null): ilChartDataBars
    {
        $data = new ilChartDataBars();
        $data->setBarOptions(self::BAR_WIDTH);
        return $data;
    }

    #[\Override]
    public function parseGlobalOptions(stdClass $a_options): void
    {
        parent::parseGlobalOptions($a_options);
        $a_options->{"grid"} = new stdClass();
        $a_options->{"grid"}->{"markings"} = [];

        if ($this->isShowAverageLine()) {
            $a_options->{"grid"}->{"markings"}[0] = new stdClass();
            $a_options->{"grid"}->{"markings"}[0]->{"yaxis"} = new stdClass();
            $a_options->{"grid"}->{"markings"}[0]->{"yaxis"}->from = $this->getAverage();
            $a_options->{"grid"}->{"markings"}[0]->{"yaxis"}->to = $this->getAverage();
            $a_options->{"grid"}->{"markings"}[0]->{"color"} = new stdClass();
            $a_options->{"grid"}->{"markings"}[0]->{"color"} = "#333333";
        }

        if ($this->isShowVarianz()) {
            $y_values = $this->getValuesForStandardabweichung();
            $x = 0;
            foreach ($this->getStandardabweichungData() as $key => $sd_data) {
                $a_options->{"grid"}->{"markings"}[$x] = new stdClass();
                $a_options->{"grid"}->{"markings"}[$x]->{"yaxis"} = new stdClass();
                $a_options->{"grid"}->{"markings"}[$x]->{"yaxis"}->from = $y_values[$key] - $sd_data / 2;
                $a_options->{"grid"}->{"markings"}[$x]->{"yaxis"}->to = $y_values[$key] + $sd_data / 2;

                $a_options->{"grid"}->{"markings"}[$x]->{"xaxis"} = new stdClass();
                $a_options->{"grid"}->{"markings"}[$x]->{"xaxis"}->from = $x + 1;
                $a_options->{"grid"}->{"markings"}[$x]->{"xaxis"}->to = $x + 1;

                $a_options->{"grid"}->{"markings"}[$x]->{"color"} = new stdClass();
                $a_options->{"grid"}->{"markings"}[$x]->{"color"} = "#333333";
                $x++;
            }
        }
    }

    public function isShowAverageLine(): bool
    {
        return $this->show_average_line;
    }

    public function setShowAverageLine(bool $show_average_line): void
    {
        $this->show_average_line = $show_average_line;
    }

    public function getAverage(): float
    {
        return $this->average;
    }

    public function setAverage(float $average): void
    {
        $this->average = $average;
    }

    public function isShowVarianz(): bool
    {
        return $this->show_varianz;
    }

    public function setShowVarianz(bool $show_varianz): void
    {
        $this->show_varianz = $show_varianz;
    }

    public function getStandardabweichungData(): array
    {
        return $this->standardabweichung_data;
    }

    public function setStandardabweichungData(array $standardabweichung_data): void
    {
        $this->standardabweichung_data = $standardabweichung_data;
    }

    public function getValuesForStandardabweichung(): array
    {
        return $this->values_for_standardabweichung;
    }

    public function setValuesForStandardabweichung(array $values_for_standardabweichung): void
    {
        $this->values_for_standardabweichung = $values_for_standardabweichung;
    }
}
