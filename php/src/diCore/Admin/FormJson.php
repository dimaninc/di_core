<?php

namespace diCore\Admin;

use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;
use diCore\Traits\BasicCreate;

class FormJson
{
    use BasicCreate;

    /** @var array */
    protected $schema;
    /** @var mixed */
    protected $value;
    /** @var string */
    protected $masterField;
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
        $this->masterField = $options['masterField'];
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
        switch ($props['type']) {
            case 'date_str':
            case 'time_str':
            case 'datetime_str':
                return $this->getDateTimeInputHtml($field, $props);

            case 'text':
                return $this->getTextInputHtml($field, $props);

            default:
            case 'string':
                return $this->getStringInputHtml($field, $props);
        }
    }

    protected function getStringInputHtml(string $field, array $props)
    {
        $attrs = [
            'type' => 'text',
            'name' => "{$this->masterField}__$field",
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

    protected function getTextInputHtml(string $field, array $props)
    {
        $value = StringHelper::out($this->getValue($field));

        $attrs = [
            'name' => "{$this->masterField}__$field",
            'data-field' => $field,
            'cols' => $props['cols'] ?? 80,
            'rows' => $props['rows'] ?? 10,
        ];

        $attrStr = ArrayHelper::toAttributesString(
            $attrs,
            true,
            ArrayHelper::ESCAPE_HTML
        );

        return "<textarea $attrStr>$value</textarea>";
    }

    protected function getDateTimeInputHtml(string $field, array $props)
    {
        $props = extend(
            [
                'usePlaceholder' => true,
                'calendarConfig' => [],
            ],
            $props
        );

        $dt = $this->Form::parseDateValue($this->getValue($field));

        $ph = [
            'dd' => $props['usePlaceholder']
                ? $this->Form::L('placeholder.date.day')
                : '',
            'dm' => $props['usePlaceholder']
                ? $this->Form::L('placeholder.date.month')
                : '',
            'dy' => $props['usePlaceholder']
                ? $this->Form::L('placeholder.date.year')
                : '',
            'th' => $props['usePlaceholder']
                ? $this->Form::L('placeholder.time.hour')
                : '',
            'tm' => $props['usePlaceholder']
                ? $this->Form::L('placeholder.time.minute')
                : '',
            'ts' => $props['usePlaceholder']
                ? $this->Form::L('placeholder.time.second')
                : '',
        ];

        $d =
            "<input type=\"text\" name=\"{$field}[dd]\" id=\"{$field}[dd]\" value=\"{$dt['dd']}\" size=\"2\" placeholder=\"{$ph['dd']}\">" .
            "<span class='date-sep'>.</span>" .
            "<input type=\"text\" name=\"{$field}[dm]\" id=\"{$field}[dm]\" value=\"{$dt['dm']}\" size=\"2\" placeholder=\"{$ph['dm']}\">" .
            "<span class='date-sep'>.</span>" .
            "<input type=\"text\" name=\"{$field}[dy]\" id=\"{$field}[dy]\" value=\"{$dt['dy']}\" size=\"4\" placeholder=\"{$ph['dy']}\">";

        $t =
            "<input type=\"text\" name=\"{$field}[th]\" id=\"{$field}[th]\" value=\"{$dt['th']}\" size=\"2\" placeholder=\"{$ph['th']}\">" .
            "<span class='time-sep'>:</span>" .
            "<input type=\"text\" name=\"{$field}[tm]\" id=\"{$field}[tm]\" value=\"{$dt['tm']}\" size=\"2\" placeholder=\"{$ph['tm']}\">";

        $date = in_array($props['type'], [
            'date',
            'date_str',
            'datetime',
            'datetime_str',
        ]);
        $time = in_array($props['type'], [
            'time',
            'time_str',
            'datetime',
            'datetime_str',
        ]);
        $input = join('&nbsp;', array_filter([$date ? $d : '', $time ? $t : '']));

        if ($date) {
            $uid = "{$this->getForm()->getTable()}__{$this->masterField}__$field";

            $config = extend(
                $props['calendarConfig'] ?: [
                    'months_to_show' => 1,
                    'date1' => $field,
                    'able_to_go_to_past' => true,
                ],
                [
                    'instance_name' => "c_$uid",
                    'position_base' => 'parent',
                    'language' => $this->getLanguage(),
                ]
            );
            $jsonConfig = json_encode($config);

            $input .= <<<EOF
<span class="calendar-controls">
    <button type="button" onclick="c_{$uid}.toggle();" class="calendar-toggle w_hover">{$this->Form::L(
                'calendar'
            )}</button>
    <button type="button" onclick="c_{$uid}.clear();" class="calendar-clear w_hover" data-purpose="reset">{$this->Form::L(
                'clear'
            )}</button>
</span>

<script type="text/javascript">
var c_{$uid} = new diCalendar($jsonConfig);
</script>
EOF;
        }

        return $input;
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
            'name' => $this->masterField,
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
