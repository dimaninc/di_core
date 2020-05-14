<?php
/**
 * Created by \diModelsManager
 * Date: 03.03.2017
 * Time: 19:08
 */

namespace diCore\Entity\Comment;

use diCore\Helper\StringHelper;
use diCore\Tool\CollectionCache;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getUserType
 * @method integer	getUserId
 * @method integer	getOwnerId
 * @method integer	getParent
 * @method integer	getTargetType
 * @method integer	getTargetId
 * @method string	getContent
 * @method string	getDate
 * @method integer	getIp
 * @method integer	getOrderNum
 * @method integer	getLevelNum
 * @method integer	getVisible
 * @method integer	getModerated
 * @method integer	getKarma
 * @method integer	getEvilScore
 *
 * @method bool hasUserType
 * @method bool hasUserId
 * @method bool hasOwnerId
 * @method bool hasParent
 * @method bool hasTargetType
 * @method bool hasTargetId
 * @method bool hasContent
 * @method bool hasDate
 * @method bool hasIp
 * @method bool hasOrderNum
 * @method bool hasLevelNum
 * @method bool hasVisible
 * @method bool hasModerated
 * @method bool hasKarma
 * @method bool hasEvilScore
 *
 * @method Model setUserType($value)
 * @method Model setUserId($value)
 * @method Model setOwnerId($value)
 * @method Model setParent($value)
 * @method Model setTargetType($value)
 * @method Model setTargetId($value)
 * @method Model setContent($value)
 * @method Model setDate($value)
 * @method Model setIp($value)
 * @method Model setOrderNum($value)
 * @method Model setLevelNum($value)
 * @method Model setVisible($value)
 * @method Model setModerated($value)
 * @method Model setKarma($value)
 * @method Model setEvilScore($value)
 */
class Model extends \diModel
{
	const type = \diTypes::comment;
	protected $table = 'comments';

	/** @var  \diModel */
	protected $target;
	/** @var  \diModel */
	protected $user;

	const COMMENTS_COUNT_FIELD = 'comments_count';
	const COMMENTS_LAST_DATE_FIELD = 'comments_last_date';

	const CONTENT_CUT_LENGTH = 100;

	const UPDATE_COLLECTION_CACHE_ON_UPDATE = false;

	protected static $userExcludeFields = [
		'password',
		'activation_key',
	];

	protected function updateCommentsCountForTargetNeeded()
	{
		return false;
	}

	public function validate()
	{
		if (!$this->getContent())
		{
			$this->addValidationError('Content required');
		}

		if (!$this->getTargetType() || !$this->getTargetId())
		{
			$this->addValidationError('Target required');
		}

		return parent::validate();
	}

	// todo: make some tags to pass into comment's content
	public function getCustomTemplateVars()
	{
		$contentCut = nl2br(StringHelper::out(StringHelper::cutEnd($this->getContent(), self::CONTENT_CUT_LENGTH)));
		$contentHtml = nl2br(StringHelper::out($this->getContent()));
		$contentHtmlWithLinks = $this->getContentHtmlWithLinks();

		return extend(parent::getCustomTemplateVars(), [
			'content_html' => $contentHtml,
			'content_html_with_links' => $contentHtmlWithLinks,
			'content_cut' => $contentCut,
		]);
	}

	public function getContentHtmlWithLinks()
	{
		return nl2br(StringHelper::wrapUrlWithTag(StringHelper::out($this->getContent())));
	}

	public function beforeSave()
	{
		parent::beforeSave();

		// order_num, level_num
		if (!$this->getId())
		{
			$h = new \diHierarchyCommentsTable();

			$skipIdsAr = $h->getChildrenIdsAr($this->getParent(), array($this->getParent()));
			$r = $this->getDb()->r($this->getTable(), $skipIdsAr, 'MAX(order_num) AS num');

			$this
				->setLevelNum($h->getChildLevelNum($this->getParent()))
				->setOrderNum((int)$r->num + 1);

			$this->getDb()->update($this->getTable(), [
				'*order_num' => 'order_num+1',
			], "WHERE order_num >= '{$this->getOrderNum()}'");
		}
		//

		//ip
		if (!$this->getIp())
		{
			$this->setIp(ip2bin());
		}
		//

		return $this;
	}

