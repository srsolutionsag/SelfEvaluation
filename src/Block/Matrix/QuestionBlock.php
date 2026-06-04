<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Block\Matrix;

use ilub\plugin\SelfEvaluation\Block\Block;
use ilub\plugin\SelfEvaluation\Question\Matrix\Question;
use ilub\plugin\SelfEvaluation\Feedback\Feedback;
use SimpleXMLElement;
use ilDBInterface;
use ilCtrl;
use ilSelfEvaluationPlugin;
use ilub\plugin\SelfEvaluation\Block\BlockTableRow;

class QuestionBlock extends Block implements QuestionBlockInterface
{
    protected string $abbreviation = '';

    public function cloneTo(int $parent_id): self
    {
        $clone = new self($this->db);
        $clone->setParentId($parent_id);
        $clone->setTitle($this->getTitle());
        $clone->setAbbreviation($this->getAbbreviation());
        $clone->setDescription($this->getDescription());
        $clone->setPosition($this->getPosition());
        $clone->update();

        $old_questions = Question::_getAllInstancesForParentId($this->db, $this->getId());
        foreach ($old_questions as $question) {
            $question->cloneTo($clone->getId());
        }

        $old_feedbacks = Feedback::_getAllInstancesForParentId($this->db, $this->getId());
        foreach ($old_feedbacks as $feedback) {
            $feedback->cloneTo($clone->getId());
        }

        return $clone;
    }

    public function toXml(SimpleXMLElement $xml): SimpleXMLElement
    {
        $child_xml = $xml->addChild("questionBlock");
        $child_xml->addAttribute("parentId", (string) $this->getParentId());
        $child_xml->addAttribute("title", $this->getTitle());
        $child_xml->addAttribute("abbreviation", $this->getAbbreviation());
        $child_xml->addAttribute("description", $this->getDescription());
        $child_xml->addAttribute("position", (string) $this->getPosition());

        $questions = Question::_getAllInstancesForParentId($this->db, $this->getId());

        foreach ($questions as $question) {
            $child_xml = $question->toXml($child_xml);
        }

        $feedbacks = Feedback::_getAllInstancesForParentId($this->db, $this->getId());

        foreach ($feedbacks as $feedback) {
            $child_xml = $feedback->toXml($child_xml);
        }

        return $xml;
    }

    public static function fromXml(ilDBInterface $db, int $parent_id, SimpleXMLElement $xml): SimpleXMLElement
    {
        $attributes = $xml->attributes();
        $block = new self($db);
        $block->setParentId($parent_id);
        $block->setTitle($attributes["title"]->__toString());
        $block->setAbbreviation($attributes["abbreviation"]->__toString());
        $block->setDescription($attributes["description"]->__toString());
        $block->setPosition((int) $attributes["position"]);
        $block->create();

        foreach ($xml->question as $question) {
            Question::fromXml($db, $block->getId(), $question);
        }

        foreach ($xml->feedback as $feedback) {
            Feedback::fromXml($db, $block->getId(), $feedback);
        }

        return $xml;
    }

    #[\Override]
    protected function getNonDbFields(): array
    {
        return array_merge(parent::getNonDbFields(), ['scale']);
    }

    public function setAbbreviation(string $abbreviation): void
    {
        $this->abbreviation = $abbreviation;
    }

    public function getAbbreviation(): string
    {
        return $this->abbreviation;
    }

    public function getLabel(): string
    {
        if ($this->getAbbreviation() !== '') {
            return $this->getAbbreviation();
        }
        return $this->getTitle();
    }

    public static function _getTableName(): string
    {
        return 'rep_robj_xsev_block';
    }

    /**
     * @return Question[]
     */
    public function getQuestions(): array
    {
        return (Question::_getAllInstancesForParentId($this->db, $this->getId()));
    }

    /**
     * @return Feedback[]
     */
    public function getFeedbacks(): array
    {
        return (Feedback::_getAllInstancesForParentId($this->db, $this->getId()));
    }

    public function getBlockTableRow(ilDBInterface $db, ilCtrl $ilCtrl, ilSelfEvaluationPlugin $plugin): BlockTableRow
    {
        return new QuestionBlockTableRow($db, $ilCtrl, $plugin, $this);
    }
}
