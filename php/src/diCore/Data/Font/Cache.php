<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.09.2016
 * Time: 18:41
 */

namespace diCore\Data\Font;


class Cache
{
	use \diSingleton;

	/** @var \diFontCollection */
	private $fonts;

	protected function init()
	{
		$this->fonts = \diCollection::create(\diTypes::font);

		$this->getFonts()
			->orderByToken();
	}

	/**
	 * @return \diFontCollection
	 */
	public function getFonts()
	{
		return $this->fonts;
	}

	public function defaultCallback()
	{
		return function(\diFontModel $f) {
			return [
				'value' => $f->getToken(),
				'text' => $f->getToken() . ' &ndash; ' . $f->getTitle() . '',
			];
		};
	}
}