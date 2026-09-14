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

    /** Консьюмер, поставивший один код и бросивший другой, сохраняет прежний статус. */
    public function testCodeAlreadySetByCmsIsKept(): void
    {
        $this->assertSame(
            HttpCode::GONE,
            HttpException::notFound()->pageStatus(HttpCode::GONE)
        );
    }

    /** Код вне 4xx/5xx – баг в месте броска, а страница всё равно ошибки: 500, не 200. */
    public function testNonErrorCodeIsServerError(): void
    {
        // Фраза явно: для кода вне таблицы конструктор передал бы в Exception null.
        $cases = [0 => 'Zero', HttpCode::OK => 'OK', 600 => 'Out of range'];

        foreach ($cases as $code => $phrase) {
            $this->assertSame(
                HttpCode::INTERNAL_SERVER_ERROR,
                (new HttpException($code, $phrase))->pageStatus(HttpCode::OK),
                "code $code"
            );
        }

        $this->assertSame(
            HttpCode::GONE,
            (new HttpException(600, 'Out of range'))->pageStatus(HttpCode::GONE)
        );
    }

    /** Докблок конструктора обещал 500 для null, а выходили код 0 и `HTTP/1.1 0`. */
    public function testNullCodeMeansInternalServerError(): void
    {
        $e = new HttpException(null);

        $this->assertSame(HttpCode::INTERNAL_SERVER_ERROR, $e->getCode());
        $this->assertSame(['HTTP/1.1 500 Internal Server Error'], $e->getHeaders());
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
