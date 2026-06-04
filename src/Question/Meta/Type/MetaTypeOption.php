<?php

declare(strict_types=1);

namespace ilub\plugin\SelfEvaluation\Question\Meta\Type;

use ilRadioOption;

class MetaTypeOption extends ilRadioOption
{
    public function __construct(string $info = '')
    {
        parent::__construct('', '', $info);
    }

    #[\Override]
    public function setDisabled(bool $a_disabled): void
    {
        $this->disabled = $a_disabled;

        foreach ($this->getSubItems() as $sub_item) {
            $this->disable($sub_item, $a_disabled);
        }
    }

    protected function disable($item, bool $disabled)
    {
        if (method_exists($item, 'getSubItems')) {
            foreach ($item->getSubItems() as $sub_item) {
                $this->disable($sub_item, $disabled);
            }
        }

        if (method_exists($item, 'setDisabled')) {
            $item->setDisabled($disabled);
        }
    }

}
