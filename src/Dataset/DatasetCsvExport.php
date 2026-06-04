<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Dataset;

use ilub\plugin\SelfEvaluation\Block\Matrix\QuestionBlock;
use ilub\plugin\SelfEvaluation\CsvExport\csvExport;
use ilub\plugin\SelfEvaluation\CsvExport\csvExportRow;
use ilub\plugin\SelfEvaluation\CsvExport\csvExportValue;
use ilSelfEvaluationPlugin;
use ilub\plugin\SelfEvaluation\Question\Meta\MetaQuestion;
use ilub\plugin\SelfEvaluation\Question\Matrix\Question;
use ilub\plugin\SelfEvaluation\CsvExport\csvExportColumn;
use ilDBInterface;
use ilub\plugin\SelfEvaluation\Block\Meta\MetaBlock;
use ilub\plugin\SelfEvaluation\Question\Meta\Type\MetaTypeMatrix;
use Exception;
use ilub\plugin\SelfEvaluation\Question\Meta\Type\MetaTypeSelect;
use ilub\plugin\SelfEvaluation\Question\Meta\Type\MetaTypeSingleChoice;
use ilub\plugin\SelfEvaluation\Identity\Identity;
use ilObjUser;

class DatasetCsvExport extends csvExport
{
    protected int $object_id = 0;

    /**
     * @var MetaQuestion[]
     */
    protected array $meta_questions = [];
    /**
     * @var Question[]
     */
    protected array $questions = [];
    /**
     * @var Dataset[]
     */
    protected array $datasets = [];
    protected string $date_format = "Y-m-d H:i:s";

    public function __construct(protected ilDBInterface $db, protected ilSelfEvaluationPlugin $pl, $object_id = 0)
    {
        parent::__construct();
        $this->setObjectId($object_id);
    }

    #[\Override]
    public function getCsvExport(string $delimiter = ";", string $enclosure = '"'): void
    {
        $this->getData();
        $this->setColumns();
        $this->setRows();
        parent::getCsvExport($delimiter);
    }

    protected function getData()
    {
        $meta_blocks = MetaBlock::_getAllInstancesByParentId($this->db, $this->getObjectId());
        foreach ($meta_blocks as $meta_block) {
            $this->addMetaQuestions(MetaQuestion::_getAllInstancesForParentId($this->db, $meta_block->getId()));
        }

        $blocks = QuestionBlock::_getAllInstancesByParentId($this->db, $this->getObjectId());
        foreach ($blocks as $block) {
            $this->addQuestions(Question::_getAllInstancesForParentId($this->db, $block->getId()));
        }

        $this->setDatasets(Dataset::_getAllInstancesByObjectId($this->db, $this->getObjectId()));
    }

    protected function setColumns()
    {
        $position = 0;
        $this->getTable()->addColumn(new csvExportColumn("identity", $this->pl->txt("identity"), -100));
        $this->getTable()->addColumn(new csvExportColumn("complete", $this->pl->txt("complete"), -95));
        $this->getTable()->addColumn(new csvExportColumn("starting_date", $this->pl->txt("starting_date"), -90));
        $this->getTable()->addColumn(new csvExportColumn("ending_date", $this->pl->txt("ending_date"), -80));
        $this->getTable()->addColumn(new csvExportColumn("duration", $this->pl->txt("duration"), -70));
        $this->getTable()->addColumn(new csvExportColumn("mean", $this->pl->txt("overview_statistics_median"), -60));
        $this->getTable()->addColumn(new csvExportColumn("max", $this->pl->txt("overview_statistics_max"), -50));
        $this->getTable()->addColumn(new csvExportColumn("min", $this->pl->txt("overview_statistics_min"), -40));
        $this->getTable()->addColumn(
            new csvExportColumn(
                "percentage_variance",
                $this->pl->txt("overview_statistics_varianz"),
                -30
            )
        );
        $this->getTable()->addColumn(
            new csvExportColumn(
                "percentage_sd",
                $this->pl->txt("overview_statistics_standardabweichung"),
                -20
            )
        );
        $this->getTable()->addColumn(
            new csvExportColumn(
                "percentage_sd_per_block",
                $this->pl->txt("overview_statistics_standardabweichung_per_plock"),
                -10
            )
        );

        $this->getTable()->setSortColumn("starting_date");

        foreach ($this->getMetaQuestions() as $meta_question) {
            $position = $this->setMetaQuestionColumn($meta_question, $position);
        }

        foreach ($this->getQuestions() as $question) {
            $this->getTable()->addColumn(
                new csvExportColumn(
                    $this->getTitleForQuestion($question),
                    $this->getTitleForQuestion($question),
                    $position
                )
            );
            $position++;
        }
    }

