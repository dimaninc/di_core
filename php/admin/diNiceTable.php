<?php
/*
    // dimaninc

    // 2015/05/12
        * moved to lib

    // 2014/10/21
        * total reorganizing #2 (including js)

    // 2015/04/09
        * diNiceTable::shim deleted
        * ::$pn added
        * lite refactoring

    // 2011/03/18
        * total reorganizing

    // 2006/10/14
        * diNiceTable::lng added
        * $btn_ar => $dint_btn_ar
        * basic methods diNiceTable::edit_btn_cell(),::del_btn_cell,::toggler_btn_cell(),
          ::up_btn_cell(),::down_btn_cell() added

    // 2006/07/18
        * "active" button added
        * "top" button added
        * "manage" button added

    // 2006/01/30
        * id of each TR is able to be written

    // 2006/01/22
        * "raw"->"row" error fixed
        * $row_properties added

    // 2006/01/14
        * DI_NT_NOT_PRINT_HEADLINE added. equals to DI_NT_NO_PRINT_HEADLINE

    // 2006/01/12
        * diNiceTable::shim added
        * diNiceTable::set_shim() added

    // 2006/01/11
        * $btn_ar changed: english alt text added

    // 2006/01/06
        * id_cell(), *_btn_cell() methods added
          NOTE: almost all these methods must be overridden!
        * DI_NT_NO_PRINT_HEADLINE and DI_NT_PRINT_HEADLINE constants added

    // 2005/12/25
        * fucking bullshit removed. now the table is becoming really nice
*/

use diCore\Admin\Base;
use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;

class diNiceTable
{
    const NO_HEADLINE = 0;
    const PRINT_HEADLINE = 1;

    const ROW_ANCHOR_PREFIX = 'row';

    /**
     * @var diPagesNavy
     */
    private $pn;

    /**
     * @var diDB
     */
    private $db;

    /**
     * @var diModel
     */
    private $rowModel;

    public $properties; // nice table properties (color, alignment, ...)
    public $cols; // columns info (title, width, color, alignment, ...)
    public $col_idx; // index of current column
    public $row_idx; // index of current row
    public $row_class_prefix; // 'level1','level2',... - to form classes like 'level1' or 'level1_num'
    private $row_id_prefix;
    public $lng;
    public $table; // mysql table
    public $page; // current page
    private $collapsedIds;

    private $anchorPlaced; // this gets reset on every ->openRow()

    private $treeView = false;

    /** @var string|null Used for editBtn href, if differs with table */
    private $formPathBase;

    /** @var \diAdminList */
    protected $List;

    /**
     * @param string $table
     * @param diPagesNavy $pn
     * @param string $lng
     */
    public function __construct($table = '', $pn = false, $lng = 'ru')
    {
        if ($table instanceof \diAdminList) {
            $this->List = $table;
            $this->table = $this->List->getTable();
        } else {
            $this->table = $table;
        }

        $this->db = \diModel::createForTable($this->table)
            ::getConnection()
            ->getDb();

        if (is_object($pn)) {
            $this->pn = $pn;
            $this->page = $pn->page;
        } else {
            $this->pn = null;
            $this->page = $pn ?: 1;
        }

        $this->properties = [];

        $this->cols = [];
        $this->col_idx = 0;

        $this->lng = $lng;

        $this->collapsedIds = isset($_COOKIE['list_collapsed'][$this->table])
            ? explode(',', $_COOKIE['list_collapsed'][$this->table])
            : [];
    }

    public function getList()
    {
        return $this->List;
    }

    public function L($token)
    {
        return $this->getList() ? $this->getList()->L($token) : $token;
    }

