<?php

namespace diCore\Tests\Entity;

use diCore\Database\FieldType;
use diCore\Entity\Localization\Model;
use PHPUnit\Framework\TestCase;

/**
 * Covers \diCore\Entity\Localization\Model::getValueFields() — what tells a
 * migration which languages the project has. Derived from $fieldTypes so that a
 * language cannot be declared half-way.
 */
class LocalizationValueFieldsTest extends TestCase
{
    public function testTheCoreModelKnowsItsOwnTwoColumns(): void
    {
        $this->assertSame(['value', 'en_value'], Model::getValueFields());
    }

    public function testAProjectModelContributesItsLanguages(): void
    {
        $this->assertSame(
            ['value', 'en_value', 'de_value', 'fr_value'],
            SixLanguageLocalizationModel::getValueFields()
        );
    }

    /** Plain "ends with _value" would also match `default_value`. */
    public function testAColumnThatMerelyEndsInValueIsNotALanguage(): void
    {
        $fields = NoisyLocalizationModel::getValueFields();

        $this->assertSame(['value', 'en_value'], $fields);
        $this->assertNotContains('default_value', $fields);
        $this->assertNotContains('old_value', $fields);
    }

    public function testAModelWithoutFieldTypesReportsNothingRatherThanGuessing(): void
    {
        $this->assertSame([], BareLocalizationModel::getValueFields());
    }
}

class SixLanguageLocalizationModel extends Model
{
    protected static $fieldTypes = [
        'id' => FieldType::int,
        'name' => FieldType::string,
        'value' => FieldType::string,
        'en_value' => FieldType::string,
        'de_value' => FieldType::string,
        'fr_value' => FieldType::string,
    ];
}

class NoisyLocalizationModel extends Model
{
    protected static $fieldTypes = [
        'id' => FieldType::int,
        'name' => FieldType::string,
        'value' => FieldType::string,
        'en_value' => FieldType::string,
        'default_value' => FieldType::string,
        'old_value' => FieldType::string,
    ];
}

class BareLocalizationModel extends Model
{
    protected static $fieldTypes = [];
}
