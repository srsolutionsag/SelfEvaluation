<?php

declare(strict_types=1);

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;
use ilub\plugin\SelfEvaluation\Block\Matrix\QuestionBlockInterface;
use ilub\plugin\SelfEvaluation\Block\Virtual\VirtualOverallBlock;
use ilub\plugin\SelfEvaluation\Block\Matrix\QuestionBlock;
use ilub\plugin\SelfEvaluation\UIHelper\TinyMceTextAreaInputGUI;
use ilub\plugin\SelfEvaluation\UIHelper\SliderInputGUI;
use ilub\plugin\SelfEvaluation\Feedback\Feedback;
use ilub\plugin\SelfEvaluation\Feedback\FeedbackTableGUI;
use JetBrains\PhpStorm\NoReturn;
use ILIAS\DI\UIServices;

class FeedbackGUI
{
    protected ilPropertyFormGUI $form;
    protected ilTemplate $overview;
    protected int $total = 0;
    protected QuestionBlockInterface $block;
    protected Feedback $feedback;

    public function __construct(
        protected ilDBInterface $db,
        protected ilObjSelfEvaluationGUI $parent,
        protected ilGlobalTemplateInterface $tpl,
        protected ilCtrl $ctrl,
        protected ilToolbarGUI $toolbar,
        protected ilAccessHandler $access,
        private WrapperFactory $http,
        private Factory $refinery,
        protected ilSelfEvaluationPlugin $plugin,
        protected UIServices $ui
    ) {
    }

    public function executeCommand(): void
    {
        $parent_overall = $this->http->query()->has('parent_overall') ? $this->http->query()->retrieve(
            'parent_overall',
            $this->refinery->kindlyTo()->int()
        ) : null;
        if ($parent_overall) {
            $this->block = new VirtualOverallBlock($this->parent->object->getId(), $this->plugin);
        } else {
            $this->block = new QuestionBlock(
                $this->db,
                $this->http->query()->retrieve('block_id', $this->refinery->kindlyTo()->int())
            );
        }
        $feedback_id = $this->http->query()->has('feedback_id') ? $this->http->query()->retrieve(
            'feedback_id',
            $this->refinery->kindlyTo()->int()
        ) : null;
        if ($feedback_id) {
            $this->feedback = new Feedback($this->db, $feedback_id);
        } else {
            $this->feedback = Feedback::_getNewInstanceByParentId($this->db, $this->getBlock()->getId());
        }
        if ($parent_overall) {
            $this->feedback->setParentTypeOverall(true);
        }

        $this->performCommand();
    }

    public function getStandardCommand(): string
    {
        return 'listObjects';
    }

    protected function performCommand()
    {
        $this->ctrl->saveParameter($this, 'block_id');
        $this->ctrl->saveParameter($this, 'parent_overall');
        $this->ctrl->saveParameter($this, 'feedback_id');
        $this->ctrl->saveParameterByClass('BlockGUI', 'block_id');

        $cmd = $this->ctrl->getCmd() ?: $this->getStandardCommand();

        switch ($cmd) {
            case 'listObjects':
            case 'addNew':
            case 'cancel':
            case 'createObject':
            case 'updateObject':
            case 'editFeedback':
            case 'checkNextValue':
            case 'deleteFeedback':
            case 'deleteFeedbacks':
            case 'deleteObject':
                if (!$this->access->checkAccess(
                    "read",
                    $cmd,
                    $this->parent->object->getRefId(),
                    $this->plugin->getId(),
                    $this->parent->object->getId()
                )) {
                    throw new ilObjectException($this->plugin->txt("permission_denied"));
                }
                $this->$cmd();
                break;
        }
    }

    protected function cancel()
    {
        $this->ctrl->setParameter($this, 'feedback_id', '');
        $this->ctrl->redirect($this);
    }

    protected function listObjects()
    {
        $this->toolbar->addButton(
            '&lt;&lt; ' . $this->plugin->txt('back_to_blocks'),
            $this->ctrl->getLinkTargetByClass('ListBlocksGUI', 'showContent')
        );
        $this->toolbar->addButton($this->plugin->txt('add_new_feedback'), $this->ctrl->getLinkTarget($this, 'addNew'));

        $ov = $this->getOverview();
        $table = new FeedbackTableGUI($this->db, $this->ui, $this, $this->plugin, 'listObjects', $this->block);
        $this->tpl->setContent($ov->get() . '<br><br>' . $table->getHTML());
    }

