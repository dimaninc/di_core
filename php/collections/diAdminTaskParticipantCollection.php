<?php
/**
 * Created by diModelsManager
 * Date: 01.06.2016
 * Time: 23:03
 */

/**
 * Class diAdminTaskParticipantCollection
 * Methods list for IDE
 *
 * @method diAdminTaskParticipantCollection filterByAdminId($value, $operator = null)
 * @method diAdminTaskParticipantCollection filterByTaskId($value, $operator = null)
 *
 * @method diAdminTaskParticipantCollection orderByAdminId($direction = null)
 * @method diAdminTaskParticipantCollection orderByTaskId($direction = null)
 *
 * @method diAdminTaskParticipantCollection selectAdminId()
 * @method diAdminTaskParticipantCollection selectTaskId()
 */
class diAdminTaskParticipantCollection extends diCollection
{
    protected $table = 'admin_task_participants';
    protected $modelType = 'admin_task_participant';
}
