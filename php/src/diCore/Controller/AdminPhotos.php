<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.03.2016
 * Time: 11:27
 */

namespace diCore\Controller;

use diCore\Admin\Submit;
use diCore\Data\Types;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;

class AdminPhotos extends \diBaseAdminController
{
    protected function getPhotoTitle($origFileName)
    {
        return str_replace(
            '_',
            ' ',
            StringHelper::replaceFileExtension($origFileName, '')
        );
    }

    protected function getPhotoContent($origFileName)
    {
        return '';
    }

    public function uploadAction()
    {
        FileSystemHelper::createTree(\diPaths::fileSystem(), get_tmp_folder());

        $upload_dir = \diPaths::fileSystem() . get_tmp_folder();
        $valid_extensions = ['gif', 'png', 'jpeg', 'jpg'];

        $Upload = new \FileUpload('pic');
        $origFileName = $Upload->getFileName();

        do {
            $Upload->newFileName =
                get_unique_id(10) . '.' . $Upload->getExtension();
        } while (is_file($upload_dir . $Upload->newFileName));

        $ok = $Upload->handleUpload($upload_dir, $valid_extensions);

        $html = '';

        if ($ok) {
            /** @var \diPhotoModel $photo */
            $photo = \diModel::create(Types::photo);

            list($w, $h, $t) = getimagesize(
                $upload_dir . $Upload->getFileName()
            );

            $photo
                ->setAlbumId(\diRequest::get('album_id', 0))
                ->setTitle($this->getPhotoTitle($origFileName))
                ->setContent($this->getPhotoContent($origFileName))
                ->setPic($Upload->getFileName())
                ->setPicW($w)
                ->setPicH($h)
                ->setPicT($t)
                ->calculateAndSetOrderNum(-1)
                ->save();

            $Submit = new Submit('photos', $photo->getId());

            $_FILES = [
                'pic' => [
                    'name' => $photo->getPic(),
                    'tmp_name' => $upload_dir . $photo->getPic(),
                    'error' => 0,
                    'size' => filesize($upload_dir . $photo->getPic()),
                ],
            ];

            $_POST = $photo->get();

            //$Submit->storeData();
            $Submit->storeImage('pic', [
                [
                    'type' => Submit::IMAGE_TYPE_MAIN,
                    'resize' => \diImage::DI_THUMB_FIT,
                ],
                [
                    'type' => Submit::IMAGE_TYPE_PREVIEW,
                    'resize' => \diImage::DI_THUMB_FIT,
                ],
                [
                    'type' => Submit::IMAGE_TYPE_ORIG,
                ],
            ]);

            if (is_file($upload_dir . $photo->getPic())) {
                unlink($upload_dir . $photo->getPic());
            }

            $picFn =
                $photo->getPicsFolder() . get_tn_folder() . $photo->getPic();
            list($tnW, $tnH) = getimagesize(\diPaths::fileSystem() . $picFn);

            $photo
                ->setPicTnW($tnW)
                ->setPicTnH($tnH)
                ->save();

            $html = <<<EOF
<li data-id="{$photo->getId()}" data-role="row">
	<div class="tn"><a href="/_admin/photos/form/{$photo->getId()}/"><img src="/{$picFn}"></a></div>
	<div class="title">{$photo->getTitle()}</div>
	<div class="buttons-panel">
		<div class="nicetable-button" data-action="up" title="Переместить вверх"></div>
		<a class="nicetable-button" data-action="edit" title="Редактировать" href="/_admin/photos/form/{$photo->getId()}/"></a>
		<div class="nicetable-button" data-action="del" title="Удалить"></div>
		<div class="nicetable-button" data-action="visible" data-state="{$photo->getVisible()}" title="Видно"></div>
		<div class="nicetable-button" data-action="down" title="Переместить вниз"></div>
	</div>
</li>
EOF;
        }

        return [
            'success' => !!$ok,
            'msg' => $ok ? '' : $Upload->getErrorMsg(),
            'file' => $ok ? $Upload->getFileName() : '',
            'html' => $html,
            'direction' => -1,
        ];
    }

    /**
     * Simple Ajax Uploader
     * Version 2.5.1
     * https://github.com/LPology/Simple-Ajax-Uploader
     *
     * Copyright 2012-2016 LPology, LLC
     * Released under the MIT license
     *
     * Returns upload progress updates for browsers that don't support the HTML5 File API.
     * Falling back to this method allows for upload progress support across virtually all browsers.
     *
     */
    public function progressAction()
    {
        // This "if" statement is only necessary for CORS uploads -- if you're
        // only doing same-domain uploads then you can delete it if you want
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400'); // cache for 1 day
        }

        if (isset($_REQUEST['progresskey'])) {
            $status = apc_fetch('upload_' . $_REQUEST['progresskey']);
        } else {
            return [
                'success' => false,
            ];
        }

        $pct = 0;
        $size = 0;

        if (is_array($status)) {
            if (
                array_key_exists('total', $status) &&
                array_key_exists('current', $status)
            ) {
                if ($status['total'] > 0) {
                    $pct = round(($status['current'] / $status['total']) * 100);
                    $size = round($status['total'] / 1024);
                }
            }
        }

        return [
            'success' => true,
            'pct' => $pct,
            'size' => $size,
        ];
    }

    /**
     * Simple Ajax Uploader
     * Version 2.5.1
     * https://github.com/LPology/Simple-Ajax-Uploader
     *
     * Copyright 2012-2016 LPology, LLC
     * Released under the MIT license
     *
     * Returns upload progress updates for browsers that don't support the HTML5 File API.
     * Falling back to this method allows for upload progress support across virtually all browsers.
     * Requires PHP 5.4+
     * Further documentation: http://php.net/manual/en/session.upload-progress.php
     *
     */
    public function progressSessionAction()
    {
        session_start();

        if (!isset($_POST[ini_get('session.upload_progress.name')])) {
            return [
                'success' => false,
            ];
        }

        $key =
            ini_get('session.upload_progress.prefix') .
            $_POST[ini_get('session.upload_progress.name')];

        if (!isset($_SESSION[$key])) {
            return [
                'success' => false,
            ];
        }

        $progress = $_SESSION[$key];
        $pct = 0;
        $size = 0;

        if (is_array($progress)) {
            if (
                array_key_exists('bytes_processed', $progress) &&
                array_key_exists('content_length', $progress)
            ) {
                if ($progress['content_length'] > 0) {
                    $pct = round(
                        ($progress['bytes_processed'] /
                            $progress['content_length']) *
                            100
                    );
                    $size = round($progress['content_length'] / 1024);
                }
            }
        }

        return [
            'success' => true,
            'pct' => $pct,
            'size' => $size,
        ];
    }
}