    protected function addNew()
    {
        $this->initForm();
        $this->feedback->setStartValue(
            Feedback::_getNextMinValueForParentId(
                $this->db,
                $this->block->getId(),
                $this->http->query()->has('start_value') ? $this->http->query()->retrieve(
                    'start_value',
                    $this->refinery->kindlyTo()->int()
                ) : 0,
                0,
                $this->feedback->isParentTypeOverall()
            )
        );
        $this->feedback->setEndValue(
            Feedback::_getNextMaxValueForParentId(
                $this->db,
                $this->block->getId(),
                $this->feedback->getStartValue(),
                0,
                $this->feedback->isParentTypeOverall()
            )
        );
        $this->setValues();
        $this->tpl->setContent($this->form->getHTML());
    }

    #[NoReturn]
    protected function checkNextValue(): never
    {
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');
        $ignore = $this->http->query()->has('feedback_id') ? $this->http->query()->retrieve(
            'feedback_id',
            $this->refinery->kindlyTo()->int()
        ) : 0;
        $start_value = $this->http->query()->has('start_value') ? $this->http->query()->retrieve(
            'start_value',
            $this->refinery->kindlyTo()->int()
        ) : 0;

        $start = Feedback::_getNextMinValueForParentId(
            $this->db,
            $this->block->getId(),
            $start_value,
            $ignore,
            $this->feedback->isParentTypeOverall()
        );
        $end = Feedback::_getNextMaxValueForParentId(
            $this->db,
            $this->block->getID(),
            $start,
            $ignore,
            $this->feedback->isParentTypeOverall()
        );
        $from = $this->http->query()->has('from') ? $this->http->query()->retrieve(
            'from',
            $this->refinery->kindlyTo()->int()
        ) : 0;
        $to = $this->http->query()->has('to') ? $this->http->query()->retrieve(
            'to',
            $this->refinery->kindlyTo()->int()
        ) : 0;
        $state = $from >= $start && $to <= $end;
        echo json_encode([
            'check' => $state,
            'start_value' => $start_value,
            'next_from' => $start,
            'next_to' => $end
        ]);

        exit;
    }

    protected function initForm(string $mode = 'create')
    {
        $this->form = new ilPropertyFormGUI();
        $this->form->setTitle($this->plugin->txt($mode . '_feedback_form'));
        $this->form->setFormAction($this->ctrl->getFormAction($this));
        $this->form->addCommandButton($mode . 'Object', $this->plugin->txt($mode . '_feedback_button'));
        $this->form->addCommandButton('cancel', $this->plugin->txt('cancel'));
        // Block
        $te = new ilNonEditableValueGUI($this->plugin->txt('block'), 'block');
        $te->setValue($this->block->getTitle());
        $this->form->addItem($te);
        // Title
        $te = new ilTextInputGUI($this->plugin->txt('title'), 'title');
        $te->setRequired(true);
        $this->form->addItem($te);
        // Description
        $te = new ilTextInputGUI($this->plugin->txt('description'), 'description');
        $this->form->addItem($te);

        if ($mode === 'create') {
            $radio_options = new ilRadioGroupInputGUI($this->plugin->txt('feedback_range_type'), 'feedback_range_type');
            $option_auto = new ilRadioOption($this->plugin->txt("option_auto"), 'option_auto');
            $option_auto->setInfo($this->plugin->txt("option_auto_info"));

            $option_slider = new ilRadioOption($this->plugin->txt("option_slider"), 'option_slider');
            $sl = new SliderInputGUI(
                $this->tpl,
                $this->plugin,
                $this->plugin->txt('slider'),
                'slider',
                0,
                100,
                $this->ctrl->getLinkTarget($this, 'checkNextValue')
            );
            $option_slider->addSubItem($sl);

            if (Feedback::_isComplete($this->db, $this->block->getId(), $this->feedback->isParentTypeOverall())) {
                $option_slider->setDisabled(true);
            }

            $radio_options->addOption($option_auto);
            $radio_options->addOption($option_slider);

            $radio_options->setRequired(true);

            $this->form->addItem($radio_options);
        } else {
            $sl = new SliderInputGUI(
                $this->tpl,
                $this->plugin,
                $this->plugin->txt('slider'),
                'slider',
                0,
                100,
                $this->ctrl->getLinkTarget($this, 'checkNextValue')
            );
            $this->form->addItem($sl);
        }

        $te = new TinyMceTextAreaInputGUI(
            $this->parent->object->getRefId(),
            $this->plugin->getId(),
            $this->plugin->txt('feedback_text'),
            'feedback_text'
        );
        $te->setRequired(true);
        $this->form->addItem($te);
    }

