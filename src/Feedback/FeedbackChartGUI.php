<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Feedback;

use ilGlobalPageTemplate;
use ilRepositoryObjectPlugin;
use ilDBInterface;
use ilToolbarGUI;
use ilub\plugin\SelfEvaluation\Dataset\Dataset;
use ilButton;
use ilObjSelfEvaluation;
use ilub\plugin\SelfEvaluation\Block\Matrix\QuestionBlock;
use ilTemplate;
use ilub\plugin\SelfEvaluation\UIHelper\Scale\Scale;
use ilub\plugin\SelfEvaluation\UIHelper\Chart\SpiderChart;
use ilub\plugin\SelfEvaluation\UIHelper\Chart\BarChart;
use ilub\plugin\SelfEvaluation\UIHelper\Chart\LeftRightChart;
use ilub\plugin\SelfEvaluation\Question\Matrix\Question;
use ilub\plugin\SelfEvaluation\Block\Block;
use ilub\plugin\SelfEvaluation\Dataset\Data;

class FeedbackChartGUI
{
    public function __construct(
        protected ilDBInterface $db,
        protected ilGlobalPageTemplate $tpl,
        protected ilRepositoryObjectPlugin $plugin,
        protected ilToolbarGUI $toolbar,
        protected ilObjSelfEvaluation $evaluation
    ) {
    }

    public function getPresentationOfFeedback(Dataset $dataset): string
    {
        $tpl = $this->initTemplate();

        $blocks = $dataset->getQuestionBlocks();

        foreach ($blocks as $block) {
            $this->parseBlockFeedback($tpl, $block, $dataset);
        }

        if (count($blocks) > 0 && $this->showOverview()) {
            $tpl->setCurrentBlock('overview');

            $tpl->setVariable('BLOCK_OVERVIEW_TITLE', $this->plugin->txt('block_overview_title'));

            $mean = $dataset->getOverallPercentage();
            /**
             * @var Block $min_block
             * @var Block $max_block
             */
            [$min_block, $min_percentage] = $dataset->getMinPercentageBlockAndMin();
            [$max_block, $max_percentage] = $dataset->getMaxPercentageBlockAndMax();
            $sd_per_block = $dataset->getPercentageStandardabweichungPerBlock();
            $scale_max = $dataset->getHighestValueFromScale();

            $statistics_median = $this->plugin->txt("overview_statistics_median") . " " . round(
                $scale_max * $mean / 100,
                2
            );//." (".$mean."%)";
            $statistics_max = $this->plugin->txt("overview_statistics_max") . " " . $max_block->getTitle(
            ) . ": " . round(
                $scale_max * $max_percentage / 100,
                2
            );// ." (".$max['percentage']."%)";
            $statistics_min = $this->plugin->txt("overview_statistics_min") . " " . $min_block->getTitle(
            ) . ": " . round(
                $scale_max * $min_percentage / 100,
                2
            );// ." (".$min['percentage']."%)";

            $statistics_sd_per_block = $this->plugin->txt("overview_statistics_standardabweichung_per_plock") . ": ";
            foreach ($sd_per_block as $key => $sd) {
                /**
                 * @var
                 */
                $statistics_sd_per_block .= $dataset->getBlockById($key)->getTitle() . ": " . $sd . "; ";
            }

            if ($this->evaluation->isShowFbsOverviewStatistics()) {
                $tpl->setVariable('OVERVIEW_STATISTICS_TITLE', $this->plugin->txt("overview_statistics_title"));

                $tpl->setVariable('OVERVIEW_STATISTICS_MEDIAN', $statistics_median);
                $tpl->setVariable('OVERVIEW_STATISTICS_MAX', $statistics_max);
                $tpl->setVariable('OVERVIEW_STATISTICS_MIN', $statistics_min);

                //$varianz = $data_set->getOverallVarianz();
                //$standardabweichung = $data_set->getOverallStandardabweichung();
                //$statistics_varianz = $this->plugin->txt("overview_statistics_varianz") . ": " . $varianz;
                //$statistics_standardabweichung = $this->plugin->txt("overview_statistics_standardabweichung") . ": " . $standardabweichung;
                //$tpl->setVariable('OVERVIEW_VARIANZ', $statistics_varianz);
                //$tpl->setVariable('OVERVIEW_STANDARDABWEICHUNG', $statistics_standardabweichung);
                //$tpl->setVariable('OVERVIEW_STANDARDABWEICHUNG_PER_BLOCK', $statistics_sd_per_block);
            }

            $percentage_per_block = $dataset->getPercentagePerBlock();

            if ($this->evaluation->isShowFbsOverviewBar()) {
                $tpl->setVariable('SHOW_BAR_CHART', $this->plugin->txt('show_bar_chart'));

                $chart = $this->getOverviewBarChart($blocks, $percentage_per_block, $dataset->getOverallPercentage());
                if ($this->evaluation->isShowFbsOverviewStatistics()) {
                    $chart->setShowVarianz(false);
                    $chart->setStandardabweichungData($sd_per_block);
                    $chart->setValuesForStandardabweichung($dataset->getPercentagePerBlock());
                }
                $tpl->setVariable('OVERVIEW_BAR_CHART', $chart->getHTML());
            }
            if ($this->evaluation->isShowFbsOverviewSpider()) {
                $tpl->setVariable(
                    'OVERVIEW_SPIDER_CHART',
                    $this->getOverviewSpiderChart($blocks, $percentage_per_block)->getHTML()
                );
                $tpl->setVariable('SHOW_SPIDER_CHART', $this->plugin->txt('show_spider_chart'));
            }
            if ($this->evaluation->isShowFbsOverviewLeftRight()) {
                $tpl->setVariable(
                    'OVERVIEW_LEFT_RIGHT_CHART',
                    $this->getOverviewLeftRightChart($blocks, $percentage_per_block)->getHTML()
                );
                $tpl->setVariable('SHOW_LEFT_RIGHT_CHART', $this->plugin->txt('show_left_right_chart'));
            }

            if ($this->evaluation->isShowFbsOverviewText()) {
                $feedback = Feedback::_getFeedbackForPercentage($this->db, $this->evaluation->getId(), $mean);

                if ($feedback !== null) {
                    $tpl->setVariable('FEEDBACK_OVERVIEW_TITLE', $feedback->getTitle());
                    $tpl->setVariable('FEEDBACK_OVERVIEW_BODY', $feedback->getFeedbackText());
                }
            }
            $tpl->parseCurrentBlock();
        }

        return $tpl->get();
    }