	public function afterSave()
	{
		parent::afterSave();

		if ($this->updateCommentsCountForTargetNeeded() && $this->getTargetModel()->exists(static::COMMENTS_COUNT_FIELD))
		{
			$this->getTargetModel()
				->set(static::COMMENTS_COUNT_FIELD, $this->getTargetModel()->get(static::COMMENTS_COUNT_FIELD) + 1)
				->set(static::COMMENTS_LAST_DATE_FIELD, \diDateTime::format(\diDateTime::FORMAT_SQL_DATE_TIME))
				->save();
		}

		return $this;
	}

	protected function afterKill()
	{
		parent::afterKill();

		if ($this->updateCommentsCountForTargetNeeded() && $this->getTargetModel()->exists(static::COMMENTS_COUNT_FIELD))
		{
			$this->getTargetModel()
				->set(static::COMMENTS_COUNT_FIELD, $this->getTargetModel()->get(static::COMMENTS_COUNT_FIELD) - 1)
				->save();
		}

		if ($this->hasVisible())
		{
			$this->afterToggleVisible();
		}

		return $this;
	}

	public function afterToggleVisible()
	{
		if (static::UPDATE_COLLECTION_CACHE_ON_UPDATE)
		{
			$Comments = \diComments::create($this->getTargetType(), $this->getTargetId());
			$Comments
				->updateCache(true);
		}
	}

	/**
	 * @return \diModel
	 * @throws \Exception
	 */
	public function getUserModel()
	{
		if (!$this->user)
		{
			if (!($this->user = $this->getRelated('user')))
			{
				$this->user = CollectionCache::getModel(
					$this->getUserType() == \diComments::utAdmin ? \diTypes::admin : \diTypes::user,
					$this->getUserId(),
					true
				);
			}
		}

		return $this->user;
	}

	/**
	 * @return \diModel
	 * @throws \Exception
	 */
	public function getTargetModel()
	{
		if (!$this->target || !$this->target->exists())
		{
			$this->target = \diModel::create($this->getTargetType(), $this->getTargetId(), 'id');
		}

		return $this->target;
	}

	public function setTargetModel(\diModel $target)
	{
		$this->target = $target;

		return $this;
	}

	protected function getHrefSuffix()
	{
		return '#comment' . $this->getId();
	}

	protected function getSuffixForPhpView()
	{
		$related = [
			"->setRelated('href', '{$this->getHref()}')",
		];

		/** @var \diCore\Entity\User\Model $user */
		if ($user = $this->getRelated('user'))
		{
			$related[] = "->setRelated('user', " . $user->asPhp(static::$userExcludeFields) . ")";
		}

		return join("\n", $related);
	}

	public function getHref()
	{
		// if href cached inside
		if ($this->getRelated('href'))
		{
			return $this->getRelated('href');
		}

		return $this->getTargetModel()->getHref() . $this->getHrefSuffix();
	}

	public function getUserAppearance(\diModel $user = null)
	{
		$user = $user ?: $this->getUserModel();
		$typeSuffix = $this->getUserType() == \diComments::utAdmin ? ' (Admin)' : '';

		return $user->getStringAppearanceForAdmin() . $typeSuffix;
	}

	public function getDescriptionForAdmin()
	{
		return
			\diTypes::getTitle($this->getTargetType()) . ': ' .
			($this->getTargetModel()->get('title') ?: $this->getTargetType() . '#' . $this->getTargetId());
	}

	public function isUserAllowed(\diCore\Entity\User\Model $user)
	{
		return $this->getUserType() == \diComments::utUser && $this->getUserId() == $user->getId();
	}
}