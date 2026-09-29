<?php

namespace diCore\Tests\Tool;

use PHPUnit\Framework\TestCase;

/**
 * PHP 8+ пишет To и Subject через CRLF. Заголовки diEmail должны идти тем же
 * разделителем, иначе MTA склеивает их в продолжение From и письмо теряет MIME.
 */
class EmailHeadersTest extends TestCase
{
    public function testDefaultSeparatorMatchesPhpVersion(): void
    {
        $this->assertSame(
            PHP_VERSION_ID >= 80000 ? "\r\n" : "\n",
            \diEmail::getHeadersNL()
        );
    }

    public function testChildClassCanOverrideSeparator(): void
    {
        $this->assertSame("\n", LfEmail::getHeadersNL());
    }

    /**
     * Реальная отправка: sendmail подменён на запись сырого письма в файл
     */
    public function testHeadersAreNotFolded(): void
    {
        $out = tempnam(sys_get_temp_dir(), 'mail');
        $root = dirname(__DIR__, 2);
        $code = sprintf(
            'require %s; require %s; diEmail::fastSend("from@example.com", "to@example.com", "subj", "", "<b>html</b>");',
            var_export($root . '/lib/diLib.php', true),
            var_export($root . '/functions.php', true)
        );

        exec(
            escapeshellarg(PHP_BINARY) .
                ' -d ' .
                escapeshellarg('sendmail_path=cat > ' . $out) .
                ' -r ' .
                escapeshellarg($code) .
                ' 2>&1',
            $output,
            $exitCode
        );

        $raw = file_get_contents($out);
        unlink($out);

        $this->assertSame(0, $exitCode, implode("\n", $output));

        [$headers] = explode("\r\n\r\n", $raw, 2);

        // Строка, начинающаяся с пробела, – продолжение предыдущего заголовка
        $this->assertDoesNotMatchRegularExpression('/\n[ \t]/', $headers);
        // Голый LF внутри заголовков и есть причина склейки
        $this->assertDoesNotMatchRegularExpression('/(?<!\r)\n/', $headers);
        $this->assertStringContainsString("\r\nMIME-Version: 1.0\r\n", $headers);
        $this->assertStringContainsString('Content-Type: multipart/mixed', $headers);
    }
}

class LfEmail extends \diEmail
{
    public static $headersNL = "\n";
}
