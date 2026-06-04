<?php

declare(strict_types=1);

use ilub\plugin\SelfEvaluation\Block\Block;
use ilub\plugin\SelfEvaluation\Dataset\Dataset;
use ilub\plugin\SelfEvaluation\Identity\Identity;
use ilub\plugin\SelfEvaluation\Dataset\Data;
use ilub\plugin\SelfEvaluation\Question\Matrix\Question as MatrixQuestion;
use ilub\plugin\SelfEvaluation\Block\BlockFactory;
use ilub\plugin\SelfEvaluation\Block\Virtual\VirtualQuestionBlock;
use ilub\plugin\SelfEvaluation\Player\PlayerFormContainer;
use ilub\plugin\SelfEvaluation\Player\Block\QuestionBlockPlayerGUI;
use ilub\plugin\SelfEvaluation\Player\Block\MetaBlockPlayerGUI;
use ilub\plugin\SelfEvaluation\Block\Matrix\QuestionBlock;
use ilub\plugin\SelfEvaluation\Block\Meta\MetaBlock;
use ilub\plugin\SelfEvaluation\Question\Meta\MetaQuestion;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;

class PlayerGUI
{
    protected PlayerFormContainer $form;
    protected int $ref_id = 0;
    protected Identity $identity;
    protected bool|Dataset $dataset;

    public function __construct(
        protected ilDBInterface $db,
        protected ilObjSelfEvaluationGUI $parent,
        protected ilGlobalTemplateInterface $tpl,
        protected ilCtrl $ctrl,
        protected ilSelfEvaluationPlugin $plugin,
        protected WrapperFactory $http,
        protected Factory $refinery
    ) {
        $this->ref_id = $this->parent->object->getRefId();
    }

    public function executeCommand(): void
    {
        if (!$this->http->query()->has('uid')) {
            $this->tpl->setOnScreenMessage(
                ilGlobalTemplateInterface::MESSAGE_TYPE_FAILURE,
                $this->plugin->txt('uid_not_given'),
                true
            );
            $this->ctrl->redirect($this->parent);
        } else {
            $this->identity = new Identity(
                $this->db,
                $this->http->query()->retrieve('uid', $this->refinery->kindlyTo()->int())
            );
        }

        if ($this->http->query()->has('dataset_id')) {
            $this->dataset = new Dataset(
                $this->db,
                (int) $this->http->query()->retrieve('dataset_id', $this->refinery->kindlyTo()->string())
            );
            $this->ctrl->setParameter($this, "dataset_id", $this->dataset->getId());
        } else {
            $this->dataset = new Dataset($this->db);
        }

        $this->ctrl->saveParameter($this, 'uid');

        $this->performCommand();
    }

    public function getStandardCommand(): string
    {
        return 'showContent';
    }

    public function performCommand(): void
    {
        $cmd = $this->ctrl->getCmd() ?: $this->getStandardCommand();

        switch ($cmd) {
            case 'startScreen':
            case 'doEvaluationStep':
            case 'resumeEvaluation':
            case 'finishEvaluation':
            case 'cancel':
            case 'endScreen':
            case 'startNewEvaluation':
            case 'nextPage':
                //				$this->checkPermission('read'); FSX
                $this->$cmd();
                break;
        }
    }

    public function cancel(): void
    {
        $this->ctrl->redirect($this->parent);
    }

    /**
     * @throws ilCtrlException
     * @throws ilTemplateException
     */
    public function startScreen(): void
    {
        $this->tpl->addCss($this->plugin->getStyleSheetLocation("css/player.css"));
        $content = $this->plugin->getTemplate('default/Dataset/tpl.dataset_presentation.html');
        $content->setVariable('INTRO_HEADER', $this->plugin->txt('intro_header'));
        $content->setVariable('INTRO_BODY', $this->parent->object->getIntro());
        if ($this->parent->object->isActive()) {
            $content->setCurrentBlock('button');
            $this->dataset = Dataset::_getInstanceByIdentifierId($this->db, $this->identity->getId());
            if ($this->dataset && !$this->dataset->isComplete()) {
                $this->ctrl->setParameter($this, "dataset_id", $this->dataset->getId());
                $content->setVariable('START_BUTTON', $this->plugin->txt('resume_button'));
                $content->setVariable('START_HREF', $this->ctrl->getLinkTarget($this, 'resumeEvaluation'));
            } else {
                $content->setVariable('START_BUTTON', $this->plugin->txt('start_button'));
                $content->setVariable('START_HREF', $this->ctrl->getLinkTarget($this, 'startNewEvaluation'));
            }
            $content->parseCurrentBlock();
        } else {
            $this->tpl->setOnScreenMessage(
                ilGlobalTemplateInterface::MESSAGE_TYPE_INFO,
                $this->plugin->txt('not_active')
            );
        }
        $this->tpl->setContent($content->get());
    }

