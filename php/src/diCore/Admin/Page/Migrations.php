<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 14.06.2015
 * Time: 0:16
 */

namespace diCore\Admin\Page;

use diCore\Admin\Base;
use diCore\Admin\Form;
use diCore\Database\Tool\Migration;
use diCore\Database\Tool\MigrationsManager;
use diCore\Tool\CollectionCache;
use diCore\Entity\DiMigrationsLog\Model;

class Migrations extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'date',
                'dir' => 'DESC',
            ],
        ],
    ];

    /** @var MigrationsManager */
    private $Manager;

    const basePath = 'migrations';

    private $pseudoTable = self::basePath;

    protected function initTable()
    {
        $this->setTable($this->pseudoTable);

        switch ($this->getMethod()) {
            case 'log':
                $this->setTable($this->getManager()::logTable);
                break;
        }
    }

    protected function initPagesNavy()
    {
        if (!$this->PagesNavy && $this->isPagesNavyNeeded()) {
            $this->PagesNavy = new \diPagesNavy(
                $this->getTable(),
                $this->getCountPerPage(),
                0
            );
        }

        return $this;
    }

    protected function shouldPrintPagesNavy()
    {
        return false;
    }

    public function getManager()
    {
        if (!$this->Manager) {
            $this->Manager = MigrationsManager::basicCreate();
        }

        return $this->Manager;
    }

    protected function beforeRenderLog()
    {
        parent::beforeRenderList();

        return true;
    }

    protected function afterRenderLog()
    {
        parent::afterRenderList();
    }

    public function getMethodCaption($action)
    {
        if ($action == 'log') {
            return [
                'ru' => 'Журнал',
                'en' => 'Migrations log',
            ];
        }

        return parent::getMethodCaption($action);
    }

    public function renderLog()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'idx' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
                'noHref' => true,
            ],
            'name' => [
                'headAttrs' => [
                    'width' => '50%',
                ],
                'noHref' => true,
            ],
            'admin_id' => [
                'title' => 'Применил админ',
                'value' => function (Model $l) {
                    /** @var \diCore\Entity\Admin\Model $admin */
                    $admin = CollectionCache::getModel(
                        \diTypes::admin,
                        $l->getAdminId()
                    );

                    return $admin->exists() ? $admin->getLogin() : '&ndash;';
                },
                'headAttrs' => [
                    'width' => '30%',
                ],
                'noHref' => true,
            ],
            'direction' => [
                'title' => '',
                'value' => function (Model $l) {
                    return $l->getDirection() == Migration::UP ? '+' : '-';
                },
                'noHref' => true,
            ],
            'date' => [
                'title' => 'Когда',
                'value' => function (Model $l) {
                    return \diDateTime::simpleFormat($l->getDate());
                },
                'attrs' => [],
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
                'noHref' => true,
            ],
        ]);
    }

    protected function cacheDataForList()
    {
        parent::cacheDataForList();

        CollectionCache::addManual(
            \diTypes::admin,
            'id',
            $this->getListCollection()->map('admin_id')
        );

        return $this;
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'description' => [
                'title' => 'Описание',
                'headAttrs' => [
                    'width' => '60%',
                ],
                'bodyAttrs' => [
                    'class' => function (\diModel $m) {
                        return $m->has('folder') ? 'id-bg' : '';
                    },
                ],
                'noHref' => true,
            ],
            'subFolder' => [
                'title' => 'Папка',
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => function (\diModel $m) {
                        return $m->has('folder') ? 'id-bg' : 'lite';
                    },
                ],
                'noHref' => true,
            ],
            'date_created' => [
                'title' => 'Дата создания',
                'value' => function (\diModel $m) {
                    if ($m->has('folder')) {
                        return '';
                    }

                    $ar = str_split($m->getId(), 2);

                    return "{$ar[3]}.{$ar[2]}.{$ar[0]}{$ar[1]} {$ar[4]}:{$ar[5]}";
                },
                'attrs' => [],
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => function (\diModel $m) {
                        return $m->has('folder') ? 'id-bg' : 'dt';
                    },
                ],
                'noHref' => true,
            ],
            'date_modified' => [
                'title' => 'Дата модификации',
                'value' => function (\diModel $m) {
                    if ($m->has('folder')) {
                        return '';
                    }

                    return \diDateTime::simpleFormat($m->get('date'));
                },
                'attrs' => [],
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => function (\diModel $m) {
                        return $m->has('folder') ? 'id-bg' : 'dt';
                    },
                ],
                'noHref' => true,
            ],
            'date_applied' => [
                'title' => 'Дата наката',
                'value' => function (\diModel $m) {
                    if ($m->has('folder')) {
                        return '';
                    }

                    $date = $this->getManager()->whenExecuted($m->getId());

                    return $date ? \diDateTime::simpleFormat($date) : '&ndash;';
                },
                'attrs' => [],
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => function (\diModel $m) {
                        return $m->has('folder') ? 'id-bg' : 'dt';
                    },
                ],
                'noHref' => true,
            ],
            '#play' => [
                'active' => function (\diModel $m) {
                    if ($m->has('folder')) {
                        return false;
                    }

                    return !$this->getManager()->wasExecuted($m->getId());
                },
                'href' =>
                    \diLib::getAdminWorkerPath('migration', 'up', '%id%') .
                    '?back=' .
                    urlencode($_SERVER['REQUEST_URI']),
                'onclick' => "return confirm('Накатить миграцию?');",
            ],
            '#rollback' => [
                'active' => function (\diModel $m) {
                    if ($m->has('folder')) {
                        return false;
                    }

                    return $this->getManager()->wasExecuted($m->getId());
                },
                'href' =>
                    \diLib::getAdminWorkerPath('migration', 'down', '%id%') .
                    '?back=' .
                    urlencode($_SERVER['REQUEST_URI']),
                'onclick' => "return confirm('Откатить миграцию?');",
            ],
        ]);

        $perPage = null;

        try {
            $perPage = $this->getCountPerPage();
        } catch (\Exception $e) {
            // it's ok, unlimited migrations count
        }

        foreach ($this->getManager()::getFolderIds() as $folderId) {
            // printing folder
            $folder = $this->getManager()->getFolderById($folderId);
            $this->getList()->addRow([
                'id' => '-',
                'description' => $folder,
                'folder' => true,
                'subFolder' => '',
            ]);

            $allMigrations = $this->getManager()->getMigrationsInFolder($folder, [
                'sort' => 'desc',
            ]);

            if ($perPage) {
                $this->getPagesNavy()->setTotalRecords(count($allMigrations));
            }

            $migrations = $perPage
                ? array_slice(
                    $allMigrations,
                    $this->getPagesNavy()->getStart(),
                    $this->getPagesNavy()->getPerPage()
                )
                : $allMigrations;

            foreach ($migrations as $i => $fn) {
                $idx = $this->getManager()::getIdxByFileName($fn);

                $subFolder = basename(dirname($fn));
                if ($subFolder == 'migrations') {
                    $subFolder = '';
                }

                /** @var Migration $className */
                $className = $this->getManager()::getClassNameByIdx($idx);
                require_once $fn;

                $this->getList()->addRow([
                    'id' => $idx,
                    'description' => \diDB::_out($className::$name),
                    'date' => filemtime($fn),
                    'subFolder' => $subFolder,
                ]);

                if ($perPage && $i >= $perPage - 1) {
                    $this->getList()->addRow([
                        'id' => '-',
                        'description' => "<div class=\"navy\">{$this->getPagesNavy()->print_pages(
                            $this->getListHref()
                        )}</div",
                        'folder' => true,
                        'subFolder' => '',
                    ]);

                    break;
                }
            }
        }
    }

    public function printList()
    {
        if ($this->getMethod() !== 'list') {
            parent::printList();
        }
    }

    public function renderForm()
    {
        $this->getTpl()
            ->define('`migrations/form', ['after_form'])
            ->assign(
                [
                    'ACTION' => Base::getPageUri($this->pseudoTable, 'submit'),
                ],
                'ADMIN_FORM_'
            );

        $rawFolders = $this->getManager()::getSubFolders(
            $this->getManager()::FOLDER_LOCAL
        );
        $folders = [
            '' => '/',
        ];

        $start = $this->getManager()->getLocalFolder() . '/';

        foreach ($rawFolders as $f) {
            $f = mb_substr($f, mb_strlen($start));

            $folders[$f] = $f;
        }

        $folders['*'] = 'Создать папку';

        $this->getForm()
            ->setInputSuffix('folder', Form::INPUT_SUFFIX_NEW_FIELD)
            ->setSelectFromArrayInput('folder', $folders);
    }

    public function submitForm()
    {
        $this->getSubmit()
            ->processData('idx', function ($idx) {
                return preg_replace('/[^a-z0-9_]/i', '', $idx);
            })
            ->processData('folder', function ($folder) {
                if ($folder == '*') {
                    $folder = \diRequest::post(
                        'folder' . Form::NEW_FIELD_SUFFIX,
                        ''
                    );
                }

                return preg_replace('/[^a-z0-9_]/i', '', $folder);
            });

        $this->getManager()->createMigration(
            $this->getSubmit()->getData('idx'),
            $this->getSubmit()->getData('name'),
            $this->getSubmit()->getData('folder')
        );
    }

    protected function afterSubmitForm()
    {
        $this->redirectAfterSubmit();
    }

    public function getFormFields()
    {
        return [
            'idx' => [
                'type' => 'string',
                'title' => 'Идентификатор',
                'default' => date('YmdHis'),
            ],

            'name' => [
                'type' => 'string',
                'title' => 'Название',
                'default' => '',
            ],

            'folder' => [
                'type' => 'string',
                'title' => 'Папка',
                'default' => '',
            ],
        ];
    }

    public function getLocalFields()
    {
        return [];
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Миграции',
            'en' => 'Migrations',
        ];
    }
}
