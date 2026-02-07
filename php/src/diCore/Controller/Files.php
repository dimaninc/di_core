<?php

namespace diCore\Controller;

use diCore\Admin\BasePage;
use diCore\Base\Exception\HttpException;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\ImageHelper;
use diCore\Helper\StringHelper;
use diCore\Admin\Submit;
use Romantic\Admin\Form;

class Files extends \diBaseAdminController
{
    protected $ret = [];

    public function rebuildDynamicPicsAction()
    {
        $module = $this->param(0);
        $field = $this->param(1);
        $id = $this->param(2);
        $redirect = \diRequest::get('redirect', 0);
        $ar = Submit::rebuildDynamicPics($module, $field, $id);

        if ($redirect) {
            $this->redirect();
        } else {
            /*
			$this->defaultResponse(array(
				'ok' => 1,
				'files' => $ar,
			));
			*/
            echo join('<br>', $ar);
        }
    }

    public function _postRenameAction()
    {
        $table = \diRequest::post('table');
        $id = \diRequest::post('id');
        $field = \diRequest::post('field');

        if (!$field) {
            return $this->badRequest([
                'message' => 'Field not set',
            ]);
        }

        $model = \diModel::createForTableNoStrict($table, $id, 'id');

        if (!$model->exists()) {
            return $this->notFound([
                'message' => 'Record not found',
            ]);
        }

        $Form = new Form(BasePage::liteCreate($table));

        $value = $model->get($field);
        $renameFields = $Form->getFieldOption($field, 'showRenameButton');
        $ext = '.' . strtolower(StringHelper::fileExtension($value));
        $newFn = Submit::getFilenameFromTitle($model, $renameFields) . $ext;

        $model->renameFiles($field, $newFn)->save();

        return $this->okay([
            'oldFn' => $value,
            'newFn' => $model->get($field),
        ]);
    }

    public function delAction()
    {
        $table = StringHelper::in($this->param(0));
        $id = (int) $this->param(1);
        $field = StringHelper::in($this->param(2));
        $redirect = \diRequest::get('redirect', 1);
        $model = \diModel::createForTableNoStrict($table, $id, 'id');
        $ok = $this->delRelatedFiles($model, $field);

        if ($redirect) {
            $this->redirect();

            return null;
        }

        return extend(
            [
                'ok' => $ok,
            ],
            $this->ret
        );
    }

    private function getDefaultSubTableForDelDynamic()
    {
        return StringHelper::in($this->param(2));
    }

    protected function getSubTableForDelDynamic($table, $id)
    {
        return $this->getDefaultSubTableForDelDynamic();
    }

    public function delDynamicAction()
    {
        $table = StringHelper::in($this->param(0)); // todo: make a check if the model belongs to $table#$id
        $id = (int) $this->param(1);
        $subTable = $this->getSubTableForDelDynamic($table, $id);
        $field = StringHelper::in($this->param(3));
        $subId = (int) $this->param(4);
        $redirect = \diRequest::get('redirect', 1);
        $model = \diModel::createForTableNoStrict($subTable, $subId, 'id');
        $ok = $this->delRelatedFiles($model, $field);

        if ($redirect) {
            $this->redirect();

            return null;
        }

        return [
            'ok' => $ok,
            'type' => 'dynamic',
            'table' => $table,
            'id' => $id,
            'subTable' => $this->getDefaultSubTableForDelDynamic(),
            'realSubTable' => $subTable,
            'field' => $field,
            'subId' => $subId,
        ];
    }

    protected function delRelatedFiles(\diModel $model, $field)
    {
        if (!$model->exists()) {
            return false;
        }

        [$masterField, $subField] = Submit::getFieldNamePair($field ?? '');

        if ($subField) {
            if ($model->hasJsonData($masterField, $subField)) {
                $model
                    ->killRelatedFiles($field)
                    ->resetFieldsOfRelatedFiles($field)
                    ->save();

                return true;
            }

            return false;
        }

        if ($model->has($field)) {
            $model
                ->killRelatedFiles($field)
                ->resetFieldsOfRelatedFiles($field)
                ->save();

            return true;
        }

        return false;
    }

