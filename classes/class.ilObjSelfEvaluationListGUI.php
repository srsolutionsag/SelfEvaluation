<?php

declare(strict_types=1);

class ilObjSelfEvaluationListGUI extends ilObjectPluginListGUI
{
    /**
     * @var ?ilSelfEvaluationPlugin
     */
    protected ?ilRepositoryObjectPlugin $plugin = null;

    /**
     *
     */
    public function initType(): void
    {
        $this->enableTimings(false);
        $this->setType('xsev');
    }

    public function getGuiClass(): string
    {
        return 'ilObjSelfEvaluationGUI';
    }

    public function initCommands(): array
    {
        return [
            [
                'permission' => 'read',
                'cmd' => 'showContent',
                'default' => true
            ],
            [
                'permission' => 'write',
                'cmd' => 'editProperties',
                'txt' => $this->txt('edit'),
                'default' => false
            ],
        ];
    }

    #[\Override]
    public function getProperties(): array
    {
        $props = [];
        $object = new ilObjSelfEvaluation($this->ref_id);
        if (!$object->isOnline()) {
            $props[] = [
                'alert' => true,
                'property' => $this->txt('status'),
                'value' => $this->txt('offline')
            ];
        }

        return $props;
    }
}
