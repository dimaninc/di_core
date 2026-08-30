<?php

namespace diCore\Tests\Admin;

use diCore\Admin\Base;
use PHPUnit\Framework\TestCase;

/**
 * Гейт журнала правок один на всех, кто пишет его мимо админской формы (тоглы и
 * удаления из списка, контроллер настроек): второй экземпляр того же правила
 * разъехался бы с первым, и два писателя стали бы по-разному отвечать на вопрос,
 * логируется ли одна и та же таблица.
 */
class EditLogGateTest extends TestCase
{
    /**
     * Страница ищется по имени таблицы, реестра «таблица => страница» нет. Не
     * нашлась – значит не логируем: сомнение трактуется в пользу молчания, а не
     * записи неизвестно чего.
     */
    public function testUnresolvableTableIsNotLogged(): void
    {
        $this->assertFalse(
            Base::isEditLogEnabledForTable('di_core_tests_no_such_module')
        );
        $this->assertFalse(Base::isEditLogEnabledForTable(''));
    }

    /** Мемо на запрос: ответ не меняется от повторного вызова */
    public function testAnswerIsStable(): void
    {
        $first = Base::isEditLogEnabledForTable('di_core_tests_no_such_module');

        $this->assertSame(
            $first,
            Base::isEditLogEnabledForTable('di_core_tests_no_such_module')
        );
    }
}