    protected function createObject()
    {
        $this->initForm();
        if ($this->form->checkInput()) {
            $obj = Feedback::_getNewInstanceByParentId(
                $this->db,
                $this->block->getId(),
                $this->feedback->isParentTypeOverall()
            );
            $obj->setTitle($this->form->getInput('title'));
            $obj->setDescription($this->form->getInput('description'));
            if ($this->form->getInput('feedback_range_type') == 'option_auto') {
                $range = Feedback::_rearangeFeedbackLinear(
                    $this->db,
                    $this->block->getId(),
                    $this->feedback->isParentTypeOverall()
                );
                $obj->setStartValue(100 - $range);
                $obj->setEndValue(100);
            } else {
                $obj->setStartValue((int) $this->form->getInput("slider_slider_from"));
                $obj->setEndValue((int) $this->form->getInput("slider_slider_to"));
            }

            $obj->setFeedbackText($this->form->getInput('feedback_text'));
            $obj->create();
            $this->tpl->setOnScreenMessage(
                ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
                $this->plugin->txt('msg_feedback_created')
            );
            $this->cancel();
        } else {
            $this->form->getItemByPostVar('slider')->setValues([
                $this->form->getInput("slider_slider_from"),
                $this->form->getInput("slider_slider_to")
            ]);
        }
        $this->form->setValuesByPost();
        $this->tpl->setContent($this->form->getHTML());
    }

    protected function setValues()
    {
        $values['title'] = $this->feedback->getTitle();
        $values['description'] = $this->feedback->getDescription();
        $values['start_value'] = $this->feedback->getStartValue();
        $values['end_value'] = $this->feedback->getEndValue();
        $values['feedback_text'] = $this->feedback->getFeedbackText();
        $values['slider'] = [$this->feedback->getStartValue(), $this->feedback->getEndValue()];
        if (Feedback::_isComplete($this->db, $this->block->getId(), $this->feedback->isParentTypeOverall())) {
            $values['feedback_range_type'] = 'option_auto';
        } else {
            $values['feedback_range_type'] = 'option_slider';
        }
        $this->form->setValuesByArray($values);
    }

    protected function editFeedback()
    {
        $this->initForm('update');
        $this->setValues();
        $this->tpl->setContent($this->form->getHTML());
    }

    protected function updateObject()
    {
        $this->initForm('update');
        if ($this->form->checkInput()) {
            $this->feedback->setTitle($this->form->getInput('title'));
            $this->feedback->setDescription($this->form->getInput('description'));
            $this->feedback->setStartValue((int) $this->form->getInput('slider_slider_from'));
            $this->feedback->setEndValue((int) $this->form->getInput('slider_slider_to'));
            $this->feedback->setFeedbackText($this->form->getInput('feedback_text'));
            $this->feedback->update();
            $this->tpl->setOnScreenMessage(
                ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
                $this->plugin->txt('msg_feedback_created')
            );
            $this->cancel();
        }
        $this->form->setValuesByPost();
        $this->tpl->setContent($this->form->getHTML());
    }

    protected function deleteFeedback()
    {
        $this->deleteFeedbacksConfirmation([$this->feedback->getId()]);
    }

