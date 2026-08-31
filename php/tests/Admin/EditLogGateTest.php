<?php

namespace diCore\Tests\Admin;

use diCore\Admin\Base;
use diCore\Admin\Page\Configuration as ConfigurationPage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/EditLogGatePages.php';

/**
 * Гейт журнала правок один на всех, кто пишет его мимо админской формы (тоглы и
 * удаления из списка, контроллер настроек): второй экземпляр того же правила
 * разъехался бы с первым, и два писателя стали бы по-разному отвечать на вопрос,
 * логируется ли одна и та же таблица.
 */
class EditLogGateTest extends TestCase
{
    /**
     * Страница ищется по имени модуля, реестра «модуль => страница» нет. Не
     * нашлась – значит не логируем: сомнение трактуется в пользу молчания, а не
     * записи неизвестно чего.
     */
    public function testUnresolvableModuleIsNotLogged(): void
    {
        $this->assertFalse(
            Base::isEditLogEnabledForModule('di_core_tests_no_such_module')
        );
        $this->assertFalse(Base::isEditLogEnabledForModule(''));
    }

    /** Найденная страница отвечает своим флагом – иначе гейт не гейт, а глушилка */
    public function testResolvedPageAnswersWithItsOwnFlag(): void
    {
        $this->assertTrue(
            Base::isEditLogEnabledForModule('edit_log_gate_probe_on'),
            'useEditLog() === true должен доходить до писателя'
        );
        $this->assertFalse(
            Base::isEditLogEnabledForModule('edit_log_gate_probe_off')
        );
    }

    /**
     * Мемо на запрос: повторный вызов не переспрашивает страницу. Проверяем не
     * равенством ответов (оно совпало бы и без кэша), а тем, что подмена флага
     * после первого вызова ответа уже не меняет.
     */
    public function testAnswerIsMemoizedPerRequest(): void
    {
        $module = 'edit_log_gate_probe_flipping';

        $this->assertTrue(Base::isEditLogEnabledForModule($module));

        \diEditLogGateProbeFlippingPage::$flag = false;

        $this->assertTrue(
            Base::isEditLogEnabledForModule($module),
            'ответ должен прийти из мемо, а не из повторного резолва'
        );
    }

    /**
     * Таблица и модуль – разные вопросы, и у настроек они расходятся:
     * Data\Configuration::setTableName() переименовывает таблицу под страницей, а
     * адрес страницы остаётся прежним. Спросив по таблице, контроллер настроек
     * получил бы «не логируем» и молча не писал бы ничего, пока страница рисует
     * вкладку.
     */
    public function testRenamedTableResolvesToNothingWhileTheModuleStands(): void
    {
        $this->assertFalse(
            Base::isEditLogEnabledForTable('renamed_settings_table'),
            'по имени переименованной таблицы страница не находится'
        );
        $this->assertSame(
            'configuration',
            ConfigurationPage::ADMIN_MODULE,
            'модуль настроек – константа, именно её и спрашивает гейт'
        );
    }

    /** Обычной сущности модуль называется по её таблице, поэтому вопросы совпадают */
    public function testTableVariantDelegatesToTheModuleOne(): void
    {
        $this->assertTrue(Base::isEditLogEnabledForTable('edit_log_gate_probe_on'));
    }
}
