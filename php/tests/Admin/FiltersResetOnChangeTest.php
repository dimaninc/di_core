<?php

namespace diCore\Tests\Admin;

use PHPUnit\Framework\TestCase;

/**
 * `resetOnChange` – поля, которые `diAdminFilters.js` очищает перед отправкой по
 * `submitOnChange`: без атрибута на select сброса не будет.
 */
class FiltersResetOnChangeTest extends TestCase
{
    public function testSelectCarriesFieldsToReset(): void
    {
        $html = (string) $this->filters(['vendor', 'region'])
            ->setSelectFromArrayInput('pay_system', [7 => 'Тинькофф'])
            ->getInput('pay_system');

        $this->assertStringContainsString('data-submit-on-change="1"', $html);
        $this->assertStringContainsString(
            'data-reset-on-change="vendor,region"',
            $html
        );
    }

    public function testNoAttributeWithoutOption(): void
    {
        $html = (string) $this->filters([])
            ->setSelectFromArrayInput('pay_system', [7 => 'Тинькофф'])
            ->getInput('pay_system');

        $this->assertStringNotContainsString('data-reset-on-change', $html);
    }

    // Без select фильтр рисуется `<input type="text">` – отдельной веткой
    public function testTextInputCarriesFieldsToReset(): void
    {
        $html = (string) $this->filters(['vendor'])->getInput('pay_system');

        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('data-reset-on-change="vendor"', $html);
    }

    private function filters(array $resetOnChange): \diAdminFilters
    {
        $reflection = new \ReflectionClass(\diAdminFilters::class);
        /** @var \diAdminFilters $filters */
        $filters = $reflection->newInstanceWithoutConstructor();
        $filters->reset = true;

        return $filters->addFilter([
            'field' => 'pay_system',
            'type' => 'int',
            'submitOnChange' => true,
            'resetOnChange' => $resetOnChange,
        ]);
    }
}
