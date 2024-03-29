<?php
/**
 * Created by diModelsManager
 * Date: 01.06.2016
 * Time: 23:03
 */

/**
 * Class diAdminTaskParticipantModel
 * Methods list for IDE
 *
 * @method integer	getAdminId
 * @method integer	getTaskId
 *
 * @method bool hasAdminId
 * @method bool hasTaskId
 *
 * @method diAdminTaskParticipantModel setAdminId($value)
 * @method diAdminTaskParticipantModel setTaskId($value)
 */
class diAdminTaskParticipantModel extends diModel
{
    protected $table = 'admin_task_participants';

    public function validate()
    {
        if (!$this->hasAdminId()) {
            $this->addValidationError('Admin required', 'admin_id');
        }

        if (!$this->hasTaskId()) {
            $this->addValidationError('Task required', 'task_id');
        }

        return parent::validate(); // TODO: Change the autogenerated stub
    }
}
