<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.10.2019
 * Time: 13:09
 */

namespace diCore\Database\Tool;

use diCore\Tool\Localization;

abstract class LocalizationMigration extends Migration
{
    protected $names = [];

    protected function updateCache()
    {
        $L = Localization::basicCreate();
        $L->createCache();

        return $this;
    }

    protected function upWrapper()
    {
        $res = parent::upWrapper();

        $this->updateCache();

        return $res;
    }

    protected function downWrapper()
    {
        $res = parent::downWrapper();

        $this->updateCache();

        return $res;
    }

    protected function getLocalizationTable(): string
    {
        return 'localization';
    }

    /**
     * Value columns in the order the per-token list is given in.
     *
     * Override in a project whose localization table carries a different set of
     * languages — this package's own dump only has `value` and `en_value`.
     *
     * @return string[]
     */
    protected function getValueFields(): array
    {
        return ['value', 'en_value', 'de_value', 'it_value', 'es_value', 'fr_value'];
    }

    /**
     * Adds localization tokens, keeping whatever is already in the table.
     *
     * Input is `token => [ru, en, de, it, es, fr]` — the list is positional and
     * lines up with getValueFields(); a short list leaves the remaining columns
     * empty, a longer one has its tail ignored.
     *
     * INSERT IGNORE (not INSERT) because `name` is unique and a token an editor
     * has already translated must not be reset to the migration's default: these
     * migrations get re-run on databases that are ahead of them.
     *
     * @param array $values
     */
    protected function insertValues(array $values): void
    {
        $db = $this->getDb();
        $fields = $this->getValueFields();

        foreach ($values as $name => $localizedValues) {
            // Values reach diDB::insertIgnore() already escaped — it quotes them
            // but does not escape, same contract as \diModel::saveToDb().
            $record = ['name' => $db->escape_string((string) $name)];
            $localizedValues = array_values((array) $localizedValues);

            foreach ($fields as $i => $field) {
                $record[$field] = $db->escape_string(
                    (string) ($localizedValues[$i] ?? '')
                );
            }

            $db->insertIgnore($this->getLocalizationTable(), $record);
        }
    }

    public function down()
    {
        $this->getDb()->delete(
            $this->getLocalizationTable(),
            "WHERE {$this->getDb()->escapeField('name')}" .
                $this->getDb()::in($this->names)
        );
    }
}
