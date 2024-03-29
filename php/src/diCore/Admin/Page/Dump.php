<?php

namespace diCore\Admin\Page;

use diCore\Controller\Db as dbController;
use diCore\Controller\Dump as dumpController;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;

class Dump extends \diCore\Admin\BasePage
{
    const basePath = 'dump';

    protected $vocabulary = [
        'ru' => [
            'caption.files' => 'Копии файлов',
            'caption.db' => 'Копии базы данных',
            'caption.select_tables' => 'Выберите таблицы',

            'button.create.files' => 'Создать архив с копией файлов',
            'button.create.db' => 'Создать копию базы данных',

            'dump.delete' => 'Удалить',
            'dump.download' => 'Скачать',
            'dump.restore' => 'Восстановить',
            'dump.view' => 'Просмотр',

            'message.no_dumps' => 'Пока не сделано ни одной копии',

            'word.select_all' => 'Выбрать все',
            'word.deselect_all' => 'Снять выделение',
        ],
        'en' => [
            'caption.files' => 'User files dumps',
            'caption.db' => 'Database dumps',
            'caption.select_tables' => 'Select tables',

            'button.create.files' => 'Create user files archive',
            'button.create.db' => 'Create database dump',

            'dump.delete' => 'Delete',
            'dump.download' => 'Download',
            'dump.restore' => 'Restore',
            'dump.view' => 'View',

            'message.no_dumps' => 'No dumps made yet',

            'word.select_all' => 'Select all',
            'word.deselect_all' => 'Deselect all',
        ],
    ];

    protected $excludedTables = ['banner_stat2', 'mail_queue', 'search_results'];

    public function renderList()
    {
        $this->getTwig()->renderPage('admin/dump/list', [
            'worker_uri' => [
                'db' => \diLib::getAdminWorkerPath('db'),
                'db_upload' => \diLib::getAdminWorkerPath('db', 'upload'),
                'dump' => \diLib::getAdminWorkerPath('dump'),
                'dump_upload' => \diLib::getAdminWorkerPath('dump', 'upload'),
            ],
            'tables' => $this->getTablesData(),
            'db_folders' => $this->getDbFolders(),
            'file_folders' => $this->getFileFolders(),
            'disks' => $this->getDisksUsage(),
        ]);
    }

    public function renderForm()
    {
        throw new \Exception('No form in ' . get_class($this));
    }

    protected function getDisksUsage()
    {
        $disks = [];

        $path = \diPaths::fileSystem();

        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $totalStr = size_in_bytes($total);
        $freeStr = size_in_bytes($free);

        $disks[] = [
            'title' => 'Server',
            'total' => $totalStr,
            'free' => $freeStr,
            'free_percent' => sprintf('%.2f', ($free / $total) * 100),
            'used_percent' => sprintf('%.2f', (($total - $free) / $total) * 100),
        ];

        return $disks;
    }

    private function getTablesData()
    {
        $tablesAr = dbController::getTablesList($this->getDb());

        $tablesSel = new \diSelect('tables');
        $tablesSel->setCurrentValue(function ($table) use ($tablesSel) {
            return !in_array($table, $this->excludedTables) &&
                substr($table, 0, 13) != 'search_index_' &&
                !preg_match('/\[[^\]]+\]$/', $tablesSel->getTextByValue($table));
        });

        $tablesSel
            ->setAttr('multiple')
            ->setAttr('size', 10)
            ->addItemArray($tablesAr['tablesForSelectAr']);

        return [
            'select' => $tablesSel,
            'total_size' => size_in_bytes($tablesAr['totalSize']),
            'total_index_size' => size_in_bytes($tablesAr['totalIndexSize']),
        ];
    }

    private function getFileFolders()
    {
        /** @var dumpController $controllerClass */
        $controllerClass = \diLib::getChildClass(dumpController::class);
        $folder = $controllerClass::getFileDumpsFolder();

        if (!is_dir($folder)) {
            return null;
        }

        $dir = FileSystemHelper::folderContents($folder, true, true);
        $filesAr = array_map(function ($v) use ($folder) {
            return substr($v, 0, strlen($folder)) == $folder
                ? substr($v, strlen($folder))
                : $v;
        }, $dir['f']);

        $ar = array_map(function ($name) use ($folder) {
            $fullFn = $folder . $name;
            $dt = filemtime($fullFn);

            return [
                'name' => $name,
                'type' => 'file',
                'info' => [
                    'name' => $name,
                    'date' => \diDateTime::format('d.m.Y', $dt),
                    'time' => \diDateTime::format('H:i', $dt),
                    'size' => size_in_bytes(filesize($fullFn)),
                    'ext' => StringHelper::fileExtension($name),
                    'full_filename' => $fullFn,
                ],
            ];
        }, $filesAr);

        return [
            [
                'name' => $folder,
                'files' => $ar,
            ],
        ];
    }

