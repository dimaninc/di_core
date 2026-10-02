<?php

namespace diCore\Tests\Admin;

use diCore\Admin\BasePage;
use diCore\Entity\AdminTableEditLog\Model as TableEditLog;
use PHPUnit\Framework\TestCase;

/**
 * BasePage::shouldLazyLoadEditLog(): on by default, so printEditLog() must stop
 * touching the store on every form render (createEditLogCollection() is
 * otherwise unbounded) and instead render an empty container that
 * loadEditLogPage() – the AJAX endpoint's entry point – fills chunk by chunk,
 * with the same degradation and gating renderEditLog() already has. Paged by an
 * id cursor (filterById($lastId, '<')) rather than setPageNumber()'s OFFSET –
 * see loadEditLogPage()'s own docblock for why an OFFSET window is unsafe here.
 */
class EditLogLazyLoadTest extends TestCase
{
    public function testLazyLoadIsOnByDefault(): void
    {
        $page = EditLogLazyProbePage::make();

        $this->assertTrue($page->shouldLazyLoadEditLog());
    }

    public function testInitialFormRenderNeverTouchesTheStore(): void
    {
        $page = EditLogLazyProbePage::make();
        $page->collection = new StoreTouchExplodesCollection();

        $page->runPrintEditLog();

        $this->assertSame(
            'rendered:admin/admin_table_edit_log/lazy',
            $page->probeForm->inputs[TableEditLog::ADMIN_TAB_NAME] ?? null
        );
    }

    public function testLazyContainerCarriesTableModuleIdAndPageSize(): void
    {
        $page = EditLogLazyProbePage::make();
        $page->collection = new StoreTouchExplodesCollection();

        $page->runPrintEditLog();

        $args = $page->probeTwig->lastArgs;
        $this->assertSame('probe_table', $args['table']);
        $this->assertSame('probe_module', $args['module']);
        $this->assertSame(42, $args['id']);
        $this->assertSame(20, $args['page_size']);
    }

    public function testOverridingToFalseKeepsTheEagerRender(): void
    {
        $page = EditLogLazyProbePage::make();
        $page->lazyLoadEnabled = false;
        $page->collection = new WorkingLazyEditLogCollection([
            new EditLogLazyProbeRecord(100),
        ]);

        $page->runPrintEditLog();

        $this->assertSame(
            'rendered:admin/admin_table_edit_log/form_field',
            $page->probeForm->inputs[TableEditLog::ADMIN_TAB_NAME] ?? null
        );
    }

    public function testLoadEditLogPageAppliesCursorAndParsesRecords(): void
    {
        $page = EditLogLazyProbePage::make();
        $record = new EditLogLazyProbeRecord(41);
        $page->collection = new WorkingLazyEditLogCollection([$record]);

        $result = $page->loadEditLogPage(42);

        $this->assertSame(20, $page->collection->pageSize);
        $this->assertSame([42, '<'], $page->collection->idFilter);
        $this->assertTrue($record->parsed);
        $this->assertSame(
            'rendered:admin/admin_table_edit_log/_items',
            $result['html']
        );
        $this->assertFalse(
            $result['has_more'],
            'a chunk smaller than the page size is the last one'
        );
        $this->assertSame(41, $result['last_id']);
    }

    public function testLoadEditLogPageWithNoCursorFetchesFirstChunkUnfiltered(): void
    {
        $page = EditLogLazyProbePage::make();
        $page->collection = new WorkingLazyEditLogCollection([
            new EditLogLazyProbeRecord(10),
        ]);

        $page->loadEditLogPage(null);

        $this->assertNull($page->collection->idFilter);
    }

    public function testLoadEditLogPageTreatsAFullChunkAsPossiblyNotLast(): void
    {
        $page = EditLogLazyProbePage::make();
        $records = [];
        for ($i = 0; $i < 20; $i++) {
            $records[] = new EditLogLazyProbeRecord(20 - $i);
        }
        $page->collection = new WorkingLazyEditLogCollection($records);

        $result = $page->loadEditLogPage(null);

        $this->assertTrue($result['has_more']);
        // Ordered id DESC, so the chunk's last item carries the lowest id – the
        // next chunk's cursor.
        $this->assertSame(1, $result['last_id']);
    }

    public function testLoadEditLogPageReturnsEmptyHtmlWhenNothingMatched(): void
    {
        $page = EditLogLazyProbePage::make();
        $page->collection = new WorkingLazyEditLogCollection([]);

        $result = $page->loadEditLogPage(null);

        $this->assertSame('', $result['html']);
        $this->assertFalse($result['has_more']);
        $this->assertNull($result['last_id']);
    }

    public function testLoadEditLogPageDegradesLikeRenderEditLogDoes(): void
    {
        $page = EditLogLazyProbePage::make();
        $page->collection = new ThrowingLazyEditLogCollection();

        $result = $page->loadEditLogPage(1);

        $this->assertSame(
            'Журнал изменений временно недоступен',
            $result['html']
        );
        $this->assertFalse($result['has_more']);
        $this->assertNull($result['last_id']);
        $this->assertInstanceOf(\Exception::class, $page->reported);
        $this->assertSame('store down', $page->reported->getMessage());
        $this->assertTrue(
            $result['error'],
            "diAdminForm.js's diEditLogLazyLoad() needs this to tell a status " .
            "message apart from zero records - without it, the degraded text " .
            "gets appended into the list (no <li>, so children().length stays " .
            "0) and is then relabelled as the empty-log message"
        );
    }

    public function testLoadEditLogPageIsGatedByUseEditLog(): void
    {
        $page = EditLogLazyProbePage::make();
        $page->editLogEnabled = false;
        $page->collection = new StoreTouchExplodesCollection();

        $result = $page->loadEditLogPage(1);

        $this->assertSame(['html' => '', 'has_more' => false, 'last_id' => null], $result);
    }

    public function testLoadEditLogPageIsGatedByHideEditLog(): void
    {
        $page = EditLogLazyProbePage::make();
        $page->editLogHidden = true;
        $page->collection = new StoreTouchExplodesCollection();

        $result = $page->loadEditLogPage(1);

        $this->assertSame(['html' => '', 'has_more' => false, 'last_id' => null], $result);
    }
}

class EditLogLazyProbePage extends BasePage
{
    public $probeForm;
    public $probeTwig;
    public $collection;
    public ?\Exception $reported = null;
    public bool $editLogEnabled = true;
    public bool $editLogHidden = false;
    public bool $lazyLoadEnabled = true;

    public static function make(): self
    {
        /** @var self $page */
        $page = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $page->probeForm = new EditLogLazyProbeForm();
        $page->probeTwig = new EditLogLazyProbeTwig();

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
        return $this->editLogHidden;
    }

    public function shouldLazyLoadEditLog()
    {
        return $this->lazyLoadEnabled;
    }

    public function getTable()
    {
        return 'probe_table';
    }

    public function getModule()
    {
        return 'probe_module';
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
        return 'ru';
    }

    protected function createEditLogCollection()
    {
        return $this->collection;
    }

    // Keep the assertion on the contract, not on the file logger.
    protected function onEditLogUnavailable(\Exception $e)
    {
        $this->reported = $e;

        return $this;
    }
}

/** Any method call proves a code path reached the store when it must not have. */
class StoreTouchExplodesCollection
{
    public function __call($name, $args)
    {
        throw new \Exception("unexpected store access via {$name}()");
    }
}

/** Loads fine and is iterable, so the happy path runs end to end. */
class WorkingLazyEditLogCollection implements \IteratorAggregate, \Countable
{
    public $pageSize;
    public $idFilter = null;
    public array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function setPageSize($size)
    {
        $this->pageSize = $size;

        return $this;
    }

    public function filterById($value, $operator)
    {
        $this->idFilter = [$value, $operator];

        return $this;
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

/** Accepts pagination calls, but load() hits a down store. */
class ThrowingLazyEditLogCollection
{
    public function setPageSize($size)
    {
        return $this;
    }

    public function filterById($value, $operator)
    {
        return $this;
    }

    public function load()
    {
        throw new \Exception('store down');
    }
}

class EditLogLazyProbeRecord
{
    public bool $parsed = false;
    private $id;

    public function __construct($id = 1)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function parseData()
    {
        $this->parsed = true;

        return $this;
    }
}

class EditLogLazyProbeTwig
{
    public ?array $lastArgs = null;

    private ?\Twig\Environment $engine = null;

    public function parse($template, $args = [])
    {
        $this->lastArgs = $args;

        return 'rendered:' . $template;
    }

    /**
     * loadEditLogPage() registers the 'insdel' escaper itself (same reason
     * renderEditLog() does) – the double must hand back a real Environment or
     * the test would stop proving that dependency is satisfiable.
     */
    public function getEngine()
    {
        if ($this->engine === null) {
            $this->engine = new \Twig\Environment(new \Twig\Loader\ArrayLoader([]));
        }

        return $this->engine;
    }
}

class EditLogLazyProbeForm
{
    public array $inputs = [];

    public function setInput($field, $input, $static_input = '')
    {
        $this->inputs[$field] = $input;

        return $this;
    }
}
