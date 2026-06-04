<?php

declare(strict_types=1);

class ilObjSelfEvaluationAccess extends ilObjectPluginAccess
{
    #[\Override]
    public function _checkAccess(string $cmd, string $permission, int $ref_id, int $obj_id, ?int $user_id = null): bool
    {
        if ($user_id == '') {
            $user_id = $this->user->getId();
        }

        switch ($permission) {
            case 'read':
            case 'visible':
                $object = new ilObjSelfEvaluation($ref_id);
                if (!$object->isOnline() && !$this->access->checkAccessOfUser($user_id, 'write', '', $ref_id)
                ) {
                    return false;
                }
                break;
        }
        return true;
    }
}
