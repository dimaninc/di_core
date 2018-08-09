<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.08.2018
 * Time: 18:15
 */

namespace diCore\Admin\Reference;

use diCore\Admin\Submit;
use diCore\Entity\Photo\Model;
use diCore\Helper\StringHelper;
use diCore\Traits\BasicCreate;

class PhotosOfAlbum
{
    use BasicCreate;
    
	protected static $table = 'photos';

	public static function getFormFieldArray()
	{
		return [
			'type' => 'dynamic',
			'title' => 'Фотографии',
			'default' => '',
			'tab' => 'photos',
			'table' => static::$table,
			'multiple_uploading' => true,
			//'drag_and_drop_uploading' => true,
			'sortby' => 'order_num ASC',
			'subquery' => function ($table, $field, $id) {
				return "album_id = '{$id}'";
			},
			'techFieldsCallback' => function($table, $field, $id, \diDynamicRows $DR) {
				return [
					'album_id' => $id,
				];
			},
			'beforeSave' => function(\diDynamicRows $DR) {
				if ($DR->getData('slug'))
				{
					return [];
				}

				//var_debug('slug', $DR->getTable(), $DR->getStoredId());

				$slugSource = StringHelper::replaceFileExtension($DR->getData('pic'), '') ?: get_unique_id();
				$slug = \diSlug::generate($slugSource, $DR->getTable(), $DR->getStoredId());

				return [
					'slug' => $slug,
					'slug_source' => $slugSource,
				];
			},
			'multiUploadCallback' => function($table, $field, $id, \diDynamicRows $DR) {
				return [
					'visible' => 1,
					'content' => '',
				];
			},
			'fields' => [
				'slug' => 'string',
				'slug_source' => 'string',
				'content' => 'text',
				'title' => [
					'type' => 'string',
				],
				'visible' => [
					'type' => 'checkbox',
					'default' => 1,
				],
				'order_num' => [
					'type' => 'int',
					//'flags' => ['local'],
				],
				'pic' => [
					'type' => 'pic',
					'defaultMultiplePic' => true,
					//'naming' => Submit::FILE_NAMING_ORIGINAL,
					'showImageType' => Submit::IMAGE_TYPE_PREVIEW,
					'callback' => [\diDynamicRows::class, 'storePicSimple'],
					'fileOptions' => Model::getPicOptions(),
				],
			],
			'template' => '<ul class="line">' .
				'<li>Подпись: {TITLE}</li>' .
				'<li>Отображать: {VISIBLE}</li>' .
				'<li>Порядковый номер: {ORDER_NUM}</li>' .
				'</ul><ul class="line">' .
				'<li>Изображение: {PIC}</li>' .
				'</ul>',
		];
	}
}