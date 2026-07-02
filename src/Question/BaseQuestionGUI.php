<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Question;

use ilub\plugin\SelfEvaluation\Block\Block;
use ilSelfEvaluationPlugin;
use ilPropertyFormGUI;
use ilCtrl;
use ilGlobalTemplateInterface;
use ilToolbarGUI;
use ilObjSelfEvaluationGUI;
use ilAccessHandler;
use ilDBInterface;
use ilTable2GUI;
use ilConfirmationGUI;
use ILIAS\DI\UIServices;

abstract class BaseQuestionGUI
{
    public const MODE_CREATE = 1;
    public const MODE_UPDATE = 2;
    protected ilPropertyFormGUI $form;
    protected bool $enable_sorting = true;

    public function __construct(
        protected ilDBInterface $db,
        protected ilObjSelfEvaluationGUI $parent,
        protected ilGlobalTemplateInterface $tpl,
        protected ilCtrl $ctrl,
        protected ilToolbarGUI $toolbar,
        protected ilAccessHandler $access,
        protected UIServices $ui,
        protected ilSelfEvaluationPlugin $plugin,
        protected Block $block,
        protected Question $question
    ) {
    }

    public function executeCommand(): void
    {
        $this->ctrl->saveParameter($this, 'block_id');
        $this->performCommand();
    }

    public function performCommand(): void
    {
        $cmd = $this->ctrl->getCmd();

        if (!$this->access->checkAccess(
            "write",
            $cmd,
            $this->parent->object->getRefId(),
            $this->plugin->getId(),
            $this->parent->object->getId()
        )) {
            throw new \ilObjectException($this->plugin->txt("permission_denied"));
        }

        match ($cmd) {
            'showContent', 'cancel', 'addQuestion', 'saveSorting', 'createQuestion', 'saveRequired', 'editQuestion', 'updateQuestion', 'confirmDeleteQuestion', 'deleteQuestion' => $this->$cmd(
            ),
            default => $this->showContent(),
        };
    }

    protected function showContent()
    {
        $this->toolbar->addButton(
            '<b>&lt;&lt; ' . $this->plugin->txt('back_to_blocks') . '</b>',
            $this->ctrl->getLinkTargetByClass('ListBlocksGUI', 'showContent')
        );
        $this->toolbar->addButton($this->plugin->txt("add_question"), $this->ctrl->getLinkTarget($this, 'addQuestion'));

        $table = $this->createTableGUI();
        $table->setData($this->question::_getAllInstancesForParentIdAsArray($this->db, $this->block->getId()));
        $this->tpl->setContent($table->getHTML());
        $table->setTitle($this->block->getTitle() . ': ' . $this->plugin->txt('question_table_title'));
    }

    abstract protected function createTableGUI(): ilTable2GUI;

    public function cancel(): void
    {
        $this->ctrl->setParameterByClass(static::class, 'question_id', null);
        $this->ctrl->redirectByClass(static::class);
    }

    protected function saveSorting()
    {
        if ($this->parent->http->wrapper()->post()->has('position')) {
            $post_array = $this->parent->http->wrapper()->post()->retrieve(
                'position',
                $this->parent->refinery->kindlyTo()->listOf($this->parent->refinery->kindlyTo()->int())
            );

            foreach ($post_array as $position => $question_id) {
                $this->question->setId((int) $question_id);
                $this->question->read();
                $this->question->setPosition($position + 1);
                $this->question->update();
            }
        }
        $this->tpl->setOnScreenMessage(
            ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
            $this->plugin->txt("sorting_saved"),
            true
        );
        $this->ctrl->redirect($this, 'showContent');
    }

    public function addQuestion(): void
    {
        $this->initQuestionForm();
        $this->tpl->setContent($this->form->getHTML());
    }

    public function editQuestion(): void
    {
        $this->ctrl->saveParameter($this, 'question_id');
        $this->initQuestionForm('update');
        $this->setQuestionFormValues();
        $this->tpl->setContent($this->form->getHTML());
    }

    abstract public function setQuestionFormValues();

    public function initQuestionForm(string $mode = 'create'): void
    {
        $this->form = new ilPropertyFormGUI();
        $this->form->setTitle($this->plugin->txt($mode . '_question'));
        $this->form->setFormAction($this->ctrl->getFormAction($this));
        $this->form->addCommandButton($mode . 'Question', $this->plugin->txt($mode . '_question_button'));
        $this->form->addCommandButton('cancel', $this->plugin->txt('cancel'));
    }

    protected function createQuestion()
    {
        $this->updateQuestion("create");
    }

    protected function updateQuestion(string $mode = "update")
    {
        if ($mode === "update") {
            $this->ctrl->saveParameter($this, 'question_id');
        }
        $this->initQuestionForm($mode);
        $this->form->setValuesByPost();

        if ($this->form->checkInput()) {
            $this->createQuestionSetFields();
            $this->question->setParentId($this->block->getId());
            $this->question->update();
            $this->tpl->setOnScreenMessage(
                ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
                $this->plugin->txt('msg_question_updated'),
                true
            );
            $this->cancel();
        }

        $this->tpl->setContent($this->form->getHTML());
    }

    abstract public function createQuestionSetFields();

    public function confirmDeleteQuestion(): void
    {
        $this->tpl->setOnScreenMessage(
            ilGlobalTemplateInterface::MESSAGE_TYPE_QUESTION,
            $this->plugin->txt('qst_delete_question')
        );
        $conf = new ilConfirmationGUI();
        $conf->setHeaderText($this->plugin->txt('qst_delete_question'));
        $conf->setFormAction($this->ctrl->getFormAction($this));
        $conf->setCancel($this->plugin->txt('cancel'), 'cancel');
        $conf->setConfirm($this->plugin->txt('delete_question'), 'deleteQuestion');
        $title = $this->question->getTitle();
        if ($title === "") {
            $title = $this->plugin->txt('question') . ' ' . $this->block->getPosition(
            ) . '.' . $this->question->getPosition();
        }

        $conf->addItem('question_id', (string) $this->question->getId(), $title);
        $this->tpl->setContent($conf->getHTML());
    }

    public function deleteQuestion(): void
    {
        $this->tpl->setOnScreenMessage(
            ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
            $this->plugin->txt('msg_question_deleted'),
            true
        );
        $this->question->delete();
        $this->cancel();
    }

    public function enableSorting(bool $enable_sorting): void
    {
        $this->enable_sorting = $enable_sorting;
    }

    public function hasSorting(): bool
    {
        return $this->enable_sorting;
    }
}
