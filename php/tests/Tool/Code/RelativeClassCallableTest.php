<?php

namespace diCore\Tests\Tool\Code;

use PHPUnit\Framework\TestCase;

/**
 * PHP 8.2+ deprecates 'self::m' / ['self', 'm'] callables; on prod the
 * deprecation is an ErrorException in Sentry, so none may stay in the sources.
 * constant('self::X') / defined('static::X') are not callables and are allowed.
 */
class RelativeClassCallableTest extends TestCase
{
    private const PATTERN =
        '/(?<!constant\(|defined\()([\'"])(self|parent|static)::[a-z_]\w*\1' .
        '|\[\s*([\'"])(self|parent)\3\s*,/';

    public function testPatternCatchesBothForms(): void
    {
        $this->assertMatchesRegularExpression(
            self::PATTERN,
            "\$f = 'self::getUserAppearance';"
        );
        $this->assertMatchesRegularExpression(
            self::PATTERN,
            "call_user_func(['parent', 'm']);"
        );
        // admin field flags, not a callable
        $this->assertDoesNotMatchRegularExpression(
            self::PATTERN,
            "'flags' => ['static', 'virtual'],"
        );
        $this->assertDoesNotMatchRegularExpression(
            self::PATTERN,
            "constant('self::IMAGE_TYPE');"
        );
        $this->assertDoesNotMatchRegularExpression(
            self::PATTERN,
            "defined('static::SKIP');"
        );
        $this->assertDoesNotMatchRegularExpression(
            self::PATTERN,
            '$f = [self::class, \'m\'];'
        );
    }

    public function testNoRelativeClassCallablesInSources(): void
    {
        $root = dirname(__DIR__, 3);
        $offenders = [];

        foreach (['lib', 'src', 'admin', 'models'] as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    "$root/$dir",
                    \FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($it as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                foreach (file($file->getPathname()) as $i => $line) {
                    if (preg_match(self::PATTERN, $line)) {
                        $offenders[] =
                            substr($file->getPathname(), strlen($root) + 1) .
                            ':' .
                            ($i + 1);
                    }
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
