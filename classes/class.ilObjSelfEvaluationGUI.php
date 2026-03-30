<?php

declare(strict_types=1);

use ILIAS\DI\UIServices;
use ILIAS\Refinery\Factory;
use ilub\plugin\SelfEvaluation\Identity\Identity;
use ilub\plugin\SelfEvaluation\UIHelper\Scale\ScaleFormGUI;
use ilub\plugin\SelfEvaluation\UIHelper\TinyMceTextAreaInputGUI;
use ilub\plugin\SelfEvaluation\Block\Meta\MetaBlock;
use ilub\plugin\SelfEvaluation\Question\Matrix\Question;
use ilub\plugin\SelfEvaluation\Block\Matrix\QuestionBlock;
use ilub\plugin\SelfEvaluation\Question\Meta\MetaQuestion;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\HTTP\GlobalHttpState;

/**
 * Class ilObjSelfEvaluationGUI
 * @ilCtrl_isCalledBy ilObjSelfEvaluationGUI: ilRepositoryGUI, ilObjPluginDispatchGUI, ilAdministrationGUI
 * @ilCtrl_Calls      ilObjSelfEvaluationGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI, ilExportGUI
 * @ilCtrl_Calls      ilObjSelfEvaluationGUI: PlayerGUI, QuestionGUI, ListBlocksGUI
 * @ilCtrl_Calls      ilObjSelfEvaluationGUI: DatasetGUI, FeedbackGUI, QuestionBlockGUI, MetaBlockGUI
 * @ilCtrl_Calls      ilObjSelfEvaluationGUI: MetaQuestionGUI, IdentityGUI
 */
class ilObjSelfEvaluationGUI extends ilObjectPluginGUI
{
    public const DEV = false;
    public const DEBUG = false;
    public const RELOAD = false; // set to true or use the GET parameter rl=true to reload the plugin languages

    public const ORDER_QUESTIONS_STATIC = 1;
    public const ORDER_QUESTIONS_BLOCK_RANDOM = 2;
    public const ORDER_QUESTIONS_FULLY_RANDOM = 3;

    public const FIELD_ORDER_TYPE = 'block_presentation_type';
    public const FIELD_ORDER_FULLY_RANDOM = 'block_option_random';
    public const FIELD_ORDER_BLOCK = 'block_option_block';
    public const FIELD_ORDER_BLOCK_RANDOM = 'shuffle_in_blocks';
    private UIServices $ui;

    public ?ilObject $object = null;
    protected ilPropertyFormGUI $form;
    /**
     * @var ilSelfEvaluationPlugin|null
     */
    protected ?ilPlugin $plugin = null;
    protected ilDBInterface $db;
    public GlobalHttpState $http;
    public Factory $refinery;

    public function __construct(
        ?int $a_ref_id = 0,
        ?int $a_id_type = self::REPOSITORY_NODE_ID,
        ?int $a_parent_node_id = 0
    ) {
        global $DIC;
        $this->db = $DIC->database();
        $this->refinery = $DIC->refinery();
        $this->http = $DIC->http()->wrapper();
        $this->ui = $DIC->ui();
        parent::__construct($a_ref_id, $a_id_type, $a_parent_node_id);
    }

    public function displayIdentifier(): void
    {
        if ($this->http->query()->has('uid')) {
            $id = new Identity($this->db, $this->http->query()->retrieve('uid', $this->refinery->kindlyTo()->int()));
            if ($id->getType() == Identity::TYPE_EXTERNAL && $this->object->isIdentitySelection()) {
                global $ilToolbar;
                $ilToolbar->addText('<b>' . $this->txt('your_uid') . ' ' . $id->getIdentifier() . '</b>');
            }
        }
    }

    private function initAssets(): void {
        $this->tpl->addCss($this->getPlugin()->getStyleSheetLocation('css/content.css'));
        $this->tpl->addCss($this->getPlugin()->getStyleSheetLocation('css/print.css'), 'print');

        $is_in_survey = $this->ctrl->getCmd() === "showContent"
            || $this->ctrl->getCmd() === "show"
            || $this->ctrl->getNextClass($this) === "palyergui"; // Typo?

        $is_not_logged_in = $this->user->getLogin() === "anonymous";

        if ($is_in_survey && $is_not_logged_in) {
            $this->tpl->addCss($this->getPlugin()->getStyleSheetLocation('css/anonymous.css'));
        }
        $this->tpl->addJavaScript($this->getPlugin()->getRelativeDirectory() . '/templates/js/scripts.js');
    }