    protected function deleteFeedbacks()
    {
        if ($this->http->post()->has('id')) {
            $ids = $this->http->post()->retrieve(
                'id',
                $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->int())
            );
        } else {
            $this->tpl->setOnScreenMessage(
                ilGlobalTemplateInterface::MESSAGE_TYPE_FAILURE,
                $this->plugin->txt('msg_no_feedback_selected')
            );
            $this->listObjects();
            return;
        }
        $this->deleteFeedbacksConfirmation($ids);
    }

    protected function deleteFeedbacksConfirmation(array $ids = [])
    {
        $this->tpl->setOnScreenMessage(
            ilGlobalTemplateInterface::MESSAGE_TYPE_QUESTION,
            $this->plugin->txt('qst_delete_feedback')
        );
        $conf = new ilConfirmationGUI();
        $conf->setFormAction($this->ctrl->getFormAction($this));
        $conf->setCancel($this->plugin->txt('cancel'), 'cancel');
        $conf->setConfirm($this->plugin->txt('delete_feedback'), 'deleteObject');
        $conf->setHeaderText($this->plugin->txt('qst_delete_feedback'));
        foreach ($ids as $id) {
            $obj = new Feedback($this->db, (int) $id);
            $conf->addItem('id[]', (string) $obj->getId(), $obj->getTitle());
        }

        $this->tpl->setContent($conf->getHTML());
    }

    protected function deleteObject()
    {
        $this->tpl->setOnScreenMessage(
            ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
            $this->plugin->txt('msg_feedback_deleted'),
            true
        );

        $ids = $this->http->post()->retrieve(
            'id',
            $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->int())
        );
        foreach ($ids as $id) {
            $obj = new Feedback($this->db, (int) $id);
            $obj->delete();
        }
        $this->cancel();
    }

    protected function getOverview(): ilTemplate
    {
        $this->overview = $this->plugin->getTemplate('default/Feedback/tpl.feedback_overview.html');

        $this->getMeasurement();
        $min = Feedback::_getNextMinValueForParentId(
            $this->db,
            $this->block->getId(),
            0,
            0,
            $this->feedback->isParentTypeOverall()
        );
        $feedbacks = Feedback::_getAllInstancesForParentId(
            $this->db,
            $this->block->getId(),
            false,
            $this->feedback->isParentTypeOverall()
        );

        if (count($feedbacks) === 0) {
            $this->parseOverviewBlock('blank', 100, 0);
            return $this->overview;
        }
        $fb = null;
        foreach ($feedbacks as $fb) {
            if ($min <= $fb->getStartValue() && $min !== 100 && $fb->getStartValue() - $min != 0) {
                $this->parseOverviewBlock('blank', $fb->getStartValue() - $min, $min);
            }
            $this->parseOverviewBlock('fb', $fb->getEndValue() - $fb->getStartValue(), $fb->getId(), $fb->getTitle());
            $min = Feedback::_getNextMinValueForParentId(
                $this->db,
                $this->block->getId(),
                $fb->getEndValue(),
                0,
                $this->feedback->isParentTypeOverall()
            );
        }
        if ($min !== 100 && is_object($fb)) {
            $this->parseOverviewBlock('blank', 100 - $min, $min);
        }

        return $this->overview;
    }

    protected function getMeasurement()
    {
        for ($x = 1; $x <= 100; $x += 1) {
            $this->overview->setCurrentBlock('line');
            if ($x % 5 === 0) {
                $this->overview->setVariable('INT', $x . '&nbsp;');
                $this->overview->setVariable('LINE_CSS', '_double');
            } else {
                $this->overview->setVariable('INT');
            }
            $this->overview->setVariable('WIDTH', 10);
            $this->overview->parseCurrentBlock();
        }
    }

    public function parseOverviewBlock(string $type, int $width, int $value, string $title = ''): void
    {
        $href = '';
        $css = '';
        switch ($type) {
            case 'blank':
                $this->ctrl->setParameter($this, 'feedback_id', null);
                $this->ctrl->setParameter($this, 'start_value', $value);
                $href = ($this->ctrl->getLinkTarget($this, 'addNew'));
                $css = '_blank';
                $title = $this->plugin->txt('insert_feedback');
                break;
            case 'fb':
                $this->ctrl->setParameter($this, 'feedback_id', $value);
                $href = ($this->ctrl->getLinkTarget($this, 'editFeedback'));
                break;
        }
        $this->total += $width;
        $this->overview->setCurrentBlock('fb');
        $this->overview->setVariable('FEEDBACK', $title);
        $this->overview->setVariable('HREF', $href);
        $this->overview->setVariable('WIDTH', $width);
        $this->overview->setVariable('CSS', $css);
        $this->overview->parseCurrentBlock();
    }

    public function getBlock(): QuestionBlockInterface
    {
        return $this->block;
    }
}