    protected function showAnyFeedbackCharts(): bool
    {
        $any_active = $this->evaluation->isShowFbsChartBar() || $this->evaluation->isShowFbsChartSpider(
        ) || $this->evaluation->isShowFbsChartLeftRight();
        return $this->evaluation->isShowFeedbacksCharts() && $any_active;
    }

    protected function showOverview(): bool
    {
        $any_overview_active = $this->evaluation->isShowFbsOverviewBar() || $this->evaluation->isShowFbsOverviewSpider(
        ) ||
            $this->evaluation->isShowFbsOverviewLeftRight() || $this->evaluation->isShowFbsOverviewStatistics();
        if ($this->evaluation->isShowFeedbacksOverview()) {
            return true;
        }
        return $any_overview_active;
    }

    protected function showAnyFeedback(): bool
    {
        if ($this->showAnyFeedbackCharts()) {
            return true;
        }
        if ($this->evaluation->isShowBlockTitlesDuringFeedback()) {
            return true;
        }
        if ($this->evaluation->isShowBlockDescriptionsDuringFeedback()) {
            return true;
        }
        return $this->evaluation->isShowFeedbacks();
    }

    protected function initTemplate(): ilTemplate
    {
        $btn = ilButton::getInstance();
        $btn->setCaption($this->plugin->txt("print_pdf"), false);
        $btn->addCSSClass("printPDF");
        $btn->setOnClick("printFeedback()");
        $this->toolbar->addButtonInstance($btn);

        $tpl = $this->plugin->getTemplate('default/Feedback/tpl.feedback.html');
        $this->tpl->addCss($this->plugin->getRelativeDirectory() . "/templates/css/feedback.css");
        $this->tpl->addJavaScript($this->plugin->getRelativeDirectory() . "/templates/js/bar_spider_chart_toggle.js");
        $this->tpl->addJavaScript($this->plugin->getRelativeDirectory() . "/templates/js/print.js");
        return $tpl;
    }

    protected function getBlockLabel(QuestionBlock $block): string
    {
        return $this->plugin->txt('block') . ' ' . ($block->getPosition() + 1);
    }

