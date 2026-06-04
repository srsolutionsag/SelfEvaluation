<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\UIHelper\Chart;

use ilChartGrid;
use ilChartDataLines;
use stdClass;

class LeftRightChart extends ilChartGrid
{
    use ChartHelper;

    public function __construct(string $a_id)
    {
        parent::__construct($a_id);
        $this->setSize($this->getCanvasWidth(), $this->getCanvasHeight());
        $this->setColors($this->getChartColors());
        $this->setLegend($this->getLegend());
        $this->setAutoResize(true);

        $this->setXAxisToInteger(false);
        $this->setYAxisToInteger(true);
    }

    #[\Override]
    public function getDataInstance($a_type = null): \ilChartData
    {
        return new ilChartDataLines();
    }

    #[\Override]
    public function parseGlobalOptions(stdClass $a_options): void
    {
        parent::parseGlobalOptions($a_options);
        $x_tick_key = array_keys($this->getTicks()["x"]);

        $a_options->{"yaxis"}->labelWidth = 0;

        $a_options->{"xaxis"}->min = min($x_tick_key);
        $a_options->{"xaxis"}->max = max($x_tick_key);

        $a_options->{"grid"} = new stdClass();
        $a_options->{"grid"}->{"markings"} = [];
        $a_options->{"grid"}->{"markings"}[0] = new stdClass();
        $a_options->{"grid"}->{"markings"}[0]->{"xaxis"} = new stdClass();
        $middle = (min($x_tick_key) + max($x_tick_key)) / 2;
        $a_options->{"grid"}->{"markings"}[0]->{"xaxis"}->from = $middle;
        $a_options->{"grid"}->{"markings"}[0]->{"xaxis"}->to = $middle;
        $a_options->{"grid"}->{"markings"}[0]->{"color"} = new stdClass();
        $a_options->{"grid"}->{"markings"}[0]->{"color"} = "#333333";
    }
}