    private function initHeader(): void
    {
        $this->setTitleAndDescription();
        $this->displayIdentifier();
        $this->initAssets();
        $this->setLocator();
        $this->setTabs();
    }

    /**
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        if (!$this->getCreationMode()) {
            if ($this->access->checkAccess(
                'read',
                '',
                $this->http->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int())
            )) {
                $this->nav_history->addItem(
                    $this->http->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int()),
                    $this->ctrl->getLinkTarget($this, $this->getStandardCmd()),
                    $this->getType()
                );
            }

            $next_class = $this->ctrl->getNextClass($this);

            $this->ctrl->saveParameterByClass('PlayerGUI', 'uid');
            $this->ctrl->saveParameterByClass('DatasetGUI', 'uid');
            $this->ctrl->saveParameterByClass('FeedbackGUI', 'uid');

            // Try to determine the block_id and request_id from POST or GET
            $block_id = $this->http->query()->has('block_id')
                ? $this->http->query()->retrieve('block_id', $this->refinery->kindlyTo()->int())
                : null;

            if ($block_id === null) {
                $block_id = $this->http->post()->has('block_id')
                    ? $this->http->post()->retrieve('block_id', $this->refinery->kindlyTo()->int())
                    : null;
            }

            $block_id ??= 0;

            $request_id = $this->http->query()->has('question_id')
                ? $this->http->query()->retrieve('question_id', $this->refinery->kindlyTo()->int())
                : null;

            if ($request_id === null) {
                $request_id = $this->http->post()->has('question_id')
                    ? $this->http->post()->retrieve('question_id', $this->refinery->kindlyTo()->int())
                    : null;
            }

            $request_id ??= 0;

            switch ($next_class) {
                case 'ilcommonactiondispatchergui':
                    $gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
                    $this->ctrl->forwardCommand($gui);
                    $this->tpl->printToStdout();
                    break;
                case 'listblocksgui':
                    $this->initHeader();
                    $gui = new ListBlocksGUI(
                        $this->db,
                        $this,
                        $this->tpl,
                        $this->ctrl,
                        $this->toolbar,
                        $this->access,
                        $this->plugin,
                        $this->http,
                        $this->refinery,
                        $this->ui
                    );
                    $this->tabs->activateTab('administration');
                    $this->ctrl->forwardCommand($gui);
                    $this->tpl->printToStdout();
                    break;
                case 'questionblockgui':
                    $this->initHeader();
                    $block_gui = new QuestionBlockGUI(
                        $this->db,
                        $this->tpl,
                        $this->ctrl,
                        $this->access,
                        $this->plugin,
                        $this
                    );
                    $this->tabs->activateTab('administration');
                    $this->ctrl->forwardCommand($block_gui);
                    $this->tpl->printToStdout();
                    break;
                case 'metablockgui':
                    $this->initHeader();
                    $block_gui = new MetaBlockGUI(
                        $this->db,
                        $this->tpl,
                        $this->ctrl,
                        $this->access,
                        $this->plugin,
                        $this
                    );
                    $this->tabs->activateTab('administration');
                    $this->ctrl->forwardCommand($block_gui);
                    $this->tpl->printToStdout();
                    break;
                case 'questiongui':
                    $this->initHeader();
                    $this->tabs->activateTab('administration');
                    $block = new QuestionBlock($this->db, $block_id);
                    $question = new Question($this->db, $request_id);
                    $container_gui = new QuestionGUI(
                        $this->db,
                        $this,
                        $this->tpl,
                        $this->ctrl,
                        $this->toolbar,
                        $this->access,
                        $this->ui,
                        $this->plugin,
                        $block,
                        $question
                    );
                    $this->ctrl->forwardCommand($container_gui);
                    $this->tpl->printToStdout();
                    break;
                case 'metaquestiongui':
                    $this->initHeader();
                    $this->tabs->activateTab('administration');

                    $block = new MetaBlock($this->db, $block_id);
                    $question = new MetaQuestion($this->db, $request_id);
                    $container_gui = new MetaQuestionGUI(
                        $this->db,
                        $this,
                        $this->tpl,
                        $this->ctrl,
                        $this->toolbar,
                        $this->access,
                        $this->ui,
                        $this->plugin,
                        $block,
                        $question
                    );
                    $this->ctrl->forwardCommand($container_gui);
                    $this->tpl->printToStdout();
                    break;
                case 'feedbackgui':
                    $this->initHeader();
                    $gui = new FeedbackGUI(
                        $this->db,
                        $this,
                        $this->tpl,
                        $this->ctrl,
                        $this->toolbar,
                        $this->access,
                        $this->http,
                        $this->refinery,
                        $this->plugin,
                        $this->ui
                    );
                    $this->tabs->activateTab('administration');
                    $this->ctrl->forwardCommand($gui);
                    $this->tpl->printToStdout();
                    break;
                case 'identitygui':
                    $this->initHeader();
                    $gui = new IdentityGUI($this->db, $this, $this->tpl, $this->ctrl, $this->plugin);
                    $this->tabs->activateTab('content');
                    $this->ctrl->forwardCommand($gui);
                    $this->tpl->printToStdout();
                    break;

                case 'playergui':
                    $this->initHeader();
                    $this->tabs_gui->activateTab('content');
                    $gui = new PlayerGUI(
                        $this->db,
                        $this,
                        $this->tpl,
                        $this->ctrl,
                        $this->plugin,
                        $this->http,
                        $this->refinery
                    );
                    $this->ctrl->forwardCommand($gui);
                    $this->tpl->printToStdout();
                    break;
                case 'datasetgui':
                    $this->initHeader();
                    $this->tabs_gui->activateTab('all_results');
                    $gui = new DatasetGUI(
                        $this->db,
                        $this,
                        $this->tpl,
                        $this->ctrl,
                        $this->toolbar,
                        $this->access,
                        $this->plugin,
                        $this->http,
                        $this->refinery,
                        $this->ui
                    );
                    $this->ctrl->forwardCommand($gui);
                    $this->tpl->printToStdout();
                    break;
                case '':
                default:
                    $this->setTitleAndDescription();
                    $this->initAssets();
                    parent::executeCommand();
                    break;
            }
        } else {
            parent::executeCommand();
        }
    }

    final public function getType(): string
    {
        return 'xsev';
    }

    public function performCommand(string $cmd): void
    {
        if ($cmd === '') {
            $cmd = $this->ctrl->getCmd();
        }
        switch ($cmd) {
            case 'editProperties':
            case 'updateProperties':
                $this->checkPermission('write');
                $this->$cmd();
                break;

            case 'showContent':
            default:
                $this->checkPermission('read');
                $this->showContent();
                break;
        }
    }

    public function getAfterCreationCmd(): string
    {
        return 'editProperties';
    }

    public function getStandardCmd(): string
    {
        return 'showContent';
    }

    public function setTabs(): void
    {
        global $DIC;

        if ($DIC->access()->checkAccess('read', '', $this->object->getRefId())) {
            $this->tabs->addTab('content', $this->txt('content'), $this->ctrl->getLinkTarget($this, 'showContent'));
        }
        $this->addInfoTab();
        if ($DIC->access()->checkAccess('write', '', $this->object->getRefId())) {
            $this->tabs->addTab(
                'properties',
                $this->txt('properties'),
                $this->ctrl->getLinkTarget($this, 'editProperties')
            );
            $this->tabs->addTab(
                'administration',
                $this->txt('administration'),
                $this->ctrl->getLinkTargetByClass('ListBlocksGUI', 'showContent')
            );
        }
        if ($this->object->isAllowShowResults() && !$DIC->user()->isAnonymous()) {
            $this->tabs->addTab(
                'all_results',
                $this->txt('show_results'),
                $this->ctrl->getLinkTargetByClass('DatasetGUI', 'index')
            );
        }

        if ($this->access->checkAccess('write', "", $this->object->getRefId())) {
            $this->addExportTab();
        }

        $this->addPermissionTab();
    }

    public function editProperties(): void
    {
        if ($this->object->hasDatasets()) {
            $this->tpl->setOnScreenMessage(
                ilGlobalTemplateInterface::MESSAGE_TYPE_INFO,
                $this->txt('scale_cannot_be_edited')
            );
        }
        $this->tabs->activateTab('properties');
        $this->initPropertiesForm();
        $this->getPropertiesValues();
        $this->tpl->setContent($this->form->getHTML());
    }

    public function initPropertiesForm(): void
    {
        $this->form = new ilPropertyFormGUI();
        // title
        $ti = new ilTextInputGUI($this->txt('title'), 'title');
        $ti->setRequired(true);
        $this->form->addItem($ti);
        // description
        $ta = new ilTextAreaInputGUI($this->txt('description'), 'desc');
        $this->form->addItem($ta);
        // online
        $cb = new ilCheckboxInputGUI($this->txt('online'), 'online');
        $this->form->addItem($cb);
        // online
        $cb = new ilCheckboxInputGUI($this->txt('identity_selection'), 'identity_selection');
        $cb->setInfo($this->txt('identity_selection_info'));
        $this->form->addItem($cb);

        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->txt('help_text_section'));
        $this->form->addItem($section);

        //////////////////////////////
        /////////Text Section////////
        //////////////////////////////
        // intro
        $te = new TinyMceTextAreaInputGUI(
            $this->object->getRefId(),
            $this->plugin->getId(),
            $this->txt('intro'),
            'intro'
        );
        $te->setInfo($this->txt('intro_info'));
        $this->form->addItem($te);
        // outro
        $te = new ilTextInputGUI($this->txt('outro_title'), 'outro_title');
        $te->setInfo($this->txt('outro_title_info'));
        $this->form->addItem($te);

        $te = new TinyMceTextAreaInputGUI(
            $this->object->getRefId(),
            $this->plugin->getId(),
            $this->txt('outro'),
            'outro'
        );
        $te->setInfo($this->txt('outro_info'));
        $this->form->addItem($te);
        // identity selection info text for anonymous users
        $te = new TinyMceTextAreaInputGUI(
            $this->object->getRefId(),
            $this->plugin->getId(),
            $this->txt('identity_selection_text'),
            'identity_selection_info'
        );
        // $te->setRTESupport($this->object->getId(), $this->object->getType(), '', NULL, FALSE, '3.4.7');
        $te->setInfo($this->txt('identity_selection_text_info'));
        $this->form->addItem($te);

        //////////////////////////////
        /////////Block Section////////
        //////////////////////////////
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->txt('block_section'));
        $this->form->addItem($section);

        //Ordering of Questions in Blocks
        $radio_options = new ilRadioGroupInputGUI(
            $this->txt(self::FIELD_ORDER_TYPE),
            self::FIELD_ORDER_TYPE
        );

        $option_random = new ilRadioOption(
            $this->txt(self::FIELD_ORDER_FULLY_RANDOM),
            self::FIELD_ORDER_FULLY_RANDOM
        );
        $option_random->setInfo($this->txt("block_option_random_info"));

        $nr_input = new ilNumberInputGUI(
            $this->txt("sort_random_nr_items_block"),
            'sort_random_nr_items_block'
        );
        $option_random->addSubItem($nr_input);

        $te = new TinyMceTextAreaInputGUI(
            $this->object->getRefId(),
            $this->plugin->getId(),
            $this->txt('block_option_random_desc'),
            'block_option_random_desc'
        );
        $te->setInfo($this->txt('block_option_random_desc_info'));
        $option_random->addSubItem($te);

        $option_block = new ilRadioOption(
            $this->txt(self::FIELD_ORDER_BLOCK),
            self::FIELD_ORDER_BLOCK
        );
        $option_block->setInfo($this->txt("block_option_block_info"));

        $cb = new ilCheckboxInputGUI(
            $this->txt(self::FIELD_ORDER_BLOCK_RANDOM),
            self::FIELD_ORDER_BLOCK_RANDOM
        );
        $option_block->addSubItem($cb);

        $radio_options->addOption($option_random);
        $radio_options->addOption($option_block);
        $radio_options->setRequired(true);
        $this->form->addItem($radio_options);

        // DisplayType
        $se = new ilSelectInputGUI($this->txt('display_type'), 'display_type');
        $se->setInfo($this->txt("display_type_info"));
        $opt = [
            ilObjSelfEvaluation::DISPLAY_TYPE_SINGLE_PAGE => $this->txt('single_page'),
            ilObjSelfEvaluation::DISPLAY_TYPE_MULTIPLE_PAGES => $this->txt('multiple_pages'),
        ];
        $se->setOptions($opt);
        $this->form->addItem($se);

        // Show question block titles during evaluation
        $cb = new ilCheckboxInputGUI($this->txt('show_block_titles_sev'), 'show_block_titles_sev');
        $this->form->addItem($cb);

        // Show question block descriptions during evaluation
        $cb = new ilCheckboxInputGUI($this->txt('show_block_desc_sev'), 'show_block_desc_sev');
        $this->form->addItem($cb);

        //////////////////////////////
        /////////Feedback Section/////
        //////////////////////////////
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->txt('feedback_section'));
        $this->form->addItem($section);

        // Show Feedbacks overview graphics
        $cb = new ilCheckboxInputGUI($this->txt('show_fbs_overview'), 'show_fbs_overview');
        $cb->setValue('1');

        $sub_cb_1 = new ilCheckboxInputGUI($this->txt('show_bar'), 'show_fbs_overview_bar');
        $sub_cb_1->setValue('1');

        $sub_sub_cb_1 = new ilCheckboxInputGUI(
            $this->txt('overview_bar_show_label_as_percentage'),
            'overview_bar_show_label_as_percentage'
        );
        $sub_sub_cb_1->setValue('1');
        $sub_cb_1->addSubItem($sub_sub_cb_1);

        $sub_cb_2 = new ilCheckboxInputGUI($this->txt('show_spider'), 'show_fbs_overview_spider');
        $sub_cb_2->setValue('1');

        $sub_cb_3 = new ilCheckboxInputGUI(
            $this->txt('show_left_right'),
            'show_fbs_overview_left_right'
        );
        $sub_cb_3->setValue('1');

        $cb->addSubItem($sub_cb_1);
        $cb->addSubItem($sub_cb_2);
        $cb->addSubItem($sub_cb_3);

        // Show Feedbacks overview text
        $sub_cb_4 = new ilCheckboxInputGUI(
            $this->txt('show_fbs_overview_text'),
            'show_fbs_overview_text'
        );
        $sub_cb_4->setInfo($this->txt('show_fbs_overview_text_info'));
        $sub_cb_4->setValue('1');
        $cb->addSubItem($sub_cb_4);

        // Show Feedbacks overview statistics
        $sub_cb_5 = new ilCheckboxInputGUI(
            $this->txt('show_fbs_overview_statistics'),
            'show_fbs_overview_statistics'
        );
        $sub_cb_5->setInfo($this->txt('show_fbs_overview_statistics_info'));
        $sub_cb_5->setValue('1');
        $cb->addSubItem($sub_cb_5);

        $this->form->addItem($cb);

        // Show question block titles during feedback
        $cb = new ilCheckboxInputGUI($this->txt('show_block_titles_fb'), 'show_block_titles_fb');
        $this->form->addItem($cb);

        // Show question block descriptions during feedback
        $cb = new ilCheckboxInputGUI($this->txt('show_block_desc_fb'), 'show_block_desc_fb');
        $this->form->addItem($cb);
        //
        $cb = new ilCheckboxInputGUI($this->txt('show_fbs'), 'show_fbs');
        $cb->setValue('1');
        $this->form->addItem($cb);
        //
        $cb = new ilCheckboxInputGUI($this->txt('show_fbs_charts'), 'show_fbs_charts');
        $cb->setValue('1');

        $sub_cb_1 = new ilCheckboxInputGUI($this->txt('show_bar'), 'show_fbs_chart_bar');
        $sub_cb_1->setValue('1');

        $sub_cb_2 = new ilCheckboxInputGUI($this->txt('show_spider'), 'show_fbs_chart_spider');
        $sub_cb_2->setValue('1');

        $sub_cb_3 = new ilCheckboxInputGUI(
            $this->txt('show_left_right'),
            'show_fbs_chart_left_right'
        );
        $sub_cb_3->setValue('1');

        $cb->addSubItem($sub_cb_1);
        $cb->addSubItem($sub_cb_2);
        $cb->addSubItem($sub_cb_3);

        $this->form->addItem($cb);

        //////////////////////////////
        /////////Scale Section////////
        //////////////////////////////
        // Append
        $aform = new ScaleFormGUI(
            $this->db,
            $this->tpl,
            $this->plugin,
            $this->object->getId(),
            $this->object->hasDatasets()
        );
        $this->form = $aform->appendToForm($this->form);

        // Buttons
        $this->form->addCommandButton('updateProperties', $this->txt('save'));
        $this->form->setTitle($this->txt('edit_properties'));
        $this->form->setFormAction($this->ctrl->getFormAction($this));
    }

    public function getPropertiesValues(): void
    {
        $aform = new ScaleFormGUI(
            $this->db,
            $this->tpl,
            $this->plugin,
            $this->object->getId(),
            $this->object->hasDatasets()
        );
        $values = $aform->fillForm();
        $values['title'] = $this->object->getTitle();
        $values['desc'] = $this->object->getDescription();
        $values['online'] = $this->object->isOnline();
        $values['identity_selection'] = $this->object->isIdentitySelection();

        $values['intro'] = $this->object->getIntro();

        if ($this->object->getSortType() == self::ORDER_QUESTIONS_FULLY_RANDOM) {
            $values[self::FIELD_ORDER_TYPE] = self::FIELD_ORDER_FULLY_RANDOM;
        } else {
            $values[self::FIELD_ORDER_TYPE] = self::FIELD_ORDER_BLOCK;
            if ($this->object->getSortType() == self::ORDER_QUESTIONS_BLOCK_RANDOM) {
                $values[self::FIELD_ORDER_BLOCK_RANDOM] = 1;
            }
        }
        $values['sort_random_nr_items_block'] = $this->object->getSortRandomNrItemBlock();
        $values['block_option_random_desc'] = $this->object->getBlockOptionRandomDesc();
        $values['outro_title'] = $this->object->getOutroTitle();
        $values['outro'] = $this->object->getOutro();
        $values['identity_selection_info'] = $this->object->getIdentitySelectionInfoText();
        $values['display_type'] = $this->object->getDisplayType();
        $values['show_fbs_overview'] = $this->object->isShowFeedbacksOverview();
        $values['show_fbs_overview_text'] = $this->object->isShowFbsOverviewText();
        $values['show_fbs_overview_statistics'] = $this->object->isShowFbsOverviewStatistics();

        $values['show_fbs'] = $this->object->isShowFeedbacks();
        $values['show_fbs_charts'] = $this->object->isShowFeedbacksCharts();
        $values['show_block_titles_sev'] = $this->object->isShowBlockTitlesDuringEvaluation();
        $values['show_block_desc_sev'] = $this->object->isShowBlockDescriptionsDuringEvaluation();
        $values['show_block_titles_fb'] = $this->object->isShowBlockTitlesDuringFeedback();
        $values['show_block_desc_fb'] = $this->object->isShowBlockDescriptionsDuringFeedback();

        $values['show_fbs_overview_bar'] = $this->object->isShowFbsOverviewBar();
        $values['overview_bar_show_label_as_percentage'] = $this->object->isOverviewBarShowLabelAsPercentage();
        $values['show_fbs_overview_spider'] = $this->object->isShowFbsOverviewSpider();
        $values['show_fbs_overview_left_right'] = $this->object->isShowFbsOverviewLeftRight();

        $values['show_fbs_chart_bar'] = $this->object->isShowFbsChartBar();
        $values['show_fbs_chart_spider'] = $this->object->isShowFbsChartSpider();
        $values['show_fbs_chart_left_right'] = $this->object->isShowFbsChartLeftRight();

        $this->form->setValuesByArray($values);
    }

    public function updateProperties(): void
    {
        $this->initPropertiesForm();
        $this->form->setValuesByPost();
        if ($this->form->checkInput()) {
            // Append
            $aform = new ScaleFormGUI(
                $this->db,
                $this->tpl,
                $this->plugin,
                $this->object->getId(),
                $this->object->hasDatasets()
            );
            $aform->updateObject();

            $this->object->setTitle($this->form->getInput('title'));
            $this->object->setDescription($this->form->getInput('desc'));
            $this->object->setOnline((bool) $this->form->getInput('online'));
            $this->object->setIdentitySelection((bool) $this->form->getInput('identity_selection'));
            $this->object->setIntro($this->form->getInput('intro'));
            $this->object->setOutroTitle($this->form->getInput('outro_title'));
            $this->object->setOutro($this->form->getInput('outro'));
            $this->object->setIdentitySelectionInfoText($this->form->getInput('identity_selection_info'));

            if ($this->form->getInput(self::FIELD_ORDER_TYPE) == self::FIELD_ORDER_FULLY_RANDOM) {
                $this->object->setSortType(self::ORDER_QUESTIONS_FULLY_RANDOM);
            } elseif ($this->form->getInput(self::FIELD_ORDER_BLOCK_RANDOM)) {
                $this->object->setSortType(self::ORDER_QUESTIONS_BLOCK_RANDOM);
            } else {
                $this->object->setSortType(self::ORDER_QUESTIONS_STATIC);
            }

            $this->object->setSortRandomNrItemBlock($this->form->getInput('sort_random_nr_items_block'));
            $this->object->setBlockOptionRandomDesc($this->form->getInput('block_option_random_desc'));
            $this->object->setShowBlockTitlesDuringEvaluation((bool) $this->form->getInput('show_block_titles_sev'));
            $this->object->setShowBlockDescriptionsDuringEvaluation(
                (bool) $this->form->getInput('show_block_desc_sev')
            );

            $this->object->setDisplayType((int) $this->form->getInput('display_type'));

            $this->object->setShowFeedbacksOverview((bool) $this->form->getInput('show_fbs_overview'));
            $this->object->setShowFbsOverviewText((bool) $this->form->getInput('show_fbs_overview_text'));
            $this->object->setShowFbsOverviewStatistics((bool) $this->form->getInput('show_fbs_overview_statistics'));

            $this->object->setShowFeedbacks((bool) $this->form->getInput('show_fbs'));
            $this->object->setShowFeedbacksCharts((bool) $this->form->getInput('show_fbs_charts'));
            $this->object->setShowBlockTitlesDuringFeedback((bool) $this->form->getInput('show_block_titles_fb'));
            $this->object->setShowBlockDescriptionsDuringFeedback((bool) $this->form->getInput('show_block_desc_fb'));

            $this->object->setShowFbsOverviewBar((bool) $this->form->getInput('show_fbs_overview_bar'));
            $this->object->setOverviewBarShowLabelAsPercentage(
                (bool) $this->form->getInput('overview_bar_show_label_as_percentage')
            );
            $this->object->setShowFbsOverviewSpider((bool) $this->form->getInput('show_fbs_overview_spider'));
            $this->object->setShowFbsOverviewLeftRight((bool) $this->form->getInput('show_fbs_overview_left_right'));

            $this->object->setShowFbsChartBar((bool) $this->form->getInput('show_fbs_chart_bar'));
            $this->object->setShowFbsChartSpider((bool) $this->form->getInput('show_fbs_chart_spider'));
            $this->object->setShowFbsChartLeftRight((bool) $this->form->getInput('show_fbs_chart_left_right'));

            $this->object->update();
            $this->tpl->setOnScreenMessage(
                ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
                $this->txt('msg_obj_modified'),
                true
            );
            $this->ctrl->redirect($this, 'editProperties');
        }
        $this->tabs->activateTab('properties');
        $this->form->setValuesByPost();
        $this->tpl->setContent($this->form->getHTML());
    }

    public function getObjId(): int
    {
        return $this->getObject()->getId();
    }
    //
    // Show content
    //
    public function showContent(): void
    {
        global $DIC;
        if ($DIC->user()->isAnonymous()) {
            $this->tabs->activateTab('content');
            $this->ctrl->redirectByClass('IdentityGUI', 'show');
        } else {
            $id = Identity::_getInstanceForObjIdAndIdentifier(
                $this->db,
                $this->object->getId(),
                (string) $DIC->user()->getId()
            );
            $this->ctrl->setParameterByClass('PlayerGUI', 'uid', $id->getId());
            $this->ctrl->redirectByClass('PlayerGUI', 'startScreen');
        }
    }
}
