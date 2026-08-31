<?php

namespace diCore\Tests\Admin;

use diCore\Admin\BasePage;
use diCore\Entity\AdminTableEditLog\Model as TableEditLog;
use PHPUnit\Framework\TestCase;

/**
 * An edit-log store outage must not 500 the admin form, and must not leave the
 * tab silently empty either — `getFormFieldsFiltered()`/`Form::get_html()`
 * register that tab unconditionally, so an empty one reads as "this record was
 * never edited".
 */
class EditLogDegradationTest extends TestCase
{
    public function testStoreOutageFillsTheTabWithAnExplicitNotice(): void
    {
        $page = EditLogProbePage::make();

        $page->runPrintEditLog();

        $this->assertSame(
            'Журнал изменений временно недоступен',
            $page->probeForm->inputs[TableEditLog::ADMIN_TAB_NAME] ?? null,
            'the tab says it is unavailable rather than rendering empty'
        );
    }

    public function testStoreOutageIsReportedThroughTheHook(): void
    {
        $page = EditLogProbePage::make();

        $page->runPrintEditLog();

        $this->assertInstanceOf(
            \Exception::class,
            $page->reported,
            'the outage is surfaced to monitoring, not swallowed silently'
        );
        $this->assertSame('mongo down', $page->reported->getMessage());
    }

    public function testNothingIsRenderedWhenTheEditLogIsOff(): void
    {
        $page = EditLogProbePage::make();
        $page->editLogEnabled = false;

        $page->runPrintEditLog();

        $this->assertSame([], $page->probeForm->inputs);
        $this->assertNull($page->reported);
    }

    public function testHealthyStoreRendersTheTabWithTheExpectedTwigArguments(): void
    {
        $page = EditLogProbePage::make();
        $page->collection = new WorkingEditLogCollection();

        $page->runPrintEditLog();

        $this->assertNull($page->reported, 'no outage reported on the happy path');
        $this->assertSame(
            'rendered:admin/admin_table_edit_log/form_field',
            $page->probeForm->inputs[TableEditLog::ADMIN_TAB_NAME] ?? null
        );

        $args = $page->probeTwig->lastArgs;
        $this->assertSame(
            $page->collection,
            $args['records'],
            'the loaded collection is passed through'
        );
        $this->assertArrayHasKey('admins', $args);
        $this->assertFalse($args['options']['show_only_diff']);
        $this->assertFalse($args['options']['strip_tags']);
        // Quirk (pre-existing): useEditLog() may return `true` rather than an
        // options array, and `extend($defaults, (array) true)` folds in a stray
        // `0 => true`. Harmless — Twig ignores it — but assert the real shape so a
        // future cleanup is a deliberate change, not a surprise.
        $this->assertSame(true, $args['options'][0] ?? null);
        $this->assertTrue(
            $page->collection->items[0]->parsed,
            'parseData() runs outside the guard, on the happy path'
        );
    }

    public function testCountingIsCoveredByTheGuard(): void
    {
        // The template opens with `records|length`; on Mongo that is a separate
        // query, so it must fail inside the guard, not mid-render.
        $page = EditLogProbePage::make();
        $page->collection = new CountFailingEditLogCollection();

        $page->runPrintEditLog();

        $this->assertInstanceOf(\Exception::class, $page->reported);
        $this->assertSame(
            'Журнал изменений временно недоступен',
            $page->probeForm->inputs[TableEditLog::ADMIN_TAB_NAME] ?? null
        );
    }

    public function testCodeBugsAreNotDisguisedAsAnOutage(): void
    {
        // \Error/\TypeError are code bugs, not store outages — the guard catches
        // \Exception precisely so these still propagate.
        $page = EditLogProbePage::make();
        $page->collection = new ErroringEditLogCollection();

        $this->expectException(\Error::class);

        try {
            $page->runPrintEditLog();
        } finally {
            $this->assertNull(
                $page->reported,
                'a code bug is never reported as an outage'
            );
        }
    }