    protected function setMetaQuestionColumn(MetaQuestion $meta_question, int $position): int
    {
        if ($meta_question->getTypeId() === MetaTypeMatrix::TYPE_ID) {
            $questions = MetaTypeMatrix::getQuestionsFromArray(
                $meta_question->getValues()
            );

            foreach ($questions as $question) {
                $this->getTable()->addColumn(
                    new csvExportColumn(
                        $question,
                        $question,
                        $position
                    )
                );
                $this->getTable()->addColumn(
                    new csvExportColumn(
                        $question . " ID",
                        $question . " ID",
                        $position
                    )
                );
                $position++;
            }
        } else {
            $column_name = $meta_question->getShortTitle() ?: $meta_question->getName();
            $this->getTable()->addColumn(
                new csvExportColumn(
                    $column_name,
                    $column_name,
                    $position
                )
            );

            if ($meta_question->getTypeId() === MetaTypeSelect::TYPE_ID ||
                $meta_question->getTypeId() === MetaTypeSingleChoice::TYPE_ID) {
                $this->getTable()->addColumn(
                    new csvExportColumn(
                        $column_name . " ID",
                        $column_name . " ID",
                        $position
                    )
                );
            }
            $position++;
        }
        return $position;
    }

    protected function setRows()
    {
        foreach ($this->getDatasets() as $dataset) {
            $row = new csvExportRow();
            $row->addValue($this->getIdentity($dataset));
            $row->addValue(new csvExportValue("complete", $dataset->isComplete() ? "1" : "0"));
            $values = $this->getDateValues($dataset);
            foreach ($values as $value) {
                $row->addValue($value);
            }
            $values = $this->getStatisticValues($dataset);
            foreach ($values as $value) {
                $row->addValue($value);
            }
            $entries = Data::_getAllInstancesByDatasetId($this->db, $dataset->getId());
            foreach ($entries as $entry) {
                if ($this->getMetaQuestion($entry->getQuestionId()) !== null) {
                    $meta_question = $this->getMetaQuestion($entry->getQuestionId());
                    $values = $this->getMetaQuestionValues(
                        $row,
                        $entry,
                        $meta_question
                    );
                    foreach ($values as $value) {
                        $row->addValue($value);
                    }
                } elseif ($this->getQuestion($entry->getQuestionId()) !== null) {
                    $row->addValue($this->getQuestionValues($row, $entry));
                }
            }
            $this->getTable()->addRow($row);
        }
    }

    protected function getIdentity(Dataset $dataset): csvExportValue
    {
        $identifier = new Identity($this->db, $dataset->getIdentifierId());
        $id = $identifier->getIdentifier();
        if ($identifier->getType() === Identity::TYPE_LOGIN) {
            $username = ilObjUser::_lookupName((int) $identifier->getIdentifier());
            $id = $username['login'];
        }

        return new csvExportValue("identity", (string) $id);
    }

    protected function getDateValues(Dataset $dataset): array
    {
        $meta_csv_values = [];

        try {
            $invalid = false;
            if ($dataset->getCreationDate() !== 0) {
                $meta_csv_values[] = new csvExportValue(
                    "starting_date",
                    date($this->getDateFormat(), $dataset->getCreationDate())
                );
            } else {
                $invalid = true;
                $meta_csv_values[] = new csvExportValue("starting_date", "Invalid");
            }
            if ($dataset->isComplete() && $dataset->getSubmitDate()) {
                $meta_csv_values[] = new csvExportValue(
                    "ending_date",
                    date($this->getDateFormat(), $dataset->getSubmitDate())
                );
            } else {
                $invalid = true;
                $meta_csv_values[] = new csvExportValue("ending_date", "Invalid");
            }
            if ($invalid || !$dataset->isComplete()) {
                $meta_csv_values[] = new csvExportValue("duration", "Invalid");
            } else {
                $meta_csv_values[] = new csvExportValue("duration", (string) $dataset->getDuration());
            }
        } catch (Exception) {
            $meta_csv_values[] = new csvExportValue("Error", "Invalid Date");
        }
        return $meta_csv_values;
    }

    protected function getStatisticValues(Dataset $dataset): array
    {
        $meta_csv_values = [];
        /**
         * @var $max_block Block
         * @var $min_block Block
         */
        [$min_block, $min_percentage] = $dataset->getMinPercentageBlockAndMin();
        [$max_block, $max_percentage] = $dataset->getMaxPercentageBlockAndMax();
        $sd_per_block = $dataset->getPercentageStandardabweichungPerBlock();

        $statistics_sd_per_block = "";
        foreach ($sd_per_block as $key => $sd) {
            $statistics_sd_per_block .= $dataset->getBlockById($key)->getTitle() . ": " . $sd . "; ";
        }

        $meta_csv_values[] = new csvExportValue("mean", $dataset->getOverallPercentage() . "%");
        $meta_csv_values[] = new csvExportValue("max", $max_block->getTitle() . ": " . $max_percentage . "%");
        $meta_csv_values[] = new csvExportValue("min", $min_block->getTitle() . ": " . $min_percentage . "%");
        $meta_csv_values[] = new csvExportValue(
            "percentage_variance",
            (string) $dataset->getOverallPercentageVarianz()
        );
        $meta_csv_values[] = new csvExportValue(
            "percentage_sd",
            (string) $dataset->getOverallPercentageStandardabweichung()
        );
        $meta_csv_values[] = new csvExportValue("percentage_sd_per_block", $statistics_sd_per_block);

        return $meta_csv_values;
    }

