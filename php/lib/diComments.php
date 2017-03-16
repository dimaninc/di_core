<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 07.07.2015
 * Time: 16:43
 */

use diCore\Entity\Comment\Model as CommentModel;

class diComments
{
	const MODERATED_BEFORE_SHOW = false;

	const MODE_INITIAL = 1;
	const MODE_LOAD = 2;

	const className = "diCustomComments";

	const utUser = 0;
	const utAdmin = 1;

	const RECENT_COMMENTS_COUNT = 5;
	const COMMENTS_COUNT_PER_LOAD = 5;
	const PAGE_PARAM = "cpage";

	const META_TITLE_COMMENT_PAGE_SUFFIX = "META_TITLE_COMMENTS_SUFFIX";

	/** @var diDB */
	private $db;
	/** @var FastTemplate */
	private $tpl;
	/** @var diTwig */
	private $Twig;

	protected $table = "comments";

	private $mode = self::MODE_INITIAL;

	protected $targetType;
	protected $targetId;

	/** @var array */
	protected $queryAr;

	/** @var integer */
	protected $totalCount;

	/** @var integer */
	protected $totalTopLevelCount;

	/** @var  \diCore\Entity\Comment\Collection */
	protected $comments;

	/** @var  diUserCollection */
	protected $users;

	/** @var  diAdminCollection */
	protected $admins;

	protected $usePagesNavy = false;

	/** @var  diPagesNavy */
	protected $PagesNavy;

	public function __construct($targetType, $targetId)
	{
		global $db;

		$this->db = $db;

		$this->targetType = $targetType;
		$this->targetId = $targetId;

		$this->queryAr = [
			"target_type = '$this->targetType'",
			"target_id = '$this->targetId'",
		];

		$this->initCounts();

		if ($this->usePagesNavy)
		{
			$this->initPagesNavy();
		}
	}

	/**
	 * @return diComments
	 */
	public static function create($targetType, $targetId)
	{
		$className = diLib::exists(self::className)
			? self::className
			: get_called_class();

		$o = new $className($targetType, $targetId);

		return $o;
	}

	public static function moderatedBeforeShow()
	{
		return static::MODERATED_BEFORE_SHOW;
	}

	public function getRecentCommentsCount()
	{
		return static::RECENT_COMMENTS_COUNT;
	}

	public function getCommentsCountPerLoad()
	{
		return static::COMMENTS_COUNT_PER_LOAD;
	}

	protected function initPagesNavy()
	{
		$this->PagesNavy = new diPagesNavy($this->table, [
			'initial' => $this->getMode() == self::MODE_INITIAL ? $this->getRecentCommentsCount() : $this->getCommentsCountPerLoad(),
			'load' => $this->getCommentsCountPerLoad(), // this is not used yet
		], "WHERE " . $this->getBaseQuery(), false, static::PAGE_PARAM);

		return $this;
	}

	protected function authorized()
	{
		return diAuth::i()->authorized();
	}

	protected function getFormTemplateName()
	{
		return $formTemplate = $this->authorized() ? "comment_form" : "comment_auth";
	}

	public function printForm()
	{
		$this->getTpl()
			->assign([
				"TARGET_TYPE" => $this->targetType,
				"TARGET_ID" => $this->targetId,
				"TOTAL_COUNT" => $this->getTotalCount(),
				'MODERATED_BEFORE_SHOW' => static::moderatedBeforeShow() ? 'true' : 'false',
			], "COMMENT_")
			->process("COMMENT_FORM", $this->getFormTemplateName());

		return $this;
	}

	protected function getMetaTitleSuffix()
	{
		$page = $this->getPagesNavy()->getPage();

		if ($this->usePagesNavy && $page > 1)
		{
			return $this->generateMetaTitleSuffix($page);
		}

		return "";
	}

	protected function generateMetaTitleSuffix($page)
	{
		return sprintf(" Страница %d", $page);
	}

	protected function prepareToRenderBlock()
	{
		$this->printForm();

		$this->getTpl()
			->assign([
				"COMMENT_ROWS" => $this->getRowsHtml(),
				static::META_TITLE_COMMENT_PAGE_SUFFIX => $this->getMetaTitleSuffix(),
				"COMMENT_AUTH_CLASS_NAME" => $this->authorized() ? "auth-ok" : "auth-needed",
			]);

		return $this;
	}

	public function getBlockHtml()
	{
		$this->prepareToRenderBlock();

		return $this->getTpl()->parse("comments_block");
	}

	protected function beforeParseRow(CommentModel $comment, diBaseUserModel $user)
	{
		return $this;
	}

