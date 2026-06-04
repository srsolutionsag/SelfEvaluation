<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ilub\plugin\SelfEvaluation\Block\Block;
use ilub\plugin\SelfEvaluation\Block\Matrix\QuestionBlock;

final class QuestionBlockTest extends TestCase
{
    private QuestionBlock $block;

    public function setUp(): void
    {
        $db = Mockery::mock("\ilDBInterface");
        $this->block = new QuestionBlock($db);
    }

    public function testConstruct(): void
    {
        $this->assertInstanceOf(Block::class, $this->block);
        $this->assertInstanceOf(QuestionBlock::class, $this->block);
    }

    public function testIdAfterConstruct(): void
    {
        $this->assertSame(0, $this->block->getId());
    }

    public function testSetId(): void
    {
        $this->block->setId(1);
        $this->assertSame(1, $this->block->getId());
    }

    public function testGetArrayForDBOnEmpty(): void
    {
        $this->assertSame([
            'id' => ['integer', 0],
            'abbreviation' => ['text', ""],
            'title' => ['text', ""],
            'description' => ['text', ""],
            'position' => ['integer', 99],
            'parent_id' => ['integer', 0]
        ], $this->block->getArrayForDb());
    }
}
