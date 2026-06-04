<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\UIHelper\Scale;

use ilPropertyFormGUI;
use ilGlobalTemplateInterface;
use ilRepositoryObjectPlugin;
use ilDBInterface;
use ilFormSectionHeaderGUI;

/**
 * @ilCtrl_Calls      ilSelfEvaluationScaleGUI: ilObjSelfEvaluationGUI
 * @ilCtrl_IsCalledBy ilSelfEvaluationScaleGUI: ilCommonActionDispatcherGUI, ilObjSelfEvaluationGUI
 */
class ScaleFormGUI extends ilPropertyFormGUI
{
    public const FIELD_NAME = 'scale';

    protected Scale $scale;

    public function __construct(
        protected ilDBInterface $db,
        protected ilGlobalTemplateInterface $tmpl,
        protected ilRepositoryObjectPlugin $plugin,
        protected int $parent_id,
        protected bool $locked = false
    ) {
        parent::__construct();

        $this->scale = Scale::_getInstanceByObjId($this->db, $this->parent_id);
        $this->initForm();
        $this->tmpl->addJavaScript($this->plugin->getRelativeDirectory() . '/templates/js/sortable.js');
    }

    protected function initForm()
    {
        // Header
        $te = new ilFormSectionHeaderGUI();
        $te->setTitle($this->plugin->txt('scale_form'));
        $this->addItem($te);
        $te = new MultipleFieldInputGUI($this->plugin, $this->plugin->txt('scale'), 'scale', self::FIELD_NAME);
        $te->setPlaceholderValue($this->plugin->txt('multinput_value'));
        $te->setPlaceholderTitle($this->plugin->txt('multinput_title'));
        $te->setDescription($this->plugin->txt('multinput_description'));
        $te->setDisabled($this->locked);
        $this->addItem($te);
        // FillForm
        $this->fillForm();
    }

    public function fillForm(): array
    {
        $array = [];
        foreach ($this->scale->getUnits() as $unit) {
            /**
             * @var $unit ScaleUnit
             */
            $array[$unit->getId()] = ['title' => $unit->getTitle(), 'value' => $unit->getValue()];
        }
        $array = [
            'scale' => $array,
        ];
        $this->setValuesByArray($array);

        return $array;
    }

    public function appendToForm(ilPropertyFormGUI $form_gui): ilPropertyFormGUI
    {
        foreach ($this->getItems() as $item) {
            $form_gui->addItem($item);
        }

        return $form_gui;
    }

    public function updateObject(): void
    {
        $positions = [];

        $this->scale->update();
        if ($this->http->wrapper()->post()->has(self::FIELD_NAME . '_new') && !is_array(
            $this->http->wrapper()->post()->retrieve(
                self::FIELD_NAME . '_new',
                $this->refinery->kindlyTo()->listOf(
                    $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->string())
                )
            )
        )) {
            return;
        }
        if ($this->http->wrapper()->post()->has(self::FIELD_NAME . '_position') && is_array(
            $this->getArrayFromPost(self::FIELD_NAME . '_position')
        )) {
            $positions = array_flip($this->getArrayFromPost(self::FIELD_NAME . '_position'));
        }

        $new = $this->getArrayFromPostComplex(self::FIELD_NAME . '_new');
        if (!is_null($new) && is_array($new['value'])) {
            foreach ($new['value'] as $k => $v) {
                if (!in_array($v, [false, null, ''], true)) {
                    $obj = new ScaleUnit($this->db);
                    $obj->setParentId($this->scale->getId());
                    $obj->setTitle($new['title'][$k]);
                    $obj->setValue((int) $v);
                    $obj->create();
                }
            }
        }

        if ($this->http->wrapper()->post()->has(self::FIELD_NAME . '_old')) {
            $old = $this->getArrayFromPostComplex(self::FIELD_NAME . '_old');
            if (is_array($old['value'])) {
                foreach ($old['value'] as $k => $v) {
                    $obj = new ScaleUnit($this->db, (int) str_replace('id_', '', (string) $k));
                    if (!in_array($v, [false, null, ''], true)) {
                        $obj->setTitle($old['title'][$k]);
                        $obj->setValue((int) $v);
                        $obj->setPosition((int) $positions[str_replace('id_', '', (string) $k)]);
                        $obj->update();
                    } else {
                        $obj->delete();
                    }
                }
            }
        }
    }

    public function getArrayFromPost(string $string): ?array
    {
        try {
            return $this->http->wrapper()->post()->retrieve(
                $string,
                $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->string())
            );
        } catch (\Exception) {
            return null;
        }
    }

    public function getArrayFromPostComplex(string $string): ?array
    {
        try {
            return $this->http->wrapper()->post()->retrieve(
                $string,
                $this->refinery->kindlyTo()->dictOf(
                    $this->refinery->kindlyTo()->dictOf($this->refinery->kindlyTo()->string())
                )
            );
        } catch (\Exception) {
            return null;
        }
    }
}
