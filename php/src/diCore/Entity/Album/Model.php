<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 9:56
 */

namespace diCore\Entity\Album;

use diCore\Data\Types;
use diCore\Helper\FileSystemHelper;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getSlugSource
 * @method string	getTitle
 * @method string	getContent
 * @method integer	getCoverPhotoId
 * @method string	getPic
 * @method integer	getPicW
 * @method integer	getPicH
 * @method integer	getPicT
 * @method string	getDate
 * @method integer	getOrderNum
 * @method integer	getVisible
 * @method integer	getTop
 * @method integer	getCommentsEnabled
 * @method string	getCommentsLastDate
 * @method integer	getCommentsCount
 * @method integer	getPhotosCount
 * @method integer	getVideosCount
 *
 * @method bool hasSlugSource
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasCoverPhotoId
 * @method bool hasPic
 * @method bool hasPicW
 * @method bool hasPicH
 * @method bool hasPicT
 * @method bool hasDate
 * @method bool hasOrderNum
 * @method bool hasVisible
 * @method bool hasTop
 * @method bool hasCommentsEnabled
 * @method bool hasCommentsLastDate
 * @method bool hasCommentsCount
 * @method bool hasPhotosCount
 * @method bool hasVideosCount
 *
 * @method Model setSlugSource($value)
 * @method Model setTitle($value)
 * @method Model setContent($value)
 * @method Model setCoverPhotoId($value)
 * @method Model setPic($value)
 * @method Model setPicW($value)
 * @method Model setPicH($value)
 * @method Model setPicT($value)
 * @method Model setDate($value)
 * @method Model setOrderNum($value)
 * @method Model setVisible($value)
 * @method Model setTop($value)
 * @method Model setCommentsEnabled($value)
 * @method Model setCommentsLastDate($value)
 * @method Model setCommentsCount($value)
 * @method Model setPhotosCount($value)
 * @method Model setVideosCount($value)
 */
class Model extends \diModel
{
	const type = Types::album;
	protected $table = 'albums';
	protected $slugFieldName = 'slug';

	const TOKEN_TEMPLATE = '[ALBUM-%s]';
	const TOKEN_DIGITS = 5;

	public function getToken()
	{
		return sprintf(static::TOKEN_TEMPLATE, str_pad($this->getId(), static::TOKEN_DIGITS, '0', STR_PAD_LEFT));
	}

	public function generateThumbnail($options = array())
	{
		if (
			!\diConfiguration::exists('albums_tn_width') ||
			!\diConfiguration::exists('albums_tn_height') ||
			!$this->hasPhotosCount()
		   )
		{
			return $this;
		}

		$options = extend([
			'childTable' => 'photos',
			'fieldForSubFolders' => null, // e.g. user_id
			'borderWidth' => 1,
			'borderHeight' => null,
		], $options);

		$query = "WHERE album_id='{$this->getId()}' and visible='1' ORDER BY id DESC";

		$width = \diConfiguration::get('albums_tn_width');
		$height = \diConfiguration::get('albums_tn_height');

		$borderWidth = $options['borderWidth'];
		$borderHeight = $options['borderHeight'] ?: $options['borderWidth'];

		$childWidth = ($width - $borderWidth) >> 1;
		$childHeight = ($height - $borderHeight) >> 1;

		$albumsFolder = get_pics_folder($this->getTable());
		$photosFolder = get_pics_folder($options['childTable']);

		if ($options['fieldForSubFolders'] && $this->has($options['fieldForSubFolders']))
		{
			$userFolder = get_1000_path($this->get($options['fieldForSubFolders']));

			$albumsFolder .= $userFolder;
			$photosFolder .= $userFolder;
		}

		FileSystemHelper::createTree(\diPaths::fileSystem(), $albumsFolder, 0775);
		$fn = $this->getPic();

		if (!$fn)
		{
			do {
				$fn = substr(get_unique_id(), 0, 10) . '.jpg';
			} while (is_file(\diPaths::fileSystem().$albumsFolder.$fn));

			$this->setPic($fn);
		}

		$fullFn = \diPaths::fileSystem().$albumsFolder.$fn;
		$collection = \diCollection::createForTable($options['childTable'], $query);

		if (false && $this->getPhotosCount() < 4)
		{
			/** @var \diCore\Entity\Photo\Model $photo */
			$photo = $collection->getFirstItem();

			if ($photo->exists())
			{
				$I = new \diImage(\diPaths::fileSystem() . $photosFolder . get_tn_folder() . $photo->getPic());
				$I->make_thumb(DI_THUMB_CROP, $fullFn, $width, $height);
				$I->close();
			}
		}
		else
		{
			$I = new \diImage();
			$I->w = $width;
			$I->h = $height;
			$I->t = \diImage::TYPE_JPEG;
			$I->image = imagecreatetruecolor($I->w, $I->h);
			imagefilledrectangle($I->image, 0, 0, $I->w, $I->h, rgb_allocate($I->image, '#ffffff'));

			$x = $y = 0;

			$randomPhotos = $collection->getRandomItemsArray(4);

			/** @var \diCore\Entity\Photo\Model $photo */
			foreach ($randomPhotos as $photo)
			{
				$picFn = \diPaths::fileSystem() . $photosFolder . get_tn_folder() . $photo->getPic();

				if (!is_file($picFn))
				{
					continue;
				}

				$I2 = new \diImage($picFn);
				list($src_w, $src_h, $src_x, $src_y) = $I2->calculate_dst_dimentsions(DI_THUMB_CROP, $childWidth, $childHeight);
				imagecopyresampled($I->image, $I2->image, $x, $y, $src_x, $src_y, $childWidth, $childHeight, $src_w, $src_h);
				$I2->close();

				$x += $childWidth + $borderWidth;

				if ($x >= ($childWidth + 1) * 2)
				{
					$x = 0;
					$y += $childHeight + $borderHeight;
				}
			}

			$I->store($fullFn);
			$I->close();
		}

		@chmod($fullFn, 0775);

		$this
			->setPicW($width)
			->setPicH($height)
			->setPicT(\diImage::TYPE_JPEG)
			->save();

		return $this;
	}
}