    protected function parseBlockFeedback(
        ilTemplate $tpl,
        QuestionBlock $block,
        Dataset $dataset
    ) {
        if ($this->showAnyFeedback()) {
            $percentage = $dataset->getPercentageForBlock($block->getId());
            $feedback = Feedback::_getFeedbackForPercentage($this->db, $block->getId(), $percentage ?? 0.0);
            if ($feedback === null) {
                return;
            }
            $tpl->setCurrentBlock('feedback');
            $tpl->setVariable('FEEDBACK_ID', 'xsev_fb_id_' . $feedback->getId());

            if ($this->evaluation->isShowBlockTitlesDuringFeedback()) {
                $abbreviation = $block->getAbbreviation();
                $tpl->setVariable('BLOCK_TITLE', $block->getTitle());
                if ($abbreviation !== '') {
                    $tpl->setCurrentBlock('block_abbreviation');
                    $tpl->setVariable('BLOCK_ABBREVIATION', $abbreviation);
                    $tpl->parseCurrentBlock();
                    $tpl->setCurrentBlock('feedback');
                }
            } else {
                $tpl->setVariable('BLOCK_TITLE', $this->getBlockLabel($block));
            }
            if ($this->evaluation->isShowBlockDescriptionsDuringFeedback()) {
                $tpl->setVariable('BLOCK_DESCRIPTION', $block->getDescription());
            }

            if ($this->showAnyFeedbackCharts()) {
                $scale = Scale::_getInstanceByObjId($this->db, $this->evaluation->getId());
                $units = $scale->getUnitsAsArray();
                $max_cnt = max(array_keys($units));

                if ($this->evaluation->isShowFbsChartBar()) {
                    $bar_chart = $this->getFeedbackBarChart($dataset, $block->getId(), $units);
                    $tpl->setVariable('BAR_CHART', $bar_chart->getHTML());
                    $tpl->setVariable('SHOW_BAR_CHART', $this->plugin->txt('show_bar_chart'));
                }

                if ($this->evaluation->isShowFbsChartLeftRight()) {
                    $left_right_chart = $this->getFeedbackLeftRightChart($dataset, $block->getId(), $units);
                    $tpl->setVariable('LEFT_RIGHT_CHART', $left_right_chart->getHTML());
                    $tpl->setVariable('SHOW_LEFT_RIGHT_CHART', $this->plugin->txt('show_left_right_chart'));
                }

                if ($this->evaluation->isShowFbsChartSpider()) {
                    $spider_chart = $this->getFeedbackSpiderChart($dataset, $block->getId(), $max_cnt);
                    $tpl->setVariable('SPIDER_CHART', $spider_chart->getHTML());
                    $tpl->setVariable('SHOW_SPIDER_CHART', $this->plugin->txt('show_spider_chart'));
                }
            } else {
                $tpl->setVariable('VISIBILITY_GRAPH', "visibility: hidden");
            }

            if ($this->evaluation->isShowFeedbacks()) {
                $tpl->setVariable('FEEDBACK_TITLE', $feedback->getTitle());
                $tpl->setVariable('FEEDBACK_BODY', $feedback->getFeedbackText());
            }
            $tpl->parseCurrentBlock();
        }
    }

    protected function getFeedbackBarChart(Dataset $dataset, int $block_id, array $scale_units): BarChart
    {
        $chart = new BarChart($block_id . "_feedback_bar_chart");
        $ticks = [];
        $x = 1;

        foreach (Question::_getAllInstancesForParentId($this->db, $block_id) as $qst) {
            $value = Data::_getInstanceForQuestionId($this->db, $dataset->getId(), $qst->getId())->getValue();
            $data = $chart->getDataInstance();
            $data->addPoint($x, (float) $value);
            $ticks[$x] = $qst->getTitle() ?: $this->plugin->txt('question') . ' ' . $x;
            $x++;
            $chart->addData($data);
        }
        $scale_units = $this->setUnusedLegendLabels($scale_units);
        $chart->setTicks($ticks, $scale_units, true);

        return $chart;
    }

    protected function getFeedbackLeftRightChart(
        Dataset $dataset,
        int $block_id,
        array $scale_units
    ): LeftRightChart {
        $chart = new LeftRightChart($block_id . "_feedback_left_right_chart");
        $data = $chart->getDataInstance();
        $ticks = [];
        $x = 1;
        foreach (Question::_getAllInstancesForParentId($this->db, $block_id) as $qst) {
            $value = Data::_getInstanceForQuestionId($this->db, $dataset->getId(), $qst->getId())->getValue();
            $data->addPoint((float) $value, $x);
            $ticks[$x] = $qst->getTitle() ?: $this->plugin->txt('question') . ' ' . $x;
            $x++;
        }

        $scale_units = $this->setUnusedLegendLabels($scale_units);
        $chart->setTicks($scale_units, $ticks, true);
        $chart->addData($data);

        return $chart;
    }

