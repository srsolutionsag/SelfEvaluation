<?php

declare(strict_types=1);

use Mockery\MockInterface;

include_once "DatasetHelperTrait.php";

use PHPUnit\Framework\TestCase;
use ilub\plugin\SelfEvaluation\Dataset\Dataset;
use ilub\plugin\SelfEvaluation\Dataset\Data;
use ilub\plugin\SelfEvaluation\Block\Matrix\QuestionBlock;
use ilub\plugin\SelfEvaluation\Question\Matrix\Question;

final class DatasetsTest extends TestCase
{
    use DatasetHelperTrait;

    private Dataset $dataset;
    protected MockInterface|ilDBInterface $db;

    protected function setUp(): void
    {
        $this->db = Mockery::mock("\ilDBInterface");
        $this->db->shouldReceive("query");
        $this->db->shouldReceive("fetchObject");

        $this->dataset = new Dataset($this->db);
    }

    public function testConstruct(): void
    {
        $this->assertSame(Dataset::class, $this->dataset::class);
    }

    public function testIdAfterConstruct(): void
    {
        $this->assertSame(0, $this->dataset->getId());
    }

    public function testSetId(): void
    {
        $this->dataset->setId(1);
        $this->assertSame(1, $this->dataset->getId());
    }

    public function testGetArrayForDBOnEmpty(): void
    {
        $this->assertSame([
            'id' => ['integer', 0],
            'identifier_id' => ['integer', 0],
            'creation_date' => ['integer', 0],
            'complete' => ['integer', 0]
        ], $this->dataset->getArrayForDb());
    }

    public function testUpdateValuesByEmptyPost(): void
    {
        $data = [];
        $this->db->shouldReceive("nextId")->with($this->dataset::TABLE_NAME)->andReturn(1);
        $this->dataset->setId(1);
        $this->db->shouldReceive("insert")->with([$this->dataset::TABLE_NAME, $this->dataset->getArrayForDb()]);
        $this->dataset->updateValuesByPost($data);
        $this->assertTrue(true);
    }

    public function testUpdateValuesByPostQuestion(): void
    {
        $data = ["qst_1" => "value1"];
        $data_fixture = (fn($argument): bool => $argument['id'] == [0 => 'integer', 1 => 2] &&
            $argument['dataset_id'] == [0 => 'integer', 1 => 1] &&
            $argument['question_id'] == [0 => 'integer', 1 => 1] &&
            $argument['question_type'] == [0 => 'text', 1 => 'qst'] &&
            $argument['creation_date'][0] == 'integer' &&
            $argument['value'] == [0 => 'text', 1 => 'value1']);

        $this->db->shouldReceive("nextId")->with($this->dataset::TABLE_NAME)->andReturn(1);
        $this->dataset->setId(1);
        $this->db->shouldReceive("insert")->with([$this->dataset::TABLE_NAME, $this->dataset->getArrayForDb()]);
        $this->db->shouldReceive("nextId")->with(Data::TABLE_NAME)->andReturn(2);
        $this->db->shouldReceive("prepare");
        $this->db->shouldReceive("execute");
        $this->db->shouldReceive("fetchObject");

        $this->checkMockeryForUpdateValuesByPost($data, [$data_fixture]);

        $this->dataset->updateValuesByPost($data);

        $this->assertTrue(true);
    }

    public function testUpdateValuesByPostMetaQuestion(): void
    {
        $data = ["mqst_1" => "value1"];
        $data_fixture = (fn($argument): bool => $argument['id'] == [0 => 'integer', 1 => 2] &&
            $argument['dataset_id'] == [0 => 'integer', 1 => 1] &&
            $argument['question_id'] == [0 => 'integer', 1 => 1] &&
            $argument['question_type'] == [0 => 'text', 1 => 'mqst'] &&
            $argument['creation_date'][0] == 'integer' &&
            $argument['value'] == [0 => 'text', 1 => 'value1']);

        $this->checkMockeryForUpdateValuesByPost($data, [$data_fixture]);

        $this->assertTrue(true);
    }