    protected function getQuestionValues(csvExportRow $row, Data $entry): csvExportValue
    {
        $column_name = $this->getTitleForQuestion($this->getQuestion($entry->getQuestionId()));

        $column_name = $this->generateUniqueName($row, $column_name);

        $value = $this->handledSkipped($entry->getValue());

        return new csvExportValue($column_name, $value);
    }

    protected function generateUniqueName(csvExportRow $row, string $column_name): string
    {
        while ($row->getColumns()->columnIdExists($column_name)) {
            $column_name .= "_duplicate";
        }
        return $column_name;
    }

    protected function handledSkipped($value)
    {
        if ($value == "ilsel_dummy") {
            return "übersprungen";
        }
        return $value;
    }

    protected function getMetaQuestionValues(csvExportRow $row, Data $entry, MetaQuestion $meta_question): array
    {
        $meta_csv_values = [];

        if ($meta_question->getTypeId() === MetaTypeMatrix::TYPE_ID) {
            $question_values = $meta_question->getValues();
            $questions = MetaTypeMatrix::getQuestionsFromArray($question_values);
            foreach ($questions as $key => $question) {
                $entry_keys = $entry->getValue();
                if (is_array($entry_keys) && array_key_exists($key, $entry_keys)) {
                    $entry_key = $entry_keys[$key];
                    $entry_content = $question_values[$entry_key];

                    $meta_csv_values[] = new csvExportValue($question, $entry_content);
                    $meta_csv_values[] = new csvExportValue(
                        $question . " ID",
                        str_replace("scale_", "", $entry_key)
                    );
                }
            }
        } else {
            $column_name = $meta_question->getShortTitle() ?: $meta_question->getName();
            $column_name = $this->generateUniqueName($row, $column_name);
            $key = $this->handledSkipped($entry->getValue());

            if ($meta_question->getTypeId() === MetaTypeSelect::TYPE_ID ||
                $meta_question->getTypeId() === MetaTypeSingleChoice::TYPE_ID) {
                $question_values = $meta_question->getValues();
                if (array_key_exists($key, $question_values)) {
                    $meta_csv_values[] = new csvExportValue($column_name . " ID", $key);
                    $entry_value = $question_values[$key];
                    $meta_csv_values[] = new csvExportValue($column_name, $entry_value);
                } else {
                    //legacy issue, get ID from time with none JSON-Save of values.
                    $value = "";
                    foreach ($question_values as $qkey => $qvalue) {
                        if ($qvalue == $key) {
                            $key = $qkey;
                            $value = $qvalue;
                            break;
                        }
                    }
                    $meta_csv_values[] = new csvExportValue($column_name, $value);
                    $meta_csv_values[] = new csvExportValue($column_name . " ID", $key);
                }
            } else {
                $meta_csv_values[] = new csvExportValue($column_name, $key);
            }
        }
        return $meta_csv_values;
    }

    protected function getTitleForQuestion(Question $question): string
    {
        $block = new QuestionBlock($this->db, $question->getParentId());
        return $question->getTitle() ?: $this->pl->txt('question') . ' ' . $block->getPosition(
        ) . '.' . $question->getPosition();
    }

    public function setObjectId(int $object_id): void
    {
        $this->object_id = $object_id;
    }

    public function getObjectId(): int
    {
        return $this->object_id;
    }

    /**
     * @param Dataset[] $datasets
     */
    public function setDatasets(array $datasets): void
    {
        $this->datasets = $datasets;
    }

    /**
     * @return Dataset[]
     */
    public function getDatasets(): array
    {
        return $this->datasets;
    }

    /**
     * @param MetaQuestion[] $meta_questions
     */
    public function setMetaQuestions(array $meta_questions): void
    {
        $this->meta_questions = $meta_questions;
    }

    /**
     * @param MetaQuestion[] $meta_questions
     */
    public function addMetaQuestions(array $meta_questions): void
    {
        $this->meta_questions += $meta_questions;
    }

    /**
     * @return MetaQuestion[]
     */
    public function getMetaQuestions(): array
    {
        return $this->meta_questions;
    }

    public function getMetaQuestion(int $id): ?MetaQuestion
    {
        if (array_key_exists($id, $this->meta_questions)) {
            return $this->meta_questions[$id];
        }
        return null;
    }

    /**
     * @param Question[] $questions
     */
    public function setQuestions(array $questions): void
    {
        $this->questions = $questions;
    }

    /**
     * @param Question[] $questions
     */
    public function addQuestions(array $questions): void
    {
        $this->questions += $questions;
    }

    /**
     * @return Question[]
     */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    public function getQuestion(int $id): ?Question
    {
        if (array_key_exists($id, $this->questions)) {
            return $this->questions[$id];
        }
        return null;
    }

    public function setDateFormat(string $date_format): void
    {
        $this->date_format = $date_format;
    }

    public function getDateFormat(): string
    {
        return $this->date_format;
    }
}
