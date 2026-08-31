<?php

namespace diCore\Tests\Admin;

use diCore\Admin\BasePage;
use diCore\Entity\AdminTableEditLog\Model as EditLog;
use PHPUnit\Framework\TestCase;

/**
 * Шаблон журнала правок пропускает каждую разницу через `escape('insdel')`, а
 * незнакомая Twig'у стратегия экранирования – это RuntimeError, а не запасной
 * вариант. То есть шаблон нельзя отрисовать, не зарегистрировав escaper, и
 * регистрация обязана стоять на КАЖДОМ пути отрисовки, а не только на форменном:
 * страница настроек формы не имеет вовсе и умирала на собственной вкладке.
 */
class EditLogEscaperTest extends TestCase
{
    private const TEMPLATE = 'admin/admin_table_edit_log/form_field';

    public function testTemplateRendersOnceTheEscaperIsRegistered(): void
    {
        $twig = $this->createTwig();
        BasePage::registerEditLogEscaper($twig);

        $html = $this->render($twig);

        // ins/del разметку diff'а escaper обязан пропустить сырой – ради неё он и есть
        $this->assertStringContainsString('<del>Old</del>', $html);
        $this->assertStringContainsString('<ins>New</ins>', $html);
    }

    /**
     * Обратная половина того же: без регистрации шаблон падает. Тест закрепляет
     * зависимость, из-за незнания которой вкладку и повесили на страницу без формы.
     */
    public function testTemplateIsUnrenderableWithoutTheEscaper(): void
    {
        $this->expectException(\Twig\Error\RuntimeError::class);
        $this->expectExceptionMessageMatches('/insdel/');

        $this->render($this->createTwig());
    }

    /** Значения чекбоксов приезжают int'ами, escaper обязан пережить и их */
    public function testIntegerValuesAreRenderedToo(): void
    {
        $twig = $this->createTwig();
        BasePage::registerEditLogEscaper($twig);

        $html = $this->render($twig, ['show_banners' => 1], ['show_banners' => 0]);

        $this->assertStringContainsString('<del>1</del>', $html);
        $this->assertStringContainsString('<ins>0</ins>', $html);
    }

    /**
     * И главное: escaper регистрирует САМА отрисовка, а не форменный путь до неё.
     * Раньше это делал только beforeRenderForm(), поэтому страница настроек –
     * без формы, отрисованная как список – падала на своей же вкладке.
     */
    public function testRenderRegistersTheEscaperItself(): void
    {
        $probe = EditLogRenderProbe::make();

        $html = $probe->render();

        $this->assertSame(
            1,
            $probe->escaperRegistrations,
            'renderEditLog() обязан зарегистрировать escaper сам'
        );
        $this->assertStringContainsString('История изменений пуста', $html);
    }

    private function createTwig(): \diTwig
    {
        $twig = \diTwig::create();
        $twig->assign(['X' => new EditLogLanguageStub()]);

        return $twig;
    }

    private function render(
        \diTwig $twig,
        array $old = ['site_title' => 'Old'],
        array $new = ['site_title' => 'New']
    ): string {
        $rec = EditLog::create();
        $rec->setTargetTable('configuration')
            ->setTargetId(1)
            ->setAdminId(1)
            ->setOldData(serialize($old))
            ->setNewData(serialize($new));
        $rec->parseData();

        return $twig->parse(self::TEMPLATE, [
            'records' => [$rec],
            'admins' => new EditLogAdminsStub(),
            'options' => ['show_only_diff' => false, 'strip_tags' => false],
        ]);
    }
}

/** Шаблон спрашивает у X только язык */
class EditLogLanguageStub
{
    public function getLanguage()
    {
        return 'ru';
    }
}

/** Заменяет Entity\Admin\Collection: без базы, любой admin_id – «Robot» */
class EditLogAdminsStub implements \ArrayAccess
{
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return new EditLogAdminStub();
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
    }
}

class EditLogAdminStub implements \ArrayAccess
{
    public function exists()
    {
        return false;
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return false;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
    }
}

/**
 * Страница без формы и без базы: проверяем ровно то, что renderEditLog() сам
 * поднимает escaper. Журнал пуст намеренно – ветка «пусто» не трогает список
 * админов, то есть тест остаётся фреймворковым и не зависит от схемы проекта.
 */
class EditLogRenderProbe extends BasePage
{
    public $escaperRegistrations = 0;

    /** @var \diTwig */
    private $twigStub;

    public static function make(): self
    {
        /** @var self $probe */
        $probe = (new \ReflectionClass(
            self::class
        ))->newInstanceWithoutConstructor();
        $probe->twigStub = \diTwig::create();
        $probe->twigStub->assign(['X' => new EditLogLanguageStub()]);

        return $probe;
    }

    public function getTwig()
    {
        return $this->twigStub;
    }

    public function useEditLog()
    {
        return true;
    }

    public function render()
    {
        return $this->renderEditLog();
    }

    protected function prepareForEditLog()
    {
        $this->escaperRegistrations++;

        return parent::prepareForEditLog();
    }

    protected function createEditLogCollection()
    {
        return new EditLogEmptyCollectionStub();
    }
}

/** Ровно то, что спрашивает renderEditLog(): load(), count() и обход */
class EditLogEmptyCollectionStub implements \IteratorAggregate, \Countable
{
    public function load()
    {
        return $this;
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return 0;
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator([]);
    }
}
