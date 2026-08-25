<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.10.2019
 * Time: 13:09
 */

namespace diCore\Database\Tool;

use diCore\Entity\Localization\Model;
use diCore\Tool\Localization;
use diCore\Tool\Logger;

abstract class LocalizationMigration extends Migration
{
    protected $names = [];

    /** @var Model|null */
    private $localizationModel;

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

    /**
     * The project's localization entity — source of the table and the languages.
     * Overriding it does NOT redirect updateCache(), which goes through the
     * entity's Collection.
     */
    protected function getLocalizationModel(): Model
    {
        // memoized: Model::create() walks every registered namespace with
        // class_exists(), and this is asked for once per token otherwise
        if ($this->localizationModel === null) {
            $this->localizationModel = Model::create();
        }

        return $this->localizationModel;
    }

    protected function getLocalizationTable(): string
    {
        return $this->getLocalizationModel()->getTable();
    }

    /**
     * Value columns to fill. Every project has its own set of languages, so the
     * list can only come from its model.
     *
     * @return string[]
     */
    protected function getValueFields(): array
    {
        return $this->getLocalizationModel()::getValueFields();
    }

    /**
     * Adds tokens, keeping what is already in the table.
     *
     * Keyed BY LANGUAGE, never positional: a positional list has to agree with
     * a column order defined in another repository, and one language inserted
     * in the middle silently shifts every translation into its neighbour.
     *
     * `$strict` demands every language of the model; without it the missing
     * ones stay empty and are logged. Refused in both modes: an empty list, a
     * nameless token, a positional list and a non-scalar value.
     *
     * INSERT IGNORE: these migrations get re-run on databases ahead of them,
     * and an editor's translation must survive.
     *
     * Does NOT fill $names — down() runs on an instance whose up() never did.
     *
     * @param array $values token => [language => value]
     * @throws \InvalidArgumentException
     */
    protected function insertValues(array $values, bool $strict = true): void
    {
        $db = $this->getDb();
        $table = $this->getLocalizationTable();
        $fields = $this->getValueFields();

        // all validated before the first write: a bad token in the middle
        // would otherwise leave the migration half-applied
        $records = [];

        foreach ($values as $name => $byLanguage) {
            $records[] = $this->buildValueRecord(
                (string) $name,
                $byLanguage,
                $strict,
                $fields
            );
        }

        foreach ($records as $record) {
            $db->insertIgnore($table, $record);
        }
    }

    /**
     * One row with every value column present: an omitted one is written as '',
     * so a re-run cannot depend on column defaults.
     *
     * @param string[] $fields value columns of the table
     * @throws \InvalidArgumentException
     */
    private function buildValueRecord(
        string $name,
        $byLanguage,
        bool $strict,
        array $fields
    ): array {
        $db = $this->getDb();
        $model = $this->getLocalizationModel();

        // down() deletes by $names, and diDB::in([]) degenerates into `in ('')`
        // — a nameless token is exactly what an empty rollback would delete
        if ($name === '') {
            throw new \InvalidArgumentException('Localization token has no name');
        }

        if (!is_array($byLanguage) || !$byLanguage) {
            throw new \InvalidArgumentException(
                "Localization token '$name' must be a non-empty array keyed by " .
                    "language, e.g. ['ru' => …, 'en' => …]"
            );
        }

        $record = array_fill_keys($fields, '');
        $filled = [];

        foreach ($byLanguage as $language => $value) {
            // a numeric key is the old positional call — caught before it
            // reaches an invented `0_value` column
            if (!is_string($language) || $language === '') {
                throw new \InvalidArgumentException(
                    "Localization token '$name' must be keyed by language, " .
                        "e.g. ['ru' => …, 'en' => …]"
                );
            }

            // (string) of an array is the literal "Array" plus a warning, which
            // would be stored as the translation
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException(
                    "Localization token '$name': the '$language' value is " .
                        gettype($value) .
                        ', expected a string'
                );
            }

            $field = $model::getLocalizedFieldName('value', $language);

            if (!array_key_exists($field, $record)) {
                if ($strict) {
                    throw new \InvalidArgumentException(
                        "Localization token '$name': language '$language' has " .
                            "no column '$field' in " .
                            $this->getLocalizationTable()
                    );
                }

                $this->logLocalizationIssue(
                    "token '$name': skipped language '$language', no column '$field'"
                );

                continue;
            }

            // insertIgnore() quotes but does not escape, same as saveToDb()
            $record[$field] = $db->escape_string((string) $value);
            $filled[] = $field;
        }

        if (!$filled) {
            throw new \InvalidArgumentException(
                "Localization token '$name' has no values for any known language"
            );
        }

        $missing = array_diff(array_keys($record), $filled);

        if ($missing) {
            $message = "token '$name': no value for " . join(', ', $missing);

            if ($strict) {
                throw new \InvalidArgumentException(
                    "Localization $message. Pass strict = false to allow a subset."
                );
            }

            $this->logLocalizationIssue($message);
        }

        return ['name' => $db->escape_string($name)] + $record;
    }

    /** A skipped translation is a log line, not a failed deploy. */
    protected function logLocalizationIssue(string $message): void
    {
        try {
            Logger::getInstance()->log($message, 'localization');
        } catch (\Throwable $e) {
            // the log folder is not always writable from CLI, and swallowing
            // this would make the promise of "logged, never silent" a lie
            error_log("Localization migration: $message ({$e->getMessage()})");
        }
    }

    public function down()
    {
        $db = $this->getDb();
        // diDB::in() quotes its items but does not escape them, and a token name
        // is prose too — "it's" would otherwise break the rollback query
        $names = array_map(function ($name) use ($db) {
            return $db->escape_string((string) $name);
        }, array_values($this->names));

        $db->delete(
            $this->getLocalizationTable(),
            "WHERE {$db->escapeField('name')}" . $db::in($names)
        );
    }
}
