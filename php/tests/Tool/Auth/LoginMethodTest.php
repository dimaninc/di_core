<?php

namespace diCore\Tests\Tool\Auth;

use diCore\Data\Configuration;
use diCore\Tool\Auth\LoginMethod;
use PHPUnit\Framework\TestCase;

class CodeFirstLoginMethod extends LoginMethod
{
    const DEFAULT_METHOD = self::password_primary;
}

/**
 * A project that never heard of sign-in by code must not get it: absent and broken
 * settings mean DEFAULT_METHOD, and the core's is password_only.
 */
class LoginMethodTest extends TestCase
{
    private $data = [];

    protected function setUp(): void
    {
        $this->data = Configuration::$data;
    }

    protected function tearDown(): void
    {
        Configuration::$data = $this->data;
    }

    public function testAbsentKeyMeansPasswordOnlyWithoutThrowing(): void
    {
        unset(Configuration::$data[LoginMethod::CONFIG_KEY]);

        $this->assertSame(LoginMethod::password_only, LoginMethod::current());
        $this->assertFalse(LoginMethod::isCodeEnabled());
        $this->assertFalse(LoginMethod::isCodePrimary());
    }

    public function testStoredValueIsFollowed(): void
    {
        $this->set('3');
        $this->assertSame(LoginMethod::code_primary, LoginMethod::current());
        $this->assertTrue(LoginMethod::isCodeEnabled());
        $this->assertTrue(LoginMethod::isCodePrimary());

        $this->set(2);
        $this->assertSame(LoginMethod::password_primary, LoginMethod::current());
        $this->assertTrue(LoginMethod::isCodeEnabled());
        $this->assertFalse(LoginMethod::isCodePrimary());
    }

    public function testInvalidValuesFallBackToTheDefault(): void
    {
        foreach (
            ['', null, '0', '99', 'code_primary', '2.5', ' 3', true]
            as $value
        ) {
            $this->set($value);
            $this->assertSame(
                LoginMethod::password_only,
                LoginMethod::current(),
                var_export($value, true)
            );
            $this->assertSame(
                CodeFirstLoginMethod::password_primary,
                CodeFirstLoginMethod::current()
            );
        }
    }

    public function testProjectOverridesTheDefault(): void
    {
        unset(Configuration::$data[LoginMethod::CONFIG_KEY]);

        $this->assertSame(
            LoginMethod::password_primary,
            CodeFirstLoginMethod::current()
        );
        $this->assertTrue(CodeFirstLoginMethod::isCodeEnabled());
    }

    public function testEveryMethodHasANameAndATitle(): void
    {
        foreach (
            [
                LoginMethod::password_only,
                LoginMethod::password_primary,
                LoginMethod::code_primary,
            ]
            as $id
        ) {
            $this->assertNotEmpty(LoginMethod::name($id));
            $this->assertNotEmpty(LoginMethod::title($id));
        }
    }

    private function set($value): void
    {
        Configuration::$data[LoginMethod::CONFIG_KEY] = [
            'value' => $value,
            'type' => 'select',
        ];
    }
}
