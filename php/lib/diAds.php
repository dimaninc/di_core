<?php
class diAds
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

	static $adTransitionsAr = array(
		self::TRANSITION_DEFAULT => "По умолчанию",
		self::TRANSITION_CROSS_FADE => "Проявление (crossfade)",
		self::TRANSITION_SLIDE_L2R => "Скроллинг (слева направо)",
		self::TRANSITION_SLIDE_R2L => "Скроллинг (справа налево)",
		self::TRANSITION_SLIDE_T2B => "Скроллинг (сверху вниз)",
		self::TRANSITION_SLIDE_B2T => "Скроллинг (снизу вверх)",
	);


	static $adTransitionStylesAr = array(
		self::TRANSITION_STYLE_DEFAULT => "По умолчанию",
		self::TRANSITION_STYLE_BOTH_SLIDING => "Новый слайд вытесняет старый",
		self::TRANSITION_STYLE_ONLY_NEW_SLIDING => "Новый слайд наезжает на старый",
	);


	static $adSlidesOrdersAr = array(
		self::ORDER_IN_ORDER => "По порядку",
		self::ORDER_RANDOM => "В случайном порядке",
	);

	/**
	 * @var diCMS
	 */
	private $Z;

	/**
	 * @var diAdCollection
	 */
	private $ads;

	public function __construct(diCMS $Z)
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

	public function render($blockId, $token = null)
	{
		if (is_object($blockId) && $blockId instanceof diModel)
		{
			$block = $blockId;
		}
		else
		{
			/** @var diAdBlockModel $block */
			$block = diModel::create(diTypes::ad_block, $blockId);
		}

		if (!$block->exists())
		{
			return "";
		}

		$this->ads = diCollection::create(diTypes::ad,
			"WHERE block_id='{$block->getId()}' and visible='1' ORDER BY order_num ASC"
		);

		$this->checkTitleAndContentOfSlides($block);

		if ($this->getTpl() && $this->getTpl()->defined("ad_block"))
		{
			return $this->oldRender($block, $token);
		}

		$templateName = $this->getTwig()->exists(static::CUSTOM_TEMPLATE_NAME)
			? static::CUSTOM_TEMPLATE_NAME
			: static::TEMPLATE_NAME;

		$this->getTwig()
			->assign([
				"block" => $block,
				"ads" => $this->ads,
			]);

		return $token
			? $this->getTwig()
				->render($templateName, $token)
				->get($token)
			: $this->getTwig()
				->parse($templateName);
	}

	protected function checkTitleAndContentOfSlides(diAdBlockModel $block)
	{
		if (!$block->hasDefaultSlideTitle() && !$block->hasDefaultSlideContent())
		{
			return $this;
		}

		/** @var diAdModel $ad */
		foreach ($this->ads as $ad)
		{
			if (!$ad->hasTitle())
			{
				$ad->setTitle($block->getDefaultSlideTitle());
			}

			if (!$ad->hasContent())
			{
				$ad->setContent($block->getDefaultSlideContent());
			}
		}

		return $this;
	}

	protected function oldRender(diAdBlockModel $block, $token)
	{
		self::getTpl()
			->clear("AD_ROWS");

		/** @var diAdModel $ad */
		foreach ($this->ads as $ad)
		{
			$this->getTpl()
				->assign($ad->getTemplateVarsExtended(), "A_")
				->process("AD_ROWS", ".ad_row");
		}

		$this->getTpl()
			->assign($block->getTemplateVarsExtended(), "AB_");

		return $this->ads->count()
			? $this->getTpl()->parse($token, "ad_block")
			: "";
	}

	public static function printBlock($blockId, $token = null, diCMS $CMS = null)
	{
		global $Z;

		$a = new static($CMS ?: $Z);

		return $a->render($blockId, $token);
	}

	public static function incutBlocks($content, diCMS $Z = null)
	{
		$ar1 = $ar2 = [];

		$blocks = diCollection::create(diTypes::ad_block);
		/** @var diAdBlockModel $block */
		foreach ($blocks as $block)
		{
			$ar1[] = sprintf("[AD-BLOCK-%d]", $block->getId());
			$ar2[] = self::printBlock($block, "_AD_BLOCK", $Z);
		}

		$content = str_replace($ar1, $ar2, $content);

		return $content;
	}
}