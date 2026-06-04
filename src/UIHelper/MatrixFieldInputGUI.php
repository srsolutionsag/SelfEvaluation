<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\UIHelper;

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilSubEnabledFormPropertyGUI;
use ilRepositoryObjectPlugin;
use ilTemplate;
use ILIAS\Refinery\ConstraintViolationException;

class MatrixFieldInputGUI extends ilSubEnabledFormPropertyGUI
{
    protected string $value = "";
    protected array $values;
    protected array $scale = [];
    private Factory $ui_factory;
    private Renderer $ui_renderer;

    public function __construct(
        protected ilRepositoryObjectPlugin $plugin,
        string $a_title = '',
        string $a_postvar = ''
    ) {
        global $DIC;
        parent::__construct($a_title, $a_postvar);
        $this->setType('matrix_field');
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();
    }

    public function getHtml(): string
    {
        return $this->buildHTML();
    }

    private function buildHTML(): string
    {
        $tpl = $this->plugin->getTemplate('default/Matrix/tpl.matrix_input.html');

        $even = false;
        $tpl->setVariable('ROW_NAME', $this->getPostVar());
        foreach (array_keys($this->getScale()) as $value) {
            $tpl->setCurrentBlock('item');
            if ($this->getValue() == $value && $this->getValue() !== null && $this->getValue() !== '') {
                $tpl->setVariable('SELECTED', 'checked="checked"');
            }
            $tpl->setVariable('CLASS', $even ? "ilUnitEven" : "ilUnitOdd");
            $even = !$even;
            $tpl->setVariable('VALUE', $value);
            $tpl->setVariable('NAME', $this->getPostVar());
            $tpl->parseCurrentBlock();
        }

        return $tpl->get();
    }

    public function insert(ilTemplate $a_tpl): void
    {
        $a_tpl->setCurrentBlock('prop_custom');
        $a_tpl->setVariable('CUSTOM_CONTENT', $this->getHtml());
        $a_tpl->parseCurrentBlock();
    }

    public function setValueByArray(array $values): void
    {
        $matrix_key = "";
        $question_key = "";

        if (array_key_exists($this->getPostVar(), $values)) {
            $this->setValue($values[$this->getPostVar()]);
            return;
        }
        try {
            [$matrix_key, $question_key] = explode("[", str_replace("]", "", $this->getPostVar()));
        } catch (\Exception) {
        }

        if (array_key_exists($matrix_key, $values)) {
            $meta_question_values = $values[$matrix_key];
            if (array_key_exists($question_key, $meta_question_values)) {
                $this->setValue($meta_question_values[$question_key]);
            }
        }
    }

    public function setScale(array $scale): void
    {
        $this->scale = $scale;
    }

    public function getScale(): array
    {
        return $this->scale;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValues(array $values): void
    {
        $this->values = $values;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    #[\Override]
    public function checkInput(): bool
    {
        if ($this->getRequired()) {
            $post_var_parts = explode("[", str_replace("]", "", $this->getPostVar()));
            if (!$this->http->wrapper()->post()->has($post_var_parts[0])) {
                $this->setAlert($this->plugin->txt('msg_input_is_required'));
                return false;
            }
            try {
                $value = $this->http->wrapper()->post()->retrieve(
                    $post_var_parts[0],
                    $this->refinery->kindlyTo()->string()
                );
            } catch (ConstraintViolationException) {
                $value = $this->http->wrapper()->post()->retrieve(
                    $post_var_parts[0],
                    $this->refinery->kindlyTo()->dictOf($this->refinery->kindlyTo()->string())
                );
            }
            if (is_array($value)) {
                if (!array_key_exists($post_var_parts[1], $value)) {
                    $this->setAlert($this->plugin->txt('msg_input_is_required'));
                    return false;
                }
            } elseif (trim((string) $value) === '') {
                $this->setAlert($this->plugin->txt('msg_input_is_required'));
                return false;
            }
        }
        return true;
    }

    #[\Override]
    public function getAlert(): string
    {
        $alert_text = parent::getAlert();

        if ($alert_text !== '') {
            // prepend alert icon
            return $this->ui_renderer->render(
                $this->ui_factory->symbol()->icon()->custom(
                    'assets/images/standard/icon_alert.svg',
                    $this->lng->txt('alert'),
                    'medium'
                )
            )
                . $alert_text;
        }

        return $alert_text;
    }

}
