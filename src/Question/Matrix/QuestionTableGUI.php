<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Question\Matrix;

use ilTable2GUI;
use ilub\plugin\SelfEvaluation\Block\Block;
use ilSelfEvaluationPlugin;
use QuestionGUI;
use ilGlobalTemplateInterface;
use ilUtil;
use ILIAS\DI\UIServices;

class QuestionTableGUI extends ilTable2GUI
{
    public function __construct(
        QuestionGUI $a_parent_obj,
        protected UIServices $ui,
        protected ilSelfEvaluationPlugin $plugin,
        ilGlobalTemplateInterface $global_template,
        string $a_parent_cmd,
        protected Block $block,
        protected bool $sortable
    ) {
        $this->setId('sev_feedbacks');
        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setTitle($this->block->getTitle() . ': ' . $this->plugin->txt('question_table_title'));
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj));
        $this->ctrl->setParameterByClass('QuestionGUI', 'question_id', null);
        $this->ctrl->setParameterByClass('QuestionGUI', 'block_id', $this->block->getId());
        $this->setRowTemplate(
            'Question/tpl.template_question_row.html',
            'public/Customizing/global/plugins/Services/Repository/RepositoryObject/SelfEvaluation/'
        );
        $this->initColumns($global_template);
    }

    protected function initColumns(ilGlobalTemplateInterface $global_template)
    {
        if ($this->sortable) {
            $global_template->addJavaScript($this->plugin->getRelativeDirectory() . '/templates/js/sortable.js');
            $this->addColumn('', 'position', '20px');
            $this->addMultiCommand('saveSorting', $this->plugin->txt('save_sorting'));
        }

        $this->addColumn($this->plugin->txt('question_body'), $this->sortable ? 'question_body' : false, 'auto');
        $this->addColumn($this->plugin->txt('short_title'), '', 'auto');
        $this->addColumn($this->plugin->txt('is_inverted'), $this->sortable ? 'is_inverse' : false, 'auto');
        $this->addColumn($this->plugin->txt('actions'), '', 'auto');
    }

    #[\Override]
    public function fillRow(array $a_set): void
    {
        $this->ctrl->setParameterByClass('QuestionGUI', 'question_id', $a_set['id']);

        if ($this->sortable) {
            $this->tpl->setCurrentBlock("sortable");
            $this->tpl->setVariable('MOVE_IMG_SRC', $this->plugin->getRelativeDirectory() . "/templates/images/move.png");
            $this->tpl->setVariable('ID', $a_set['id']);
            $this->tpl->parseCurrentBlock();
        }
        $this->tpl->setVariable('TITLE', strip_tags((string) $a_set['question_body']));
        $this->tpl->setVariable(
            'EDIT_LINK',
            $this->ctrl->getLinkTargetByClass('QuestionGUI', 'editQuestion')
        );
        $this->tpl->setVariable(
            'BODY',
            $a_set['title'] ?:
                $this->plugin->txt('question') . ' ' . $this->block->getPosition() . '.' . $a_set['position']
        );
        $this->tpl->setVariable(
            'IS_INVERTED',
            $a_set['is_inverse'] ? ilUtil::getImagePath('standard/icon_ok.svg') : $this->plugin->getRelativeDirectory(
            ) . '/templates/images/empty.png'
        );
        // Actions
        $dropdown = $this->ui->factory()->dropdown()->standard([
            $this->ui->factory()->link()->standard(
                $this->plugin->txt('edit_question'),
                $this->ctrl->getLinkTargetByClass('QuestionGUI', 'editQuestion')
            ),
            $this->ui->factory()->link()->standard(
                $this->plugin->txt('delete_question'),
                $this->ctrl->getLinkTargetByClass('QuestionGUI', 'confirmDeleteQuestion')
            )
        ]);

        $this->tpl->setVariable('ACTIONS', $this->ui->renderer()->render($dropdown));
    }
}