    public function rotateAction()
    {
        $ar = [
            'ok' => false,
        ];

        $table = StringHelper::in($this->param(0));
        $id = StringHelper::in($this->param(1));
        $field = StringHelper::in($this->param(2));
        $direction = mb_strtolower(StringHelper::in($this->param(3)));

        switch ($direction) {
            case 'cw':
                $angle = 90;
                break;

            case 'ccw':
                $angle = -90;
                break;

            default:
                throw HttpException::badRequest("Undefined direction: $direction");
        }

        $model = \diModel::createForTableNoStrict($table, $id, 'id');
        $files = $model->getFilesForRotation($field);

        foreach ($files as $fn) {
            static::doRotate($angle, $fn);
        }

        $model->rebuildPics($field);

        $ar['ok'] = true;
        $ar['files'] = $files;

        return $ar;
    }

    protected static function doRotate($angle, $fn)
    {
        ImageHelper::rotate($angle, $fn);
    }

    public function watermarkAction()
    {
        $ar = [
            'ok' => false,
        ];

        $table = StringHelper::in($this->param(0));
        $id = (int) $this->param(1);
        $field = StringHelper::in($this->param(2));
        $type = strtolower(StringHelper::in($this->param(3, 'main')));

        $model = \diModel::createForTableNoStrict($table, $id, 'id');

        $fn = \diPaths::fileSystem($this) . $model[$field . '_with_path'];

        static::doWatermark($table, $field, $type, $fn);

        $ar['ok'] = true;
        $ar['fn'] = $fn;

        return $ar;
    }

    protected static function getWatermarkFilename($table, $field, $type)
    {
        switch ($type) {
            case 'main':
                $wm =
                    \diPaths::fileSystem() .
                    \diConfiguration::getFilename('watermark');
                break;

            case 'tn':
                $wm =
                    \diPaths::fileSystem() .
                    \diConfiguration::getFilename('watermark_tn');
                break;

            default:
                throw HttpException::badRequest("Undefined watermark size: $type");
        }

        return $wm;
    }

    protected static function getWatermarkCoordinates($table, $field, $type)
    {
        return [
            'x' => 'right',
            'y' => 'bottom',
        ];
    }

    protected static function doWatermark($table, $field, $type, $fn)
    {
        $xy = static::getWatermarkCoordinates($table, $field, $type);

        ImageHelper::watermark(
            static::getWatermarkFilename($table, $field, $type),
            $fn,
            null,
            $xy['x'],
            $xy['y']
        );
    }

    protected function scavengeChunkUploads($folder, $days = 1)
    {
        $files = FileSystemHelper::folderContents($folder, true, true)['f'];
        $timestamp = time() - 60 * 60 * 24 * $days;

        foreach ($files as $file) {
            if (filemtime($file) <= $timestamp) {
                unlink($file);
            }
        }

        return $this;
    }

    public function _postChunkUploadAction()
    {
        $table = \diRequest::get('table');
        $field = \diRequest::get('field');
        $tmpFilename = \diRequest::get('tmp_filename');
        $tmpPath = get_tmp_folder() . $table . '/' . $field . '/';

        if (!$tmpFilename) {
            throw HttpException::badRequest('No tmp filename defined');
        }

        if (!$table) {
            throw HttpException::badRequest('No table defined');
        }

        if (!$field) {
            throw HttpException::badRequest('No field name defined');
        }

        FileSystemHelper::createTree(\diPaths::fileSystem(), $tmpPath, 0777);

        file_put_contents(
            \diPaths::fileSystem() . $tmpPath . $tmpFilename,
            file_get_contents('php://input'),
            FILE_APPEND
        );

        $this->scavengeChunkUploads(
            \diPaths::fileSystem() . get_tmp_folder() . $table
        );
    }
}