    public function startNewEvaluation(): void
    {
        $this->dataset->setIdentifierId($this->identity->getId());
        $this->dataset->setCreationDate(time());
        $this->dataset->update();
        $this->ctrl->setParameter($this, "dataset_id", $this->dataset->getId());
        $this->ctrl->redirect($this, 'doEvaluationStep');
    }

    public function resumeEvaluation(): void
    {
        $this->doEvaluationStep();
    }

    public function doEvaluationStep(): void
    {
        $this->initPresentationForm();
        $this->fillForm();
        $this->tpl->setContent($this->form->getHTML());
    }

    /**
     * @throws ilCtrlException
     */
    public function nextPage(): void
    {
        $this->initPresentationForm();

        if ($this->form->checkInput()) {
            $post_data = $this->getDataFromPost();
            $this->dataset->updateValuesByPost($post_data);

            $this->ctrl->setParameter($this, 'page', $_GET['page'] + 1);
            $this->ctrl->redirect($this, 'doEvaluationStep');
        }

        $this->form->setValuesByPost();
        $this->tpl->setContent($this->form->getHTML());
    }

    private function getDataFromPost(): array
    {
        global $DIC;
        $items = count($DIC->http()->request()->getParsedBody()) - 1;
        if ($items === 0) {
            return [];
        }
        $found_question = 0;
        $data = [];
        $i = 0;
        while ($found_question < $items) {
            if ($this->http->post()->has("qst_" . $i) || $this->http->post()->has("mqst_" . $i)) {
                $qid = $this->http->post()->has("qst_" . $i) ? "qst_" . $i : "mqst_" . $i;
                try {
                    $value = $this->http->post()->retrieve($qid, $this->refinery->kindlyTo()->string());
                } catch (Exception) {
                    $value = $this->http->post()->retrieve(
                        $qid,
                        $this->refinery->kindlyTo()->dictOf($this->refinery->kindlyTo()->string())
                    );
                }
                $data[$qid] = $value;
                $found_question++;
            }
            $i++;
        }
        return $data;
    }

    public function finishEvaluation(): void
    {
        $this->initPresentationForm();

        if ($this->form->checkInput()) {
            $this->dataset->updateValuesByPost($this->getDataFromPost());
            $this->dataset->setComplete(true);
            $this->dataset->update();
            $this->redirectToResults($this->dataset);
        }
        $this->form->setValuesByPost();
        $this->tpl->setContent($this->form->getHTML());
    }

    private function redirectToResults(Dataset $dataset): void
    {
        $this->ctrl->setParameterByClass('DatasetGUI', 'dataset_id', $dataset->getId());
        $this->ctrl->redirectByClass('DatasetGUI', 'show');
    }

    protected function initPresentationForm(string $mode = 'new')
    {
        $this->form = new PlayerFormContainer($this->tpl, $this->plugin);
        $this->form->setId('evaluation_form');

        $factory = new BlockFactory($this->db, $this->parent->object->getId());
        $blocks = $factory->getAllBlocks();

        if ($this->parent->object->getSortType() == ilObjSelfEvaluation::SHUFFLE_ACROSS_BLOCKS) {
            $blocks = $this->orderMixedBlocks($blocks);
        }

        if ($this->parent->object->getDisplayType() == ilObjSelfEvaluation::DISPLAY_TYPE_SINGLE_PAGE) {
            $this->displayAllBlocks($blocks, $mode);
        } else {
            $this->displaySingleBlock($blocks, $mode);
        }

        $this->form->addCommandButton('cancel', $this->plugin->txt('cancel'));
        $this->form->setFormAction($this->ctrl->getFormAction($this));
    }

