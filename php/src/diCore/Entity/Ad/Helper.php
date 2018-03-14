<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.12.2017
 * Time: 11:55
 */

namespace diCore\Entity\Ad;

use diCore\Base\CMS;
use diCore\Data\Types;
use diCore\Entity\AdBlock\Model as AdBlock;

class Helper
{
	const TEMPLATE_NAME = 'snippets/ad_block';
	const CUSTOM_TEMPLATE_NAME = 'snippets/custom_ad_block';

	const TRANSITION_DEFAULT = 0;
	const TRANSITION_CROSS_FADE = 1;
	const TRANSITION_SLIDE_L2R = 2;
	const TRANSITION_SLIDE_R2L = 3;
	const TRANSITION_SLIDE_T2B = 4;
	const TRANSITION_SLIDE_B2T = 5;

	const TRANSITION_STYLE_DEFAULT = 0;
	const TRANSITION_STYLE_BOTH_SLIDING = 1;
	const TRANSITION_STYLE_ONLY_NEW_SLIDING = 2;

	const ORDER_IN_ORDER = 0;
	const ORDER_RANDOM = 1;

	static $adTransitionsAr = [
		self::TRANSITION_DEFAULT => 'По умолчанию',
		self::TRANSITION_CROSS_FADE => 'Проявление (crossfade)',
		self::TRANSITION_SLIDE_L2R => 'Скроллинг (слева направо)',
		self::TRANSITION_SLIDE_R2L => 'Скроллинг (справа налево)',
		self::TRANSITION_SLIDE_T2B => 'Скроллинг (сверху вниз)',
		self::TRANSITION_SLIDE_B2T => 'Скроллинг (снизу вверх)',
	];

	static $adTransitionStylesAr = [
		self::TRANSITION_STYLE_DEFAULT => 'По умолчанию',
		self::TRANSITION_STYLE_BOTH_SLIDING => 'Новый слайд вытесняет старый',
		self::TRANSITION_STYLE_ONLY_NEW_SLIDING => 'Новый слайд наезжает на старый',
	];

	static $adSlidesOrdersAr = [
		self::ORDER_IN_ORDER => 'По порядку',
		self::ORDER_RANDOM => 'В случайном порядке',
	];

	/**
	 * @var CMS
	 */
	private $Z;

	/**
	 * @var array
	 */
	private $ads = [];
	/**
	 * @var AdBlock
	 */
	private $block;

	public static function printBlock($blockId, $token = null, CMS $CMS = null)
	{
		global $Z;

		$a = new static($CMS ?: $Z);

		return $a->render($blockId, $token);
	}

	public static function incutBlocks($content, CMS $Z = null)
	{
		$ar1 = $ar2 = [];

		$blocks = \diCollection::create(Types::ad_block);
		foreach ($blocks as $block)
		{
			$ar1[] = sprintf(AdBlock::INCUT_TEMPLATE, $block->getId());
			$ar2[] = self::printBlock($block, '_AD_BLOCK', $Z);
		}

		$content = str_replace($ar1, $ar2, $content);

		return $content;
	}

	public function __construct(CMS $Z)
	{
		$this->Z = $Z;
	}

	protected function getZ()
	{
		return $this->Z;
	}

	public function getTpl()
	{
		return $this->getZ()->getTpl();
	}

	public function getTwig()
	{
		return $this->getZ()->getTwig();
	}

	protected function populateBlock($blockId)
	{
		$this->block = is_object($blockId) && $blockId instanceof \diModel
			? $blockId
			: \diModel::create(Types::ad_block, $blockId);

		return $this;
	}

	protected function blockIsNeeded()
	{
		return true;
	}