    public function testUpdateValuesByPostMetaQuestionCombination(): void
    {
        $data = ["qst_1" => "value1", "qst_2" => "value2", "mqst_1" => "value1"];
        $data_fixture1 = (fn($argument): bool => $argument['id'] == [0 => 'integer', 1 => 2] &&
            $argument['dataset_id'] == [0 => 'integer', 1 => 1] &&
            $argument['question_id'] == [0 => 'integer', 1 => 1] &&
            $argument['question_type'] == [0 => 'text', 1 => 'qst'] &&
            $argument['creation_date'][0] == 'integer' &&
            $argument['value'] == [0 => 'text', 1 => 'value1']);
        $data_fixture2 = (fn($argument): bool => $argument['id'] == [0 => 'integer', 1 => 2] &&
            $argument['dataset_id'] == [0 => 'integer', 1 => 1] &&
            $argument['question_id'] == [0 => 'integer', 1 => 1] &&
            $argument['question_type'] == [0 => 'text', 1 => 'mqst'] &&
            $argument['creation_date'][0] == 'integer' &&
            $argument['value'] == [0 => 'text', 1 => 'value1']);
        $data_fixture3 = (fn($argument): bool => $argument['id'] == [0 => 'integer', 1 => 2] &&
            $argument['dataset_id'] == [0 => 'integer', 1 => 1] &&
            $argument['question_id'] == [0 => 'integer', 1 => 2] &&
            $argument['question_type'] == [0 => 'text', 1 => 'qst'] &&
            $argument['creation_date'][0] == 'integer' &&
            $argument['value'] == [0 => 'text', 1 => 'value2']);

        $this->checkMockeryForUpdateValuesByPost($data, [$data_fixture1, $data_fixture2, $data_fixture3]);

        $this->assertTrue(true);
    }

    protected function checkMockeryForUpdateValuesByPost(array $data, $fixtures)
    {
        $this->db->shouldReceive("nextId")->with($this->dataset::TABLE_NAME)->andReturn(1);
        $this->dataset->setId(1);
        $this->db->shouldReceive("insert")->with([$this->dataset::TABLE_NAME, $this->dataset->getArrayForDb()]);
        $this->db->shouldReceive("nextId")->with(Data::TABLE_NAME)->andReturn(2);
        $this->db->shouldReceive("prepare");
        $this->db->shouldReceive("execute");
        $this->db->shouldReceive("fetchObject");

        foreach ($fixtures as $fixture) {
            $this->db->shouldReceive("insert")->with(Data::TABLE_NAME, Mockery::on($fixture));
        }

        $this->dataset->updateValuesByPost($data);
    }

    public function testSetHighestScale(): void
    {
        $this->dataset->setHighestScale(75);
        $this->assertSame(75, $this->dataset->getHighestValueFromScale());
    }

    public function testSetQuestionBlocksEmpty(): void
    {
        $this->dataset->setQuestionBlocks([]);
        $this->assertSame([], $this->dataset->getQuestionBlocks());
    }

    public function testSetQuestionBlocksNotEmpty(): void
    {
        $block1 = new QuestionBlock($this->db);
        $block1->setId(1);
        $this->dataset->setQuestionBlocks([$block1]);
        $this->assertEquals([$block1], $this->dataset->getQuestionBlocks());
    }

    public function testSetQuestionsDataForBlocksEmpty(): void
    {
        $block1 = new QuestionBlock($this->db);
        $block1->setId(1);
        $this->dataset->setQuestionsDataForBlocks([$block1->getId() => []]);
        $this->assertSame([], $this->dataset->getQuestionsDataPerBlock($block1->getId()));
    }

    public function testSetQuestionsDataForBlocksNotEmpty(): void
    {
        $block1 = new QuestionBlock($this->db);
        $block1->setId(1);
        $question1 = new Question($this->db);
        $question1->setId(1);
        $answer = new Data($this->db);
        $answer->setValue("0");
        $this->dataset->setQuestionsDataForBlocks([$block1->getId() => [$question1->getId() => $answer]]);
        $this->assertEquals([$question1->getId() => $answer], $this->dataset->getQuestionsDataPerBlock($block1->getId()));
    }