    private function getDbFolders()
    {
        /** @var dbController $controllerClass */
        $controllerClass = \diLib::getChildClass(dbController::class);

        $ar = [];

        foreach ($controllerClass::getFolderIds() as $folderId) {
            $folder = $controllerClass::getFolderById($folderId);
            $filesAr = $this->getDumpFilesFromFolder($folder, $folderId);

            $ar[] = [
                'id' => $folderId,
                'name' => $folder,
                'files' => $filesAr,
            ];
        }

        return $ar;
    }

    private function getDumpFilesFromFolder($folder, $folderId = null)
    {
        $ar = [];

        $dir = FileSystemHelper::folderContents($folder, true, true);
        $filesAr = $dir['f'];

        $filesAr = array_map(function ($v) use ($folder) {
            return substr($v, 0, strlen($folder)) == $folder
                ? substr($v, strlen($folder))
                : $v;
        }, $filesAr);

        usort($filesAr, function ($a, $b) {
            $aDir = dirname($a);
            $bDir = dirname($b);

            if ($aDir > $bDir) {
                return 1;
            } elseif ($aDir < $bDir) {
                return -1;
            } else {
                if ($a > $b) {
                    return 1;
                } elseif ($a < $b) {
                    return -1;
                }
            }

            return 0;
        });

        $currentFolder = '';

        foreach ($filesAr as $f) {
            unset($regs);
            unset($regs2);

            // we're inside folder
            if (basename($f) != $f && $currentFolder != dirname($f)) {
                $currentFolder = dirname($f);

                $ar[] = [
                    'type' => 'folder',
                    'name' => $currentFolder,
                    'name_slashed' => StringHelper::slash($currentFolder),
                ];
            }

            preg_match(
                "/^(.*)__dump_(.{4})_(.{2})_(.{2})__(.{2})_(.{2})_(.{2})\.sql(\.gz)?$/i",
                basename($f),
                $regs
            );
            preg_match("/^(.*)\.sql(\.gz)?$/i", basename($f), $regs2);

            if ($regs || $regs2) {
                if ($regs) {
                    $standard = true;

                    for ($i = 2; $i < count($regs) - 1; $i++) {
                        if (lead0(intval($regs[$i])) != $regs[$i]) {
                            $standard = false;

                            break;
                        }
                    }
                } else {
                    $standard = false;
                }

                if ($standard) {
                    $name = $regs[1];
                    $dy = $regs[2];
                    $dm = $regs[3];
                    $dd = $regs[4];
                    $th = $regs[5];
                    $tm = $regs[6];
                    $ts = $regs[7];
                    $compressed = isset($regs[8]) && strtolower($regs[8]) == '.gz';
                } else {
                    $name = $regs2[1];
                    list($dy, $dm, $dd, $th, $tm, $ts) = explode(
                        ',',
                        date('Y,m,d,H,i,s', filemtime($folder . $f))
                    );
                    $compressed = isset($regs[2]) && strtolower($regs[2]) == '.gz';
                }

                $ext = $compressed ? 'gz' : 'sql';

                $ar[] = [
                    'type' => 'file',
                    'fullFilename' => $folder . $f,
                    'datetime' => strtotime("$dd.$dm.$dy $th:$tm:$ts"),
                    'info' => [
                        'name' => $name,
                        'date' => "$dy.$dm.$dd",
                        'time' => "$th:$tm:$ts",
                        'size' => size_in_bytes(filesize($folder . $f)),
                        'ext' => $ext,
                        'full_filename' => $folder . $f,
                        'filename' => $f,
                    ],
                ];
            }
        }

        /*
		usort($ar, function($a, $b) use($folderId) {
			if ($folderId == diDbController::FOLDER_CORE_SQL)
			{
				return $a["templateAr"]["NAME"] > $b["templateAr"]["NAME"];
			}

			return $a["datetime"] < $b["datetime"];
		});
		*/

        return $ar;
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Резервное копирование базы данных',
            'en' => 'Database dump/restore',
        ];
    }

    public function addButtonNeededInCaption()
    {
        return false;
    }
}
