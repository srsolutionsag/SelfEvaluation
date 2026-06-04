<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Feedback;

use ilTable2GUI;
use ilub\plugin\SelfEvaluation\Block\Matrix\QuestionBlockInterface;
use ilRepositoryObjectPlugin;
use ilDBInterface;
use FeedbackGUI;
use ILIAS\DI\UIServices;

class FeedbackTableGUI extends ilTable2GUI
{
    public function __construct(
        protected ilDBInterface $db,
        protected UIServices $ui,
        FeedbackGUI $a_parent_obj,
        protected ilRepositoryObjectPlugin $plugin,
        string $a_parent_cmd,
        QuestionBlockInterface $block,
        bool $is_ovarall = false
    ) {
        $this->setId('');

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setTitle($block->getTitle() . ': ' . $this->plugin->txt('feedback_table_title'));
        $this->addColumn("", "", "1");
        $this->addColumn($this->plugin->txt('fb_title'), 'title', 'auto');
        $this->addColumn($this->plugin->txt('fb_body'), 'feedback_text', 'auto');
        $this->addColumn($this->plugin->txt('fb_start'), 'start_value', 'auto');
        $this->addColumn($this->plugin->txt('fb_end'), 'end_value', 'auto');
        $this->addColumn($this->plugin->txt('actions'));

        $this->ctrl->setParameter($this->parent_obj, 'feedback_id', null);
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj));
        $this->addMultiCommand("deleteFeedbacks", $this->plugin->txt("delete_feedback"));

        $this->setRowTemplate(
            'Feedback/tpl.template_feedback_row.html',
            'public/Customizing/global/plugins/Services/Repository/RepositoryObject/SelfEvaluation/'
        );

        $this->setData(
            Feedback::_getAllInstancesForParentId(
                $this->db,
                $a_parent_obj->getBlock()->getId(),
                true,
                $is_ovarall
            )
        );
    }

    #[\Override]
    protected function fillRow($a_set): void
    {
        $obj = new Feedback($this->db, $a_set['id']);
        $this->tpl->setVariable("ID", $obj->getId());
        $this->tpl->setVariable('TITLE', $obj->getTitle());
        $this->tpl->setVariable('BODY', strip_tags($obj->getFeedbackText()));
        $start_sign = "> ";
        if ($obj->getStartValue() == "0") {
        } elseif ($obj->getStartValue() == "100") {
            $start_sign = "= ";
        }
        $this->tpl->setVariable('START', $start_sign . $obj->getStartValue() . '%');
        $this->tpl->setVariable('END', '<= ' . $obj->getEndValue() . '%');
        $this->ctrl->setParameter($this->parent_obj, 'feedback_id', $obj->getId());
        // Actions
        $dropdown = $this->ui->factory()->dropdown()->standard([
            $this->ui->factory()->link()->standard(
                $this->plugin->txt('edit_feedback'),
                $this->ctrl->getLinkTarget($this->parent_obj, 'editFeedback')
            ),
            $this->ui->factory()->link()->standard(
                $this->plugin->txt('delete_feedback'),
                $this->ctrl->getLinkTarget($this->parent_obj, 'deleteFeedback')
            )
        ]);

        $this->tpl->setVariable('ACTIONS', $this->ui->renderer()->render($dropdown));
    }
}
