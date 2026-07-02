<?php

declare(strict_types=1);

use ilub\plugin\SelfEvaluation\Block\BlockGUI;
use ilub\plugin\SelfEvaluation\Block\Meta\MetaBlock;
use ilub\plugin\SelfEvaluation\Block\Matrix\QuestionBlock;

class MetaBlockGUI extends BlockGUI
{
    protected QuestionBlock|MetaBlock $object;

    public function __construct(
        ilDBInterface $db,
        ilGlobalTemplateInterface $tpl,
        ilCtrl $ilCtrl,
        ilAccessHandler $access,
        ilSelfEvaluationPlugin $plugin,
        ilObjSelfEvaluationGUI $parent
    ) {
        parent::__construct($db, $tpl, $ilCtrl, $access, $plugin, $parent);
        if ($parent->http->wrapper()->query()->has('block_id')) {
            $this->object = new MetaBlock(
                $this->db,
                $parent->http->wrapper()->query()->retrieve('block_id', $parent->refinery->kindlyTo()->int())
            );
        } else {
            $this->object = new MetaBlock($this->db);
        }
        $this->object->setParentId($this->parent->getObjId());
    }
}