    /**
     * @param Block[] $blocks
     */
    protected function orderMixedBlocks(array $blocks): array
    {
        $return_blocks = [];
        /**
         * ilSelfEvaluationVirtualQuestionBlock[]
         */
        $virtual_blocks = [];
        $questions = [];

        $meta_blocks_end_form = [];
        $meta_block_beginning = true;

        foreach ($blocks as $block) {
            if ($block instanceof MetaBlock) {
                if ($meta_block_beginning) {
                    $return_blocks[] = $block;
                } else {
                    $meta_blocks_end_form[] = $block;
                }
            } else {
                $meta_block_beginning = false;
                foreach (MatrixQuestion::_getAllInstancesForParentId($this->db, $block->getId()) as $question) {
                    $questions[] = $question;
                }
            }
        }

        //Order is just a completely random array same length as question. $val*123%13 is completely random, but will
        //alway return the same order.
        $order = array_map(fn($val): int => $val * 123 % 13, range(1, count($questions)));
        array_multisort($order, $questions);

        $questions_in_block = 0;
        $block_nr = 0;
        $virtual_blocks[0] = new VirtualQuestionBlock($this->parent->object->getId());
        $virtual_blocks[$block_nr]->setTitle($this->plugin->txt("mixed_block_title") . " " . (1));
        $virtual_blocks[$block_nr]->setDescription($this->parent->object->getBlockOptionRandomDesc());

        foreach ($questions as $question) {
            if ($questions_in_block >= $this->parent->object->getSortRandomNrItemBlock()) {
                $questions_in_block = 0;
                $block_nr++;
                $virtual_blocks[$block_nr] = new VirtualQuestionBlock($this->parent->object->getId());
                $virtual_blocks[$block_nr]->setTitle($this->plugin->txt("mixed_block_title") . " " . ($block_nr + 1));
                $virtual_blocks[$block_nr]->setDescription($this->parent->object->getBlockOptionRandomDesc());
            }
            $virtual_blocks[$block_nr]->addQuestion($question);
            $questions_in_block++;
        }

        foreach ($virtual_blocks as $virtual_block) {
            $return_blocks[] = $virtual_block;
        }

        foreach ($meta_blocks_end_form as $meta_block) {
            $return_blocks[] = $meta_block;
        }

        return $return_blocks;
    }

    protected function displaySingleBlock(array $blocks, string $mode = 'new')
    {
        $page = $this->http->query()->has('page') ? $this->http->query()->retrieve(
            'page',
            $this->refinery->kindlyTo()->int()
        ) : 1;
        $last_page = count($blocks);

        if ($last_page > 1) {
            $this->form->addKnob($page, $last_page);
        }
        $this->ctrl->setParameter($this, 'page', $page);
        if ($page < $last_page) {
            $this->form->addCommandButton('nextPage', $this->plugin->txt('next_' . $mode));
        } else {
            $this->form->addCommandButton("finishEvaluation", $this->plugin->txt('send_' . $mode));
        }
        if (array_key_exists($page - 1, $blocks)) {
            $this->addBlockHtmlToForm($blocks[$page - 1]);
        }
    }

    protected function displayAllBlocks($blocks, string $mode = 'new')
    {
        foreach ($blocks as $block) {
            $this->addBlockHtmlToForm($block);
        }
        $this->form->addCommandButton('finishEvaluation', $this->plugin->txt('send_' . $mode));
    }

    protected function addBlockHtmlToForm($block)
    {
        $gui_class = "";

        switch ($block::class) {
            case QuestionBlock::class:
            case VirtualQuestionBlock::class:
                $gui_class = QuestionBlockPlayerGUI::class;
                break;
            case MetaBlock::class:
                $gui_class = MetaBlockPlayerGUI::class;
                break;
        }
        /**
         * @var $block_gui BlockPlayerGUI
         */
        $block_gui = new $gui_class($this->db, $this->plugin, $this->parent, $block);
        $this->form = $block_gui->getBlockForm($this->form);
    }

    protected function fillForm()
    {
        $data = Data::_getAllInstancesByDatasetId($this->db, $this->dataset->getId());
        $values = [];
        foreach ($data as $question_data) {
            if ($question_data->getQuestionType() == Data::QUESTION_TYPE) {
                $values[MatrixQuestion::POSTVAR_PREFIX . $question_data->getQuestionId()] = $question_data->getValue();
            } else {
                $values[MetaQuestion::POSTVAR_PREFIX . $question_data->getQuestionId()] = $question_data->getValue();
            }
        }
        if (!empty($values)) {
            $this->form->setValuesByArray($values);
        }
    }
}
