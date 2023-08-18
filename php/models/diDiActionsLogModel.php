<?php
/**
 * Created by diModelsManager
 * Date: 15.07.2015
 * Time: 17:30
 */
/**
 * Class diDiActionsLogModel
 * Methods list for IDE
 *
 * @method string	getTargetType
 * @method integer	getTargetId
 * @method integer	getUserType
 * @method integer	getUserId
 * @method string	getAction
 * @method string	getInfo
 * @method string	getDate
 *
 * @method bool hasTargetType
 * @method bool hasTargetId
 * @method bool hasUserType
 * @method bool hasUserId
 * @method bool hasAction
 * @method bool hasInfo
 * @method bool hasDate
 *
 * @method diDiActionsLogModel setTargetType($value)
 * @method diDiActionsLogModel setTargetId($value)
 * @method diDiActionsLogModel setUserType($value)
 * @method diDiActionsLogModel setUserId($value)
 * @method diDiActionsLogModel setAction($value)
 * @method diDiActionsLogModel setInfo($value)
 * @method diDiActionsLogModel setDate($value)
 */
class diDiActionsLogModel extends diModel
{
    protected $table = 'di_actions_log';

    public function getCustomTemplateVars()
    {
        return extend(parent::getCustomTemplateVars(), [
            'info_str' => diActionsLog::getActionInfoStr((object) $this->get()),
            'action_str' => diActionsLog::getActionStr($this->getAction()),
        ]);
    }
}
