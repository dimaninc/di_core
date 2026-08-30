<?php

namespace diCore\Admin\Page;

use diCore\Data\Configuration as Cfg;
use diCore\Entity\AdminTableEditLog\Collection as TableEditLogs;
use diCore\Entity\AdminTableEditLog\Model as TableEditLog;
use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;

class Configuration extends \diCore\Admin\BasePage
{
    /**
     * Settings have no row to hang their history on, and the log needs one:
     * Model::validate() requires a non-empty target_id and diModel::has() counts 0
     * as empty. The whole settings table is a single logical record, so it gets a
     * single synthetic id – nothing else writes target_table = 'configuration'.
     */
    const EDIT_LOG_TARGET_ID = 1;

    /**
     * Nothing else bounds this log: it is filtered by table only and grows with
     * every save, forever. See BasePage::createEditLogCollection() for why a page
     * size (unlike lazy chunks) is safe here.
     */
    const EDIT_LOG_PAGE_SIZE = 50;

    protected $vocabulary = [
        'ru' => [
            'delete' => 'Удалить',
            'form.submit.title' => 'Сохранить',
            'form.cancel.title' => 'Закрыть',
            'saved' => 'Изменения сохранены',
        ],
        'en' => [
            'delete' => 'Delete',
            'form.submit.title' => 'Save',
            'form.cancel.title' => 'Cancel',
            'saved' => 'Changes saved',
        ],
    ];

    protected function initTable()
    {
        $this->setTable('configuration');
    }

    public function printList()
    {
        Cfg::getInstance()->loadAllFromDB();

        $this->getTpl()
            ->define('`configuration', ['page', 'saved_message'])
            ->assign([
                'SUBMIT_TITLE' => $this->getVocabularyTerm('form.submit.title'),
                'CANCEL_TITLE' => $this->getVocabularyTerm('form.cancel.title'),
                'SAVED' => $this->getVocabularyTerm('saved'),
            ]);

        $this->printConfigurationTable();

        $saved = \diRequest::get('saved', 0);

        if ($saved) {
            $this->getTpl()->parse('saved_message');
        } else {
            $this->getTpl()->assign([
                'SAVED_MESSAGE' => '',
            ]);
        }
    }

    public function renderForm()
    {
        throw new \Exception('No form in ' . get_class($this));
    }

