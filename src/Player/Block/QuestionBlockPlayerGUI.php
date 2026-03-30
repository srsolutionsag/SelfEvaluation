<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Player\Block;

use ilub\plugin\SelfEvaluation\Player\Question\QuestionPlayerGUI;
use ilub\plugin\SelfEvaluation\Player\PlayerFormContainer;
use ilub\plugin\SelfEvaluation\UIHelper\Scale\Scale;
use ilub\plugin\SelfEvaluation\UIHelper\MatrixHeaderGUI;
use ilObjSelfEvaluation;

class QuestionBlockPlayerGUI extends BlockPlayerGUI
{
    public function getBlockForm(?PlayerFormContainer $parent_form): PlayerFormContainer
    {
        $form = parent::getBlockForm($parent_form);

        $scale = Scale::_getInstanceByObjId($this->db, $this->block->getParentId());
        $matrix_gui = new MatrixHeaderGUI($this->plugin);
        $matrix_gui->setScale($scale->getUnitsAsArray());
        $form->addItem($matrix_gui);

        $question_form_size = min(max(12 - ($scale->getAmount() + 1), 3), 6);
        $form->setQuestionFieldSize($question_form_size);

        $questions = $this->block->getQuestions();

        if ($this->parent->object->getSortType() == ilObjSelfEvaluation::SHUFFLE_IN_BLOCKS) {
            shuffle($questions);
        }
        foreach ($questions as $question) {
            $qst_gui = new QuestionPlayerGUI($this->plugin, $question);
            $form->addItem($qst_gui->getQuestionFormItem($scale));
        }

        return $form;
    }
}