    public function testGetPercentageForBlockOnEmptyDataSet(): void
    {
        $this->dataset->setHighestScale(75);
        $this->dataset->setQuestionBlocks([]);
        $this->assertNull($this->dataset->getPercentageForBlock(1));
    }

    public function testGetPercentageForBlockOnSingularSetNoAnswer(): void
    {
        $this->dataset->setHighestScale(75);
        $block1 = $this->getBasicBlock();
        $this->dataset->setQuestionBlocks([$block1]);
        $this->dataset->setQuestionsDataForBlocks([$block1->getId() => []]);
        try {
            $this->dataset->getPercentageForBlock(1);
            self::fail();
        } catch (Exception) {
            $this->assertTrue(true);
        }
    }

    public function testGetPercentageForBlockOnSingularSetZeroAnswer(): void
    {
        $this->dataset->setHighestScale(5);
        [$block1, $question1, $answer1] = $this->getBasics();
        $answer1->setValue("0");
        $this->dataset->setQuestionBlocks([$block1]);
        $this->dataset->setQuestionsDataForBlocks([$block1->getId() => [$question1->getId() => $answer1]]);

        $this->assertEquals(0 / 5 * 100, $this->dataset->getPercentageForBlock(1));
    }

    public function testGetPercentageForBlockOnSingularSetMaxAnswer(): void
    {
        $this->dataset->setHighestScale(5);
        [$block1, $question1, $answer1] = $this->getBasics();
        $answer1->setValue("5");
        $this->dataset->setQuestionBlocks([$block1]);
        $this->dataset->setQuestionsDataForBlocks([$block1->getId() => [$question1->getId() => $answer1]]);

        $this->assertEquals(100, $this->dataset->getPercentageForBlock(1));
    }

    public function testGetPercentageForBlockOnSingularSetMediumAnswer(): void
    {
        $this->dataset->setHighestScale(5);
        [$block1, $question1, $answer1] = $this->getBasics();
        $answer1->setValue("2");
        $this->dataset->setQuestionBlocks([$block1]);
        $this->dataset->setQuestionsDataForBlocks([$block1->getId() => [$question1->getId() => $answer1]]);

        $this->assertSame(2 / 5 * 100, $this->dataset->getPercentageForBlock(1));
    }

    public function testGetPercentageForBlockOnSingularSetReversedScale(): void
    {
        $this->dataset->setHighestScale(5);
        [$block1, $question1, $answer1] = $this->getBasics();
        $this->dataset->setQuestionBlocks([$block1]);
        $answer1->setValue("2");
        $this->dataset->setQuestionsDataForBlocks([$block1->getId() => [$question1->getId() => $answer1]]);

        $this->assertSame(2 / 5 * 100, $this->dataset->getPercentageForBlock(1));
    }

    public function testGetPercentageForBlockWithMultipleQuestionAnswers(): void
    {
        $this->dataset = $this->setUpDatasetWithThreeBlocks($this->dataset);
        $percentages = $this->getBlockThreeBlocksPercentages();
        $this->assertEquals($percentages[$this->getBlock1()->getId()], $this->dataset->getPercentageForBlock($this->getBlock1()->getId()));
    }

    public function testGetPercentagePerBlockWithMultipleQuestionAnswers(): void
    {
        $this->dataset = $this->setUpDatasetWithThreeBlocks($this->dataset);
        $this->assertEquals($this->getBlockThreeBlocksPercentages(), $this->dataset->getPercentagePerBlock());
    }

    public function testGetMinPercentageBlock(): void
    {
        $this->dataset = $this->setUpDatasetWithThreeBlocks($this->dataset);
        $this->assertEquals([$this->getBlock2(), $this->getBlock2Percentage()], $this->dataset->getMinPercentageBlockAndMin());
    }

    public function testGetMaxPercentageBlock(): void
    {
        $this->dataset = $this->setUpDatasetWithThreeBlocks($this->dataset);
        $this->assertEquals([$this->getBlock1(), $this->getBlock1Percentage()], $this->dataset->getMaxPercentageBlockAndMax());
    }
}