    public function printConfigurationTable()
    {
        Cfg::getInstance()
            ->setAdminPage($this)
            ->checkOtherTabInList(true);

        $this->getTpl()->define('`configuration', [
            'head_tab_row',

            'tab_page',
            'property_row',

            'note_row',
            'notes_block',
        ]);

        $tabPagesAr = [];

        foreach (Cfg::getData() as $k => $v) {
            if (!isset($v['title']) || Cfg::hasFlag($k, 'hidden')) {
                continue;
            }

            $titleSuffix = '';
            $valueSuffix = '';
            $val = $v['value'] ?? '';

            $this->getTpl()->clear('P_NOTE_ROWS');

            $htmlFieldName = str_replace(
                array_keys(Cfg::$inputNameReplaces),
                array_values(Cfg::$inputNameReplaces),
                $k
            );

            switch ($v['type']) {
                case 'checkbox':
                    $checked = $val ? " checked=\"checked\"" : '';
                    $value = "<input type=\"checkbox\" id='$k' name=\"$htmlFieldName\" value=\"1\" {$checked}>";
                    break;

                case 'select':
                    $prefix_ar = isset($v['select_prefix_ar'])
                        ? $v['select_prefix_ar']
                        : [];
                    $suffix_ar = isset($v['select_suffix_ar'])
                        ? $v['select_suffix_ar']
                        : [];
                    $template_text = isset($v['select_template_text'])
                        ? $v['select_template_text']
                        : '%title%';
                    $template_value = isset($v['select_template_value'])
                        ? $v['select_template_value']
                        : '%id%';

                    $value = \diSelect::fastCreate(
                        $htmlFieldName,
                        $val,
                        $v['select_values'],
                        $prefix_ar,
                        $suffix_ar,
                        $template_text,
                        $template_value
                    );
                    break;

                case 'text':
                    $attrs = [
                        'name' => $htmlFieldName,
                        'id' => $k,
                    ];

                    if (isset($v['rows'])) {
                        $attrs['rows'] = $v['rows'];
                    }

                    $attrs = ArrayHelper::toAttributesString($attrs);

                    $value = "<textarea {$attrs}>{$val}</textarea>";
                    break;

                case 'pic':
                case 'file':
                    $folder = Cfg::getInstance()->getFolder();
                    $ff = \diPaths::fileSystem() . $folder . $val;
                    $ff_orig = '/' . $folder . $val;
                    $path = '/' . $folder;
                    $ext = strtoupper(StringHelper::fileExtension($ff));
                    $info = "<a href=\"$path$val\" target=_blank>$val</a>";
                    $ff_w = $ff_h = $ff_t = 0;

                    if (is_file($ff)) {
                        [$ff_w, $ff_h, $ff_t] = getimagesize($ff);
                        $ff_s = str_filesize(filesize($ff));
                        $info .= $ff_w || $ff_h ? ", {$ff_w}x{$ff_h}px" : '';
                        $info .= ", $ff_s";
                    }

                    if ($v['type'] == 'pic') {
                        $imgTag =
                            $ff_t == 4 || $ff_t == 13
                                ? "<script type=\"text/javascript\">run_movie(\"$path$val\", \"$ff_w\", \"$ff_h\", \"opaque\");</script>"
                                : "<img src='$path$val' border='0'>";

                        //$ff_w2 = $ff_w > 500 ? 500 : $ff_w;
                        $imgTag = "<div class='uploaded-pic'>$imgTag</div>";
                        // style='width: {$ff_w2}px; overflow-x: auto;'
                    } elseif (in_array($ext, ['MP4', 'M4V', 'OGV', 'WEBM', 'AVI'])) {
                        // video
                        // $mime_type = \diCore\Admin\Form::get_mime_type_by_ext($ext);
                        // type=\"$mime_type\"
                        $imgTag = "<div><video preload=\"none\" controls width=400 height=225><source src=\"$ff_orig\"></video></div>";
                    } else {
                        $imgTag = '';
                    }

                    $valueSuffix = $val
                        ? sprintf(
                            '<div>%s</div><div class="file-info">%s <a href="%s" data-purpose="del">%s</a></div>',
                            $imgTag,
                            $info,
                            \diLib::getAdminWorkerPath(
                                'configuration',
                                'del_pic',
                                $k
                            ),
                            $this->getVocabularyTerm('delete')
                        )
                        : '';

                    $value = "<input type=\"file\" name=\"$htmlFieldName\" id='$k' size=\"40\">";
                    break;

                default:
                    $value =
                        isset($v['flags']) && in_array('static', $v['flags'])
                            ? StringHelper::out($val, true)
                            : "<input type=\"text\" name=\"$htmlFieldName\" id='$k' value=\"" .
                                StringHelper::out($val, true) .
                                "\">";
                    break;
            }

            $tab = $v['tab'] ?? Cfg::getInstance()->getOtherTabName();

            if (!isset($tabPagesAr[$tab])) {
                $tabPagesAr[$tab] = '';
            }

            if (!empty($v['notes'])) {
                if (!is_array($v['notes'])) {
                    $v['notes'] = [$v['notes']];
                }

                $this->getTpl()->clear_parse('NOTE_ROWS');

                foreach ($v['notes'] as $_note) {
                    $this->getTpl()->assign([
                        'NOTE' => $_note,
                    ]);

                    $this->getTpl()->parse('P_NOTE_ROWS', '.note_row');
                }

                $this->getTpl()->parse('P_NOTES_BLOCK', 'notes_block');
            } else {
                $this->getTpl()
                    ->clear_parse('P_NOTES_BLOCK')
                    ->assign([
                        'P_NOTES_BLOCK' => '',
                    ]);
            }

            $this->getTpl()->assign(
                [
                    'TITLE' => $v['title'] . $titleSuffix,
                    'VALUE' => $value . $valueSuffix,
                    'FIELD' => $k,
                ],
                'P_'
            );

            $tabPagesAr[$tab] .= $this->getTpl()->parse('property_row');
        }

        $tabNames = array_keys(Cfg::getInstance()->getTabsAr());

        if ($this->shouldPrintEditLog()) {
            // appended, so the first tab stays a settings one
            $tabNames[] = TableEditLog::ADMIN_TAB_NAME;
        }

        $this->getTpl()->assign([
            'TABS_LIST' => join(',', $tabNames),
            'FIRST_TAB' => current($tabNames),
            'WORKER_URI' => \diLib::getAdminWorkerPath('configuration', 'store'),
        ]);

        foreach (Cfg::getInstance()->getTabsAr() as $k => $v) {
            if (empty($tabPagesAr[$k])) {
                continue;
            }

            $this->getTpl()
                ->assign(
                    [
                        'NAME' => $k,
                        'TITLE' => $v,
                        'PROPERTY_ROWS' => $tabPagesAr[$k],
                    ],
                    'T_'
                )
                ->process('HEAD_TAB_ROWS', '.head_tab_row')
                ->process('TAB_PAGES', '.tab_page');
        }

        $this->printEditLogTab();
    }

    /**
     * The log tab, after the settings groups. Its own block template: tab_page.htm
     * wraps the content into <table class="grid">, while the log is a <ul>. The div
     * has to end up inside {TAB_PAGES} – diConfiguration.js hands diTabs the
     * `form [data-purpose="tab-pages"]` container. There are no inputs in it, so
     * sitting inside the settings <form> is harmless.
     *
     * @return $this
     */
    protected function printEditLogTab()
    {
        if (!$this->shouldPrintEditLog()) {
            return $this;
        }

        $this->getTpl()
            ->define('`configuration', ['edit_log_tab_page'])
            ->assign(
                [
                    'NAME' => TableEditLog::ADMIN_TAB_NAME,
                    'TITLE' => TableEditLog::adminTabTitle($this->getLanguage()),
                    'CONTENT' => $this->renderEditLog(),
                ],
                'T_'
            )
            ->process('HEAD_TAB_ROWS', '.head_tab_row')
            ->process('TAB_PAGES', '.edit_log_tab_page');

        return $this;
    }

    /**
     * No getId() part: the settings are one logical record (EDIT_LOG_TARGET_ID),
     * and this page never has an id.
     */
    protected function shouldPrintEditLog()
    {
        return $this->useEditLog() && !$this->hideEditLog();
    }

    protected function createEditLogCollection()
    {
        return TableEditLogs::create()
            ->filterByTargetTable(Cfg::getInstance()->getTableName())
            ->orderById('DESC')
            ->setPageSize(static::EDIT_LOG_PAGE_SIZE);
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Настройки',
            'en' => 'Configuration',
        ];
    }

    public function addButtonNeededInCaption()
    {
        return false;
    }
}
