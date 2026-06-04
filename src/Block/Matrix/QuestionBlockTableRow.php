<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Block\Matrix;

use ilub\plugin\SelfEvaluation\Question\Matrix\Question;
use ilub\plugin\SelfEvaluation\Feedback\Feedback;
use ilUtil;
use ilub\plugin\SelfEvaluation\Block\BlockTableRow;
use ilub\plugin\SelfEvaluation\Block\BlockTableAction;
use ilDBInterface;
use ilCtrl;
use ilSelfEvaluationPlugin;

class QuestionBlockTableRow extends BlockTableRow
{
    public function __construct(
        protected ilDBInterface $db,
        ilCtrl $ilCtrl,
        ilSelfEvaluationPlugin $plugin,
        QuestionBlock $block
    ) {
        parent::__construct($ilCtrl, $plugin, $block);

        $questions = Question::_getAllInstancesForParentId($this->db, $block->getId());
        $this->setQuestionCount(count($questions));
        $question_action = $this->getQuestionAction();
        $this->setQuestionsLink($question_action->getLink());
        $this->addAction($question_action);

        $feedbacks = Feedback::_getAllInstancesForParentId($this->db, $block->getId());
        $this->setFeedbackCount(count($feedbacks));
        $feedback_action = $this->getFeedbackAction();
        $this->setFeedbackLink($feedback_action->getLink());
        $this->addAction($feedback_action);

        if (Feedback::_isComplete($this->db, $block->getId())) {
            $img_path = ilUtil::getImagePath('standard/icon_ok.svg');
        } else {
            $img_path = ilUtil::getImagePath('standard/icon_not_ok.svg');
        }
        $this->setStatusImg($img_path);

        $this->setAbbreviation($block->getAbbreviation());
    }

    #[\Override]
    protected function saveCtrlParameters()
    {
        $this->ctrl->setParameterByClass('QuestionBlockGUI', 'block_id', $this->getBlockId());
        $this->ctrl->setParameterByClass('QuestionGUI', 'block_id', $this->getBlockId());
        $this->ctrl->setParameterByClass('FeedbackGUI', 'block_id', $this->getBlockId());
    }

    protected function getQuestionAction(): BlockTableAction
    {
        $title = $this->plugin->txt('edit_questions');
        $link = $this->ctrl->getLinkTargetByClass('QuestionGUI', 'showContent');
        $cmd = 'edit_questions';

        return new BlockTableAction($title, $cmd, $link);
    }

    protected function getFeedbackAction(): BlockTableAction
    {
        $title = $this->plugin->txt('edit_feedback');
        $link = $this->ctrl->getLinkTargetByClass('FeedbackGUI', 'listObjects');
        $cmd = 'listObjects';
        $position = 2;
        return new BlockTableAction($title, $cmd, $link, $position);
    }
}