	public function render($blockId, $token = null)
	{
		$this->populateBlock($blockId);

		if ($this->blockIsNeeded() && !$this->getBlock()->exists())
		{
			return '';
		}

		$this->checkTitleAndContentOfSlides();

		if ($this->getTpl() && $this->getTpl()->defined('ad_block'))
		{
			return $this->oldRender($token);
		}

		$templateName = $this->getTwig()->exists(static::CUSTOM_TEMPLATE_NAME)
			? static::CUSTOM_TEMPLATE_NAME
			: static::TEMPLATE_NAME;

		$data = [
			'block' => $this->getBlock(),
			'ads' => $this->getAds(),
		];

		return $token
			? $this->getTwig()
				->render($templateName, $token, $data)
				->get($token)
			: $this->getTwig()
				->parse($templateName, $data);
	}

	/**
	 * @return AdBlock
	 * @throws \Exception
	 */
	protected function getBlock()
	{
		return $this->block ?: \diModel::create(Types::ad_block);
	}

	protected function getHolidayDates()
	{
		$ar = preg_split('#[;,\s]+#', \diConfiguration::safeGet('holidays'));

		return $ar;
	}

	protected function isHoliday()
	{
		return !!array_intersect([
			\diDateTime::format('m/d'),
			\diDateTime::format('d.m'),
		], $this->getHolidayDates());
	}

	/**
	 * @return Collection
	 * @throws \Exception
	 */
	protected function getAds()
	{
		if (!isset($this->ads[$this->getBlock()->getId()]))
		{
			$this->ads[$this->getBlock()->getId()] = $this->fetchAds();
		}

		return $this->ads[$this->getBlock()->getId()];
	}

	protected function fetchAds()
	{
		$wd = \diDateTime::weekDay();

		$holidayValues = [
			ShowOnHolidays::always,
			$this->isHoliday()
				? ShowOnHolidays::only
				: ShowOnHolidays::except,
		];

		/** @var Collection $col */
		$col = \diCollection::create(Types::ad);
		$col
			->filterByBlockId($this->getBlock()->getId());

		if ($this->considerDates())
		{
			$col
				->filterManual('show_date1 IS NULL OR NOW() >= show_date1')
				->filterManual('show_date2 IS NULL OR NOW() <= show_date2');
		}

		if ($this->considerTimes())
		{
			$col
				->filterManual('show_time1 IS NULL OR NOW() >= show_time1')
				->filterManual('show_time2 IS NULL OR NOW() <= show_time2');
		}

		if ($this->considerWeekdays())
		{
			$col
				->filterManual("show_on_weekdays = '' OR INSTR(show_on_weekdays, ',$wd,') > 0");
		}

		if ($this->considerHolidays())
		{
			$col
				->filterByShowOnHolidays($holidayValues);
		}

		$col
			->filterByVisible(1)
			->orderByOrderNum();

		return $col;
	}

	protected function considerDates()
	{
		return true;
	}

	protected function considerTimes()
	{
		return true;
	}

	protected function considerWeekdays()
	{
		return true;
	}

	protected function considerHolidays()
	{
		return true;
	}

	protected function checkTitleAndContentOfSlides()
	{
		if (!$this->getBlock()->hasDefaultSlideTitle() && !$this->getBlock()->hasDefaultSlideContent())
		{
			return $this;
		}

		/** @var Model $ad */
		foreach ($this->getAds() as $ad)
		{
			if (!$ad->hasTitle())
			{
				$ad->setTitle($this->getBlock()->getDefaultSlideTitle());
			}

			if (!$ad->hasContent())
			{
				$ad->setContent($this->getBlock()->getDefaultSlideContent());
			}
		}

		return $this;
	}

	protected function oldRender($token)
	{
		self::getTpl()
			->clear('AD_ROWS');

		/** @var Model $ad */
		foreach ($this->getAds() as $ad)
		{
			$this->getTpl()
				->assign($ad->getTemplateVarsExtended(), 'A_')
				->process('AD_ROWS', '.ad_row');
		}

		$this->getTpl()
			->assign($this->getBlock()->getTemplateVarsExtended(), 'AB_');

		return $this->getAds()->count()
			? $this->getTpl()->parse($token, 'ad_block')
			: '';
	}
}