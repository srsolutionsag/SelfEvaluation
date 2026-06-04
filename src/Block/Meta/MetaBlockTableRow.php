<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Block\Meta;

use ilub\plugin\SelfEvaluation\Block\BlockTableRow;
use ilub\plugin\SelfEvaluation\Block\BlockTableAction;
use ilCtrl;
use ilSelfEvaluationPlugin;
use ilUtil;

class MetaBlockTableRow extends BlockTableRow
{
    public function __construct(
        ilCtrl $ilCtrl,
        ilSelfEvaluationPlugin $plugin,
        MetaBlock $block
    ) {
        parent::__construct($ilCtrl, $plugin, $block);

        $this->setQuestionCount(count($block->getQuestions()));
        $question_action = $this->getQuestionAction();
        $this->setQuestionsLink($question_action->getLink());
        $this->addAction($question_action);

        $img_path = ilUtil::getImagePath('standard/icon_ok.svg');
        $this->setStatusImg($img_path);
    }

    #[\Override]
    protected function saveCtrlParameters()
    {
        $this->ctrl->setParameterByClass('MetaBlockGUI', 'block_id', $this->getBlockId());
        $this->ctrl->setParameterByClass('MetaQuestionGUI', 'block_id', $this->getBlockId());
    }

    protected function getQuestionAction(): BlockTableAction
    {
        $title = $this->plugin->txt('edit_questions');
        $link = $this->ctrl->getLinkTargetByClass('MetaQuestionGUI', 'listFields');
        $cmd = 'edit_questions';

        return new BlockTableAction($title, $cmd, $link);
    }
}