	protected function getUserModelForComment(CommentModel $comment)
	{
		if ($this->admins && $this->users)
		{
			$user = $comment->getUserType() == diComments::utAdmin
				? $this->admins[$comment->getUserId()]
				: $this->users[$comment->getUserId()];
		}
		else
		{
			$user = diModel::create(
				$comment->getUserType() == self::utAdmin ? diTypes::admin : diTypes::user,
				$comment->getUserId(),
				"id"
			);
		}

		if (!$user)
		{
			$user = diModel::create(diTypes::user);
		}

		return $user;
	}

	public function getRowHtml(CommentModel $comment)
	{
		$user = $this->getUserModelForComment($comment);

		$this->getTpl()
			->assign($comment->getTemplateVarsExtended(), "C_")
			->assign($user->getTemplateVarsExtended(), "C_U_");

		$this->beforeParseRow($comment, $user);

		return $this->getTpl()
			->parse("comment_row");
	}

	protected function getBaseQuery()
	{
		return join(" AND ", $this->queryAr);
	}

	protected function getAdditionalQueryAr()
	{
		return [
			//"visible='1'",
		];
	}

	protected function getInitialQueryAr()
	{
		return [];
	}

	protected function getPastQueryAr($firstCommentId)
	{
		return [];
	}

	public function getInitialCommentsCollection()
	{
		return $this->getCommentsCollection(array_merge($this->queryAr, $this->getAdditionalQueryAr(), $this->getInitialQueryAr()));
	}

	public function getPastCommentsCollection($firstCommentId, $size = null)
	{
		return $this->getCommentsCollection(array_merge($this->queryAr, $this->getAdditionalQueryAr(), $this->getPastQueryAr($firstCommentId)), $size);
	}

	protected function getCommentsCollection($queryAr, $size = null)
	{
		$adminIds = [];
		$userIds = [];

		//echo "WHERE " . join(" AND ", $queryAr) . " LIMIT = " . $size;

		$this->comments = diCollection::create(diTypes::comment, "WHERE " . join(" AND ", $queryAr));
		$this->comments->orderBy('order_num');

		if ($size !== null)
		{
			$this->comments
				->setPageSize($size)
				->setPageNumber(1);
		}

		/** @var CommentModel $comment */
		foreach ($this->comments as $comment)
		{
			if ($comment->getUserType() == diComments::utAdmin)
			{
				$adminIds[] = (int)$comment->getUserId();
			}
			else
			{
				$userIds[] = (int)$comment->getUserId();
			}
		}

		$this->users = diCollection::create(diTypes::user, "WHERE id" . diDB::in(array_unique($userIds), true));
		$this->admins = diCollection::create(diTypes::admin, "WHERE id" . diDB::in(array_unique($adminIds), true));

		return $this->comments;
	}

	public function getRowsHtml()
	{
		$rows = [];
		$this->getInitialCommentsCollection();

		foreach ($this->comments as $comment)
		{
			$rows[] = $this->getRowHtml($comment);
		}

		return join("", $rows);
	}

	private function initCounts()
	{
		$queryAr = array_merge($this->queryAr, [
			"visible = '1'",
		]);

		$r = $this->getDb()->r(
			$this->table,
			"WHERE " . join(" AND ", $queryAr),
			"COUNT(id) AS total," .
			"SUM(CASE WHEN level_num = '0' THEN 1 ELSE 0 END) total_level_0"
		);

		$this->totalCount = (int)$r->total;
		$this->totalTopLevelCount = (int)$r->total_level_0;

		return $this;
	}

	/**
	 * @return diPagesNavy
	 */
	protected function getPagesNavy()
	{
		if (!$this->usePagesNavy)
		{
			die("diPagesNavy is not enabled in comments here");
		}

		return $this->PagesNavy;
	}

	public function getTotalCount()
	{
		return $this->totalCount;
	}

	public function getTotalTopLevelCount()
	{
		return $this->totalTopLevelCount;
	}

	public function incTotalCount()
	{
		return ++$this->totalCount;
	}

	public function decTotalCount()
	{
		return --$this->totalCount;
	}

	public function incTotalTopLevelCount()
	{
		return ++$this->totalTopLevelCount;
	}

	public function decTotalTopLevelCount()
	{
		return --$this->totalTopLevelCount;
	}

	protected function getDb()
	{
		return $this->db;
	}

	public function setTpl(FastTemplate $tpl)
	{
		$this->tpl = $tpl;

		return $this;
	}

	public function getTpl()
	{
		return $this->tpl;
	}

	public function setTwig(diTwig $twig)
	{
		$this->Twig = $twig;

		return $this;
	}

	public function getTwig()
	{
		return $this->Twig;
	}

	/**
	 * @return int
	 */
	public function getMode()
	{
		return $this->mode;
	}

	/**
	 * @param int $mode
	 */
	public function setMode($mode)
	{
		$this->mode = $mode;

		return $this;
	}
}