    protected function getDb()
    {
        return $this->db;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getRowModel()
    {
        return $this->rowModel;
    }

    public function getRowId()
    {
        return $this->getRowModel()->getId();
    }

    public function getLanguage()
    {
        return $this->lng;
    }

    public static function getRowAnchorName($id)
    {
        return self::ROW_ANCHOR_PREFIX . $id;
    }

    protected function setRowRec($r)
    {
        if ($r instanceof diModel) {
            $this->rowModel = $r;
        } else {
            $type = \diTypes::getNameByTable($this->getTable());
            $this->rowModel =
                $type && diModel::existsFor($type)
                    ? \diModel::create($type, $r)
                    : new diModel($r, $this->getTable());
        }

        if ($this->getRowModel()->exists()) {
            $this->treeView = $this->getRowModel()->exists('level_num');
        }

        return $this;
    }

    /** @deprecated */
    public function get_pn()
    {
        return $this->getPn();
    }

    public function getPn()
    {
        return $this->pn;
    }

    public function getNavyBlock()
    {
        return '<p class="navy">' .
            $this->pn->print_pages("{$_SERVER['SCRIPT_NAME']}?path=$this->table") .
            '</p>';
    }

    public function addColumn($title = '&nbsp;', $moreParams = [], $field = null)
    {
        if (!is_array($moreParams)) {
            $moreParams = [];
        }

        $this->cols[] = [
            'title' => $title,
            'more_params' => $moreParams,
            'field' => $field,
        ];

        return $this;
    }

    public function textCell($text, $moreTdParams = [])
    {
        $td_params = [
            //'class' => $this->row_class_prefix
        ];

        $td_params = array_merge($td_params, $moreTdParams);

        return $this->fillCell($text, $td_params);
    }

    public function textLinkCell($text, $more_td_params = [])
    {
        $href = Base::getPageUri($this->getFormPathBase(), 'form', [
            'id' => $this->getRowModel()->getId(),
            // 'edit' => 1,
        ]);

        $tdAttrs = [
            'data-href' => $href,
            //'class' => $this->row_class_prefix,
        ];

        $tdAttrs = array_merge($tdAttrs, $more_td_params);

        return $this->fillCell($text, $tdAttrs);
    }

    protected function btnCell($text)
    {
        return $this->fillCell($text, ['class' => 'btn']);
    }

    protected function fillCell($text, $td_params = [])
    {
        $s = '<td';

        $td_params = array_merge(
            $this->cols[$this->col_idx]['more_params'],
            $td_params
        );

        foreach ($td_params as $p => $v) {
            $s .= $p ? " $p=\"$v\"" : " $v";
        }

        if (!$this->anchorPlaced) {
            $anchorName = self::getRowAnchorName($this->getRowModel()->getId());
            $anchor = "<a name='$anchorName' class='anchor'></a>";

            $text = $anchor . $text;

            $this->anchorPlaced = true;
        }

        $s .= ">$text</td>\n";

        $this->col_idx++;

        return $s;
    }

    /**
     * @param string $action
     * @param array|string $options
     */
    public function getButton($action, $options = [])
    {
        if (gettype($options) == 'string') {
            $options = [
                'href' => $options,
            ];
        }

        return diNiceTableButtons::getButton(
            $action,
            extend(
                [
                    'language' => $this->getLanguage(),
                    'customTitles' => $this->getList()
                        ->getAdminPage()
                        ->getCustomListButtonTitles(),
                ],
                $options
            )
        );
    }

    public function getEmptyButton()
    {
        return diNiceTableButtons::getButtonHtml();
    }

    public function openTable($print_headline = null)
    {
        if ($print_headline === null) {
            $print_headline = self::PRINT_HEADLINE;
        }

        $this->row_idx = 0;
        $s = '';

        $s .=
            "<div class=\"dinicetable_div\">" .
            "<table class=\"dinicetable\" data-table=\"$this->table\"><tbody>\n";

        if ($print_headline == self::PRINT_HEADLINE) {
            $headCols = [];

            $s .= $this->openRow(null, '', 'headline');

            foreach ($this->cols as $col) {
                $headCols[] = $this->textCell($col['title'], [
                    'data-field' => $col['field'] ?? null,
                ]);
            }

            $s .= join('', $headCols) . $this->closeRow();
        }

        return $s;
    }

    public function closeTable()
    {
        $s = "</tbody></table></div>\n";

        return $s;
    }

    public function openRow(
        $r = null,
        $row_id_prefix = 'section',
        $row_class_prefix = '',
        $options = []
    ) {
        $this->setRowRec($r);

        if (is_array($row_id_prefix) && !$row_class_prefix && !$options) {
            $options = $row_id_prefix;

            $options = extend(
                [
                    'idPrefix' => 'section',
                    'classPrefix' => '',
                ],
                $options
            );

            $row_id_prefix = $options['idPrefix'];
            $row_class_prefix = $options['classPrefix'];
        }

        $options = extend(
            [
                'classes' => null,
                'attributes' => [],
            ],
            $options
        );

        if (is_string($options['classes'])) {
            $classes = explode(' ', $options['classes']);
        } elseif (is_callable($options['classes'])) {
            $classes = $options['classes']($this->getRowModel(), $this);
        } elseif (is_array($options['classes'])) {
            $classes = $options['classes'];
        } else {
            $classes = [];
        }

        if (is_string($options['attributes'])) {
            $attributes = explode(' ', $options['attributes']);
        } elseif (is_callable($options['attributes'])) {
            $attributes = $options['attributes']($this->getRowModel(), $this);
        } elseif (is_array($options['attributes'])) {
            $attributes = $options['attributes'];
        } else {
            $attributes = [];
        }

        $id = StringHelper::out($this->getRowModel()->getId() ?: '');

        $this->col_idx = 0;
        $this->anchorPlaced = false;
        $this->row_class_prefix =
            $row_class_prefix ?:
            'level' . ((int) $this->getRowModel()->get('level_num') + 1);
        $this->row_id_prefix = $row_id_prefix;

        $classes[] = $this->row_class_prefix;

        if (
            $this->getRowModel()->has('parent') &&
            in_array($this->getRowModel()->get('parent'), $this->collapsedIds)
        ) {
            $classes[] = 'collapsed';
        }

        if ($this->row_id_prefix) {
            $attributes['id'] = $this->row_class_prefix . ($id ?: '');
        }

        $attributes['class'] = join(' ', $classes);

        return sprintf(
            "<tr %s data-role=\"row\" data-id=\"%s\" data-level=\"%d\">\n",
            ArrayHelper::toAttributesString(
                $attributes,
                true,
                ArrayHelper::ESCAPE_HTML
            ),
            $id,
            (int) $this->getRowModel()->get('level_num')
        );
    }

    public function closeRow()
    {
        $this->row_idx++;

        return "</tr>\n\n";
    }

    /* methods of printing each type of table cells: all possible buttons, etc. */
    /* basic methods implemented, all of them may be overridden */

    public function idCell(
        $show_id = true,
        $show_checkbox = false,
        $show_expand_collapse = true
    ) {
        if (!$this->getRowModel()->exists()) {
            throw new Exception('diNiceTable::idCell(): no current rec');
        }

        $inner = '';

        if ($show_id) {
            $inner .= $this->getRowId();
        }

        if ($show_checkbox) {
            $inner .= " <input type=\"checkbox\" data-purpose=\"toggle\" data-id=\"{$this->getRowId()}\">";
        }

        if ($this->getRowModel()->exists('level_num') && $show_expand_collapse) {
            $expandClassName = in_array(
                $this->getRowModel()->getId(),
                $this->collapsedIds
            )
                ? 'expand'
                : 'collapse';

            $inner .= "<u class=\"tree {$expandClassName}\"></u>";
        }

        return $this->textCell($inner, ['class' => 'id']);
    }

    public function setFormPathBase($formPathBase)
    {
        $this->formPathBase = $formPathBase;

        return $this;
    }

    public function getFormPathBase()
    {
        return $this->formPathBase ?: $this->getTable();
    }

    public function editBtnCell()
    {
        $queryParams = [
            'id' => $this->getRowModel()->getId(),
            //'edit' => 1,
        ];

        $href = Base::getPageUri($this->getFormPathBase(), 'form', $queryParams);

        return $this->btnCell($this->getButton('edit', $href));
    }

    public function delBtnCell()
    {
        return $this->btnCell($this->getButton('del'));
    }

    public function toggleBtnCell($field, $active = true)
    {
        return $active
            ? $this->btnCell(
                $this->getButton($field, [
                    'state' => $this->getRowModel()->get($field),
                ])
            )
            : $this->emptyBtnCell();
    }

    public function upBtnCell()
    {
        return $this->btnCell($this->getButton('up'));
    }

    public function downBtnCell()
    {
        return $this->btnCell($this->getButton('down'));
    }

    public function orderBtnCell()
    {
        $o = $this->getRowModel()->get('order_num');
        $input =
            "<div class=\"nicetable-order\" data-prev-value=\"$o\">" .
            "<input type=\"text\" name=\"order\" value=\"$o\" size=\"5\">" .
            '<button type="button">✅</button>' .
            '</div>';

        return $this->fillCell($input);
    }

    public function playBtnCell($opts = [])
    {
        return $this->btnCell($this->getButton('play', $opts));
    }

    public function rollbackBtnCell($opts = [])
    {
        return $this->btnCell($this->getButton('rollback', $opts));
    }

    public function commentsBtnCell()
    {
        $r = $this->getDb()->r(
            'comments',
            "WHERE target_type='" .
                diTypes::getId($this->getTable()) .
                "' and target_id='{$this->getRowModel()->getId()}'",
            'COUNT(id) AS cc'
        );

        $s = $r->cc
            ? $this->getButton('comments', [
                'text' => $r->cc,
            ])
            : $this->getEmptyButton();

        return $this->btnCell($s);
    }

    public function picBtnCell($opts = [])
    {
        switch ($this->getTable()) {
            case 'albums':
                $path = 'photos';
                $suffix = "?album_id={$this->getRowModel()->getId()}";
                break;

            default:
                $path = '';
                $suffix = '';
                break;
        }

        if (!$opts['href']) {
            if (!$path) {
                throw new Exception('picBtnCell path not defined');
            }

            $opts['href'] =
                '/' . diAdmin::getSubFolder() . '/' . $path . '/' . $suffix;
        }

        return $this->btnCell($this->getButton('pic', $opts['href']));
    }

    public function videoBtnCell($opts = [])
    {
        switch ($this->getTable()) {
            case 'albums':
                $path = 'videos';
                $suffix = "?album_id={$this->getRowModel()->getId()}";
                break;

            default:
                $path = '';
                $suffix = '';
                break;
        }

        if (!$opts['href']) {
            if (!$path) {
                throw new Exception('videoBtnCell path not defined');
            }

            $opts['href'] =
                '/' . diAdmin::getSubFolder() . '/' . $path . '/' . $suffix;
        }

        return $this->btnCell($this->getButton('video', $opts['href']));
    }

    public function manageBtnCell($href = null)
    {
        switch ($this->getTable()) {
            case 'albums':
                $path = 'photos';
                $suffix = "?album_id={$this->getRowModel()->getId()}";
                break;

            default:
                $path = '';
                $suffix = '';
                break;
        }

        if (is_null($href)) {
            if (!$path) {
                throw new Exception('manageBtnCell path not defined');
            }

            $opts['href'] =
                '/' . \diAdmin::getSubFolder() . '/' . $path . '/' . $suffix;
        }

        return $this->btnCell($this->getButton('manage', $href));
    }

    public function hrefCell($href = '', $title = '')
    {
        if (!$href) {
            switch ($this->getRowModel()->get('type')) {
                case 'href':
                    $href = $this->getRowModel()->get('menu_title');
                    break;

                case 'nohref':
                    $href = '';
                    break;

                default:
                    $href = "/{$this->getRowModel()->getSlug()}/";
                    break;
            }
        }

        if (!$title) {
            $title = $this->L('open');
        }

        $s = $href
            ? " <a target=\"_blank\" href=\"$href\" title=\"$title\"></a>"
            : '';

        return $this->textCell($s, [
            'class' => 'href',
        ]);
    }

    public function createBtnCell($maxLevelNum, $queryParams = [])
    {
        if ($this->getRowModel()->get('level_num') < $maxLevelNum) {
            $s = $this->getButton(
                'create',
                Base::getPageUri(
                    $this->getTable(),
                    'form',
                    extend(
                        [
                            'parent' => $this->getRowModel()->getId(),
                        ],
                        $queryParams
                    )
                )
            );
        } else {
            $s = $this->getEmptyButton();
        }

        return $this->btnCell($s);
    }

    public function printBtnCell($state = 1)
    {
        $s = $state
            ? $this->getButton(
                'print',
                Base::getPageUri($this->getTable(), 'form', [
                    'id' => $this->getRowModel()->getId(),
                    'print' => 1,
                ])
            )
            : $this->getEmptyButton();

        return $this->btnCell($s);
    }

    public function toShowContentBtnCell($levelNumsToShow = [])
    {
        if (!is_array($levelNumsToShow)) {
            $levelNumsToShow = [$levelNumsToShow];
        }

        return in_array($this->getRowModel()->get('level_num'), $levelNumsToShow)
            ? $this->toggleBtnCell('to_show_content')
            : $this->emptyBtnCell();
    }

    public function emptyBtnCell()
    {
        return $this->btnCell($this->getEmptyButton());
    }
}