    protected function getFeedbackSpiderChart(
        Dataset $dataset,
        int $block_id,
        int $max_level
    ): SpiderChart {
        $chart = new SpiderChart($block_id . "_feedback_spider_chart");
        $data = $chart->getDataInstance();
        $leg_labels = [];
        $cnt = 0;
        foreach (Question::_getAllInstancesForParentId($this->db, $block_id) as $qst) {
            $value = Data::_getInstanceForQuestionId($this->db, $dataset->getId(), $qst->getId())->getValue();
            $data->addPoint($cnt, (float) $value);
            $leg_labels[] = $qst->getTitle() ?: $this->plugin->txt('question') . ' ' . ($cnt + 1);
            $cnt++;
        }
        $chart->setLegLabels($leg_labels); // This might be the questions
        $chart->setYAxisMax($max_level); // set the max number of net lines
        $chart->addData($data);

        return $chart;
    }

    protected function setUnusedLegendLabels(array $scale_unit): array
    {
        $key = array_search('', $scale_unit);
        if ($key === false) {
            return $scale_unit;
        }

        $scale_unit[$key] = $key;
        return $scale_unit;
    }

    /**
     * @param QuestionBlock[] $blocks
     * @param float[]         $block_percentages
     */
    protected function getOverviewBarChart(array $blocks, array $block_percentages, float $average_percantage): BarChart
    {
        $chart = new BarChart('bar_overview');
        $x_axis = [];

        foreach ($blocks as $block) {
            $data = $chart->getDataInstance();
            $data->addPoint($block->getPosition(), $block_percentages[$block->getId()]);
            $x_axis[$block->getPosition()] = $block->getLabel();
            $chart->addData($data);
        }

        if ($this->evaluation->isOverviewBarShowLabelAsPercentage()) {
            // display y-axis in 10% steps
            $y_axis = [];
            for ($i = 0; $i <= 10; $i++) {
                $y_axis[$i * 10] = $i * 10 . '%';
            }
            $chart->setTicks($x_axis, $y_axis, true);
        } else {
            $scale = Scale::_getInstanceByObjId($this->db, $this->evaluation->getId());
            $units = $scale->getUnitsAsRelativeArray();
            $units = $this->setUnusedLegendLabels($units);
            $chart->setTicks($x_axis, $units, true);
        }

        $chart->setShowAverageLine(true);
        $chart->setAverage($average_percantage);

        return $chart;
    }

    /**
     * @param QuestionBlock[] $blocks
     * @param int[]           $block_percentages
     */
    protected function getOverviewLeftRightChart(array $blocks, array $block_percentages): LeftRightChart
    {
        $chart = new LeftRightChart('left_right_overview');
        $data = $chart->getDataInstance();

        $y_axis = [];
        $y = 999999;

        foreach ($blocks as $block) {
            $data->addPoint($block_percentages[$block->getId()], $y - $block->getPosition());
            $y_axis[$y - $block->getPosition()] = $block->getLabel();
            $chart->addData($data);
        }
        // display y-axis in 10% steps
        $x_axis = [];
        for ($i = 0; $i <= 10; $i++) {
            $x_axis[$i * 10] = $i * 10 . '%';
        }
        $chart->setTicks($x_axis, $y_axis, true);

        return $chart;
    }

    /**
     * @param QuestionBlock[] $blocks
     * @param int[]           $block_percentages
     */
    protected function getOverviewSpiderChart(array $blocks, array $block_percentages): SpiderChart
    {
        $chart = new SpiderChart('spider_chart_overview');
        $data = $chart->getDataInstance();

        $cnt = 0;
        $leg_labels = [];
        foreach ($blocks as $block) {
            $leg_labels[] = $block->getLabel();
            $data->addPoint($cnt, $block_percentages[$block->getId()] / 10);
            $cnt++;
        }

        $chart->setLegLabels($leg_labels);
        $chart->setYAxisMax(10); // set the max number of net lines (10% steps)
        $chart->addData($data);

        return $chart;
    }
}
