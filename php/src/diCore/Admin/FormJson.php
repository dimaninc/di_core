<?php

namespace diCore\Admin;

use diCore\Helper\ArrayHelper;
use diCore\Traits\BasicCreate;

class FormJson
{
    use BasicCreate;

    /** @var array */
    protected $schema;
    /** @var mixed */
    protected $value;
    /** @var string */
    protected $masteField;
    /** @var \diCore\Admin\Form */
    protected $Form;

    public static function buildHtml(array $options)
    {
        $FJ = static::basicCreate($options);

        return $FJ->getHtml();
    }

    public function __construct(array $options)
    {
        $this->populateOptions($options);
    }

    protected function populateOptions(array $options)
    {
        $options = extend(
            [
                'schema' => null,
                'jsonValue' => null,
                'Form' => null,
                'masterField' => null,
            ],
            $options
        );

        if (!$options['schema']) {
            throw new \Exception('Schema not defined');
        }

        if (!$options['Form']) {
            throw new \Exception('Form not defined');
        }

        if (!$options['masterField']) {
            throw new \Exception('masterField not defined');
        }

        $this->schema = $options['schema'];
        $this->value = $options['jsonValue']
            ? json_decode($options['jsonValue'], true)
            : null;
        $this->masteField = $options['masterField'];
        $this->Form = $options['Form'];

        return $this;
    }

    protected function getForm()
    {
        return $this->Form;
    }

    protected function getLanguage()
    {
        return $this->getForm()
            ->getX()
            ->getLanguage();
    }

    protected function getValue($field)
    {
        return ArrayHelper::get($this->value, $field);
    }

    protected function getInputTitle(string $field, array $props)
    {
        return $props['title'][$this->getLanguage()] ?? ($props['title'] ?? $field);
    }

    protected function getInputHtml(string $field, array $props)
    {
        $attrs = [
            'type' => 'text',
            'name' => "{$this->masteField}__$field",
            'data-field' => $field,
            'value' => $this->getValue($field),
        ];

        $attrStr = ArrayHelper::toAttributesString(
            $attrs,
            true,
            ArrayHelper::ESCAPE_HTML
        );

        return "<input $attrStr />";
    }

    protected function getRowHtml(string $field, array|string $props)
    {
        if (!is_array($props)) {
            $props = ['type' => $props];
        }

        $title = $this->getInputTitle($field, $props);
        $input = $this->getInputHtml($field, $props);

        return "<div class='diadminform-json-title'>$title</div><div class='diadminform-json-input'>$input</div>";
    }

    protected function getMasterInput()
    {
        $attrs = [
            'type' => 'hidden',
            'data-type' => 'master-input',
            'name' => $this->masteField,
            'value' => json_encode($this->value),
        ];

        $attrStr = ArrayHelper::toAttributesString(
            $attrs,
            true,
            ArrayHelper::ESCAPE_HTML
        );

        return "<input $attrStr />";
    }

    protected function wrapHtml($html)
    {
        $masterInput = $this->getMasterInput();

        return "<div class='diadminform-json-wrapper'>$masterInput$html</div>";
    }

    public function getHtml()
    {
        $rows = [];

        foreach ($this->schema as $field => $props) {
            $rows[] = $this->getRowHtml($field, $props);
        }

        return $this->wrapHtml(join("\n", $rows));
    }
}
