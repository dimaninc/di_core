<?php
/**
 * Стенды для EditLogGateTest. Живут в глобальном неймспейсе и в отдельном файле
 * намеренно: резолв идёт через \diLib::getClassNameFor(), последний запасной
 * вариант которого – глобальный di{Name}Page, а неймспейсов проекта у библиотеки
 * нет. Файл не кончается на Test.php, поэтому phpunit его не собирает.
 */

class diEditLogGateProbeOnPage extends \diCore\Admin\BasePage
{
    public function useEditLog()
    {
        return true;
    }
}

class diEditLogGateProbeOffPage extends \diCore\Admin\BasePage
{
    public function useEditLog()
    {
        return false;
    }
}

class diEditLogGateProbeFlippingPage extends \diCore\Admin\BasePage
{
    public static $flag = true;

    public function useEditLog()
    {
        return static::$flag;
    }
}