    public function testAFailingReporterStillLeavesTheFormUsable(): void
    {
        // The recovery path must be best-effort itself: an unwritable log dir makes
        // Logger throw a TypeError (an \Error), which would sail past catch
        // (\Exception) and 500 the form — the exact outcome this feature prevents.
        $page = EditLogProbePage::make();
        $page->reporterThrows = true;

        $page->runPrintEditLog();

        $this->assertSame(
            'Журнал изменений временно недоступен',
            $page->probeForm->inputs[TableEditLog::ADMIN_TAB_NAME] ?? null
        );
    }

    public function testUnknownAdminLanguageFallsBackInsteadOfBlowingUp(): void
    {
        // localized() has no fallback; the notice must still render for an admin
        // language other than ru/en.
        $page = EditLogProbePage::make();
        $page->language = 'de';

        $page->runPrintEditLog();

        $this->assertSame(
            'Edit log is temporarily unavailable',
            $page->probeForm->inputs[TableEditLog::ADMIN_TAB_NAME] ?? null
        );
    }
}

class EditLogProbePage extends BasePage
{
    public $probeForm;
    public $probeTwig;
    public $collection;
    public ?\Exception $reported = null;
    public bool $editLogEnabled = true;
    public bool $reporterThrows = false;
    public string $language = 'ru';

    public static function make(): self
    {
        /** @var self $page */
        $page = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $page->probeForm = new EditLogProbeForm();
        $page->probeTwig = new EditLogProbeTwig();
        $page->collection = new ThrowingEditLogCollection();

        return $page;
    }

    public function getTwig()
    {
        return $this->probeTwig;
    }

    public function runPrintEditLog()
    {
        return $this->printEditLog();
    }

    public function useEditLog()
    {
        return $this->editLogEnabled;
    }

    public function hideEditLog()
    {
        return false;
    }

    public function getTable()
    {
        return 'probe_table';
    }

    public function getId()
    {
        return 42;
    }

    public function getForm()
    {
        return $this->probeForm;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    protected function createEditLogCollection()
    {
        return $this->collection;
    }

    // Keep the assertion on the contract, not on the file logger.
    protected function onEditLogUnavailable(\Exception $e)
    {
        $this->reported = $e;

        if ($this->reporterThrows) {
            // what an unwritable log dir does inside Logger
            throw new \TypeError('fputs(): Argument #1 must be of type resource');
        }

        return $this;
    }
}

/** Only load() is reached before the guard trips. */
class ThrowingEditLogCollection
{
    public function load()
    {
        throw new \Exception('mongo down');
    }
}

/** Loads fine and is iterable/countable, so the happy path runs end to end. */
class WorkingEditLogCollection implements \IteratorAggregate, \Countable
{
    public array $items;

    public function __construct()
    {
        $this->items = [new EditLogProbeRecord()];
    }

    public function load()
    {
        return $this;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->items);
    }
}

/** Loads, but counting hits the store and fails (the Mongo count() query). */
class CountFailingEditLogCollection
{
    public function load()
    {
        return $this;
    }

    public function count()
    {
        throw new \Exception('mongo down on count');
    }
}

/** A code bug, not an outage — must propagate. */
class ErroringEditLogCollection
{
    public function load()
    {
        throw new \Error('broken code');
    }
}

class EditLogProbeRecord
{
    public bool $parsed = false;

    public function parseData()
    {
        $this->parsed = true;

        return $this;
    }
}

class EditLogProbeTwig
{
    public ?array $lastArgs = null;

    private ?\Twig\Environment $engine = null;

    public function parse($template, $args = [])
    {
        $this->lastArgs = $args;

        return 'rendered:' . $template;
    }

    /**
     * renderEditLog() поднимает escaper 'insdel' сам – шаблон без него не
     * отрисовывается вовсе. Дублю приходится отдавать настоящий Environment: если
     * заглушить и это, тест перестанет проверять, что зависимость выполнима.
     */
    public function getEngine()
    {
        if ($this->engine === null) {
            $this->engine = new \Twig\Environment(new \Twig\Loader\ArrayLoader([]));
        }

        return $this->engine;
    }
}

class EditLogProbeForm
{
    public array $inputs = [];

    public function setInput($field, $input, $static_input = '')
    {
        $this->inputs[$field] = $input;

        return $this;
    }
}
