<?php
/**
 * Created by \diModelsManager
 * Date: 11.12.2017
 * Time: 15:59
 */

namespace diCore\Entity\MailPlan;

use diCore\Traits\Model\TargetInside;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getMode
 * @method string	getConditions
 * @method string	getCreatedAt
 * @method string	getStartedAt
 * @method string	getProcessedAt
 *
 * @method bool hasMode
 * @method bool hasConditions
 * @method bool hasCreatedAt
 * @method bool hasStartedAt
 * @method bool hasProcessedAt
 *
 * @method $this setMode($value)
 * @method $this setConditions($value)
 * @method $this setCreatedAt($value)
 * @method $this setStartedAt($value)
 * @method $this setProcessedAt($value)
 */
abstract class Model extends \diModel
{
    use TargetInside;

    const type = \diTypes::mail_plan;
    protected $table = 'mail_plans';
    protected $sentMailsCount = 0;

    protected $customDateFields = ['started_at', 'processed_at'];

    public function getModeName()
    {
        return Mode::name($this->getMode());
    }

    public function getModeTitle()
    {
        return Mode::title($this->getMode());
    }

    public function getCustomTemplateVars()
    {
        return extend(parent::getCustomTemplateVars(), [
            'mode_name' => $this->getModeName(),
            'mode_title' => $this->getModeTitle(),
        ]);
    }

    protected function beforeProcess()
    {
        $this->setStartedAt(\diDateTime::sqlFormat())->save();

        return $this;
    }

    protected function afterProcess()
    {
        $this->setProcessedAt(\diDateTime::sqlFormat())->save();

        return $this;
    }

    /**
     * @return $this
     */
    abstract protected function mainProcess();

    public function process()
    {
        return $this->beforeProcess()
            ->mainProcess()
            ->afterProcess();
    }

    public function getSentMailsCount()
    {
        return $this->sentMailsCount;
    }
}
