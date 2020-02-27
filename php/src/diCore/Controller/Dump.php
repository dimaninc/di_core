<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.02.20
 * Time: 13:40
 */

namespace diCore\Controller;

use diCore\Data\Config;
use diCore\Helper\Slug;
use diCore\Helper\StringHelper;
use diCore\Traits\Admin\DumpActions;

class Dump extends \diBaseAdminController
{
    use DumpActions;

    const CHMOD_FILE = 0664;

    protected static $fileFolders = [
        //'uploads',
        'favicon'
    ];
    protected static $customFileFolders = [];

    public function __construct($params = [])
    {
        parent::__construct($params);

        $this->file = \diRequest::get('file', '');
        $this->folder = StringHelper::slash(static::getFileDumpsFolder()); // \diRequest::get('folder', '') ?:
    }

    public static function getFileDumpsFolder()
    {
        return Config::getFileDumpPath();
    }

    public function createAction()
    {
        $base = Slug::prepare(Config::getMainDomain() ?: Config::getSiteTitle(), '_');
        $filename = $base . '-' . \diDateTime::format('Y-m-d-H-i-s') . '.zip';
        $fullFilename = $this->folder . $filename;

        $command = 'zip -0 -r ' . $fullFilename . ' ' . join(' ', static::getFoldersWithAbsolutePath());
        $ending = '> /dev/null 2>/dev/null &';

        $ret = shell_exec($command . ' ' . $ending);

        return [
            'ret' => $ret,
            //'c' => $command . ' ' . $ending,
            'file' => $filename,
            'ok' => true,
        ];
    }

    public function updateSizeAction()
    {
        //$prevSize = \diRequest::request('prev_size', 0);
        $fullFilename = $this->folder . $this->file;

        return [
            'size' => filesize($fullFilename),
        ];
    }

    protected static function getFoldersWithAbsolutePath()
    {
        return array_map(function($folder) {
            return \diPaths::fileSystem() . $folder;
        }, static::getFolders());
    }

    protected static function getFolders()
    {
        return array_merge(static::$fileFolders, static::$customFileFolders);
    }
}