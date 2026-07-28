<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Question\Meta;

use ilTable2GUI;
use ilSelfEvaluationPlugin;
use ilub\plugin\SelfEvaluation\Question\Meta\Type\MetaTypeFactory;
use ILIAS\DI\UIServices;
use MetaQuestionGUI;
use ilGlobalTemplateInterface;
use ilub\plugin\SelfEvaluation\Block\Block;

class MetaQuestionTableGUI extends ilTable2GUI
{
    public function __construct(
        MetaQuestionGUI $a_parent_obj,
        protected UIServices $ui,
        protected ilSelfEvaluationPlugin $plugin,
        ilGlobalTemplateInterface $global_template,
        string $a_parent_cmd,
        protected array $types,
        protected bool $sortable,
        Block $block
    ) {
        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setTitle($block->getTitle() . ': ' . $this->plugin->txt('question_table_title'));

        $this->setFormAction($this->ctrl->getFormAction($this->getParentObject(), $this->getParentCmd()));

        $this->addCommandButton('saveRequired', $this->lng->txt('save'));

        $this->setEnableHeader(true);
        $this->setEnableNumInfo(true);

        $this->setRowTemplate(
            'Question/tpl.template_meta_question_row.html',
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
        } else {
            $this->setDefaultOrderField('name');
            $this->setDefaultOrderDirection('asc');
        }

        $this->addColumn($this->plugin->txt('question'), $this->sortable ? 'name' : false, 'auto');
        $this->addColumn($this->plugin->txt('short_title'), $this->sortable ? 'short_title' : false, 'auto');
        $this->addColumn($this->plugin->txt('type'), "", 'auto');
        $this->addColumn($this->plugin->txt('required_field'), $this->sortable ? 'required' : false, 'auto');
        $this->addColumn($this->plugin->txt('actions'));
    }

    #[\Override]
    protected function fillRow(array $a_set): void
    {
        $this->ctrl->setParameter($this->getParentObject(), 'question_id', $a_set['id']);

        if ($this->sortable) {
            $this->tpl->setCurrentBlock('sortable');
            $this->tpl->setVariable('MOVE_IMG_SRC', $this->plugin->getRelativeDirectory() . "/templates/images/move.png");
            $this->tpl->setVariable('ID', $a_set['id']);
            $this->tpl->parseCurrentBlock();
        }
        $this->tpl->setVariable('VAL_ID', $a_set['id']);
        $this->tpl->setVariable(
            'EDIT_LINK',
            $this->ctrl->getLinkTarget($this->getParentObject(), 'editQuestion')
        );
        $this->tpl->setVariable('VAL_NAME', $a_set['name']);
        $this->tpl->setVariable('VAL_SHORT_TITLE', $a_set['short_title']);
        $type_factory = new MetaTypeFactory();
        $this->tpl->setVariable(
            'VAL_TYPE',
            $this->plugin->txt($type_factory->getTypeByTypeId($a_set['type_id'])->getTypeName())
        );

        $this->tpl->setVariable('REQUIRED_CHECKED', $a_set['required'] ? 'checked="checked"' : '');

        // actions
        $dropdown = $this->ui->factory()->dropdown()->standard([
            $this->ui->factory()->link()->standard(
                $this->lng->txt('edit'),
                $this->ctrl->getLinkTarget($this->getParentObject(), 'editQuestion')
            ),
            $this->ui->factory()->link()->standard(
                $this->lng->txt('delete'),
                $this->ctrl->getLinkTarget($this->getParentObject(), 'confirmDeleteQuestion')
            )
        ]);

        $this->tpl->setVariable('ACTIONS', $this->ui->renderer()->render($dropdown));
    }
}
