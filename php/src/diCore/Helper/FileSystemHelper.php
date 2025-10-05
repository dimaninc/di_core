<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.08.2016
 * Time: 16:14
 */

namespace diCore\Helper;

class FileSystemHelper
{
    public static function folderContents(
        $path,
        $returnFullPath = false,
        $recursive = false
    ) {
        $handle = opendir($path);

        $result = [
            'f' => [],
            'd' => [],
        ];

        while ($f = readdir($handle)) {
            $f2 = $returnFullPath ? StringHelper::slash($path) . $f : $f;

            if ($f && is_file(StringHelper::slash($path) . $f)) {
                $result['f'][] = $f2;
            } elseif (
                $f &&
                is_dir(StringHelper::slash($path) . $f) &&
                $f != '.' &&
                $f != '..'
            ) {
                $result['d'][] = $f2;

                if ($recursive) {
                    $result = array_merge_recursive(
                        $result,
                        self::folderContents(
                            StringHelper::slash($path) . $f,
                            $returnFullPath,
                            $recursive
                        )
                    );
                }
            }
        }

        closedir($handle);

        sort($result['f']);
        sort($result['d']);

        return $result;
    }

    public static function createTree($basePath, $pathEndingsToCreate, $mod = 0775)
    {
        if (!is_array($pathEndingsToCreate)) {
            $pathEndingsToCreate = [$pathEndingsToCreate];
        }

        foreach ($pathEndingsToCreate as $path) {
            $folders = explode('/', $path);
            $fullPath = $basePath;

            $oldMask = umask(0);

            foreach ($folders as $f) {
                if ($f) {
                    $fullPath = StringHelper::slash($fullPath) . $f;

                    if (!is_dir($fullPath)) {
                        mkdir($fullPath, $mod);
                    }
                }
            }

            umask($oldMask);
        }
    }

    public static function delTree($path, $killRootFolder = true)
    {
        if (!$path) {
            throw new \InvalidArgumentException("Path can't be empty");
        }

        if (!is_dir($path)) {
            throw new \InvalidArgumentException("$path must be a directory");
        }

        $contents = self::folderContents($path, true, true);

        foreach ($contents['f'] as $file) {
            @unlink($file);
        }

        foreach ($contents['d'] as $dir) {
            if ($dir == $path && !$killRootFolder) {
                continue;
            }

            @rmdir($dir);
        }
    }
}
