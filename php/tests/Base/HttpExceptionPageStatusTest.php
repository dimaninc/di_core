<?php

namespace diCore\Tests\Base;

use diCore\Base\Exception\HttpException;
use diCore\Data\Http\HttpCode;
use PHPUnit\Framework\TestCase;

/**
 * Статус страницы, отвечающей HttpException. `CMS::work()` отправляет код CMS,
 * а не исключения, поэтому модуль, бросивший `HttpException::notFound()` без
 * `setResponseCode()`, отдавал страницу 404 со статусом 200.
 */
class HttpExceptionPageStatusTest extends TestCase
{
    public function testExceptionCodeWinsWhileCmsStillSaysOk(): void
    {
        $this->assertSame(
            HttpCode::NOT_FOUND,
            HttpException::notFound()->pageStatus(HttpCode::OK)
        );
        $this->assertSame(
            HttpCode::BAD_GATEWAY,
            (new HttpException(HttpCode::BAD_GATEWAY))->pageStatus(HttpCode::OK)
        );
    }

    /** `errorNotFound()` и соседи ставят код до броска – это решение не перетирается. */
    public function testCodeAlreadySetByCmsIsKept(): void
    {
        $this->assertSame(
            HttpCode::GONE,
            HttpException::notFound()->pageStatus(HttpCode::GONE)
        );
    }

    /**
     * Порядок важен: `renderBeforeError()` тоже ветвится по коду ответа, и
     * поставленный после него статус оставил бы странице 404 чужие данные.
     */
    public function testWorkSetsStatusBeforeRenderingTheErrorPage(): void
    {
        $src = self::squash(self::workSource());

        $set = strpos(
            $src,
            '$this->setResponseCode($e->pageStatus($this->getResponseCode()));'
        );
        $render = strpos($src, '$this->renderBeforeError();');

        $this->assertNotFalse($set, 'work() не выставляет статус из исключения');
        $this->assertNotFalse($render, 'work() больше не рисует страницу ошибки');
        $this->assertLessThan($render, $set);
    }

    /** Читается файлом, а не через Reflection: CMS тянет за собой весь бутстрап. */
    private static function workSource(): string
    {
        $src = (string) file_get_contents(
            __DIR__ . '/../../src/diCore/Base/CMS.php'
        );
        $start = strpos($src, 'public function work()');
        self::assertNotFalse($start, 'CMS::work() пропал');

        $end = strpos($src, "\n    }\n", $start);
        self::assertNotFalse($end);

        return substr($src, $start, $end - $start);
    }

    /** Сверка не должна зависеть от переносов, которые расставит Prettier. */
    private static function squash(string $src): string
    {
        return (string) preg_replace('/\s+/', '', $src);
    }
}
