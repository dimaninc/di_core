<?php
/*
    // dimaninc

	// 2015/06/18
		* refactor for diLib

    // 2012/10/19
        * ditags -> dioldtags
        * dibasictags -> ditags

    // 2012/10/03
        * dibasictags added

    // 2010/07/16
        * ::is_tag_assigned() added
        * ::get_id_by_ct() added
        * checkboxes support added
        * ::update_tag_quantity() added

    // 2009/07/14
        * ::get_target_ids_ar() improved: tag clean_title support added

    // 2008/10/10
        * table names is now customizable (::tables_ar)

    // 2008/09/24
        * category_id support added

    // 2008/08/03
        * created
*/

class diTags
{
	/** @var diDB */
	private $db;

	protected $new_field_suffix;

	protected $outputGlue = ", ";
	protected $inputGlue = "/,/";

	protected $tables = [
		"tags" => "tags",
		"map" => "tag_links",
	];
	protected $fields = [
		"tag_id" => "tag_id",
		"target_type" => "target_type",
		"target_id" => "target_id",
	];

	public function __construct()
	{
		global $db;

		$this->db = $db;
		$this->new_field_suffix = diAdminForm::NEW_FIELD_SUFFIX;
	}

	protected function getDb()
	{
		return $this->db;
	}

	public static function create($opts = [])
	{
		$opts = extend([
			"tags_table" => "",
			"map_table" => "",

			"tag_id_field" => "",
			"target_type_field" => "",
			"target_id_field" => "",
		], $opts);

		/** @var diTags $t */
		$t = new static();

		$t->setTables([
			"tags" => $opts["tags_table"] ?: $opts["tag_id_field"] . "s",
			"map" => $opts["map_table"] ?: $opts["target_id_field"] . "_" . $opts["tag_id_field"] . "_links",
		])->setFields([
			"tag_id" => $opts["tag_id_field"],
			"target_type" => $opts["target_type_field"],
			"target_id" => $opts["target_id_field"],
		]);

		return $t;
	}

	public function setTables($ar)
	{
		$this->tables = extend($this->tables, $ar);

		return $this;
	}

	public function setFields($ar)
	{
		$this->fields = extend($this->fields, $ar);

		return $this;
	}

	public function getTableName($table)
	{
		return $this->tables[$table];
	}

	public function getSubQueryForTargetId($targetType, $tagId)
	{
		return "SELECT {$this->fields["target_id"]} FROM {$this->tables["map"]} WHERE {$this->fields["target_type"]}='$targetType' and {$this->fields["tag_id"]}='$tagId'";
	}

	public function getTags($targetType, $targetId)
	{
		$ar = [];

		$queryAr = [
			"m." . $this->fields["target_type"] . "='$targetType'",
			"m." . $this->fields["target_id"] . diDB::in($targetId),
			"t.visible='1'",
		];

		$tag_rs = $this->getDb()->rs(
			"{$this->tables["map"]} m INNER JOIN {$this->tables["tags"]} t ON t.id=m.{$this->fields["tag_id"]}",
			"WHERE " . join(" AND ", $queryAr) . " ORDER BY t.title ASC",
			"t.*"
		);
		while ($tag_r = $this->getDb()->fetch($tag_rs))
		{
			$ar[] = $tag_r;
		}

		return $ar;
	}

	public function getTagsByIds($ids)
	{
		$ar = [];

		$tag_rs = $this->getDb()->rs($this->getTableName("tags"), $ids);
		while ($tag_r = $this->getDb()->fetch($tag_rs))
		{
			$ar[$tag_r->id] = $tag_r;
		}

		return $ar;
	}

	public static function tagsByIds($ids)
	{
		$o = new static();

		return $o->getTagsByIds($ids);
	}

	public function printTags($targetType, $targetId, $printFunction)
	{
		$ar = $this->getTags($targetType, $targetId);

		foreach ($ar as $tag_r)
		{
			$ar[] = $printFunction($tag_r, $targetType, $targetId);
		}

		return join($this->outputGlue, $ar);
	}

	public function isTagAssigned($targetType, $targetId, $tagId)
	{
		if (!$targetId)
		{
			return false;
		}

		$r = $this->getDb()->r($this->tables["map"],
			"WHERE {$this->fields["target_type"]}='$targetType' and {$this->fields["target_id"]}='$targetId' and {$this->fields["tag_id"]}='$tagId'"
		);

		return !!$r;
	}

	public static function saveFromPost($targetType, $targetId, $postVarKey)
	{
		$o = new static();

		return $o->storeTagsFromPost($targetType, $targetId, $postVarKey);
	}

	public static function saveTargetsFromPost($targetType, $tagId, $postVarKey)
	{
		$o = new static();

		return $o->storeTargetsFromPost($targetType, $tagId, $postVarKey);
	}

	public function storeTagsFromPost($targetType, $targetId, $postVarKey)
	{
		return $this->storeTags($targetType, $targetId,
			isset($_POST[$postVarKey]) && is_array($_POST[$postVarKey]) ? $_POST[$postVarKey] : [],
			isset($_POST[$postVarKey . $this->new_field_suffix]) ? $_POST[$postVarKey . $this->new_field_suffix] : ""
		);
	}

	protected function getDbArForMapRecord($targetType, $targetId, $tagId)
	{
		return [
			$this->fields["target_type"] => $targetType,
			$this->fields["target_id"] => $targetId,
			$this->fields["tag_id"] => $tagId,
		];
	}

	protected function storeMapRecord($targetType, $targetId, $tagId)
	{
		$ar = $this->getDbArForMapRecord($targetType, $targetId, $tagId);

		$this->getDb()->insert($this->tables["map"], $ar);
	}

	protected function beforeStoreTags($targetType, $targetId, $tagsAr, $tagsStr = "")
	{
		return $this;
	}

	public function storeTags($targetType, $targetId, $tagsAr, $tagsStr = "")
	{
		$this->beforeStoreTags($targetType, $targetId, $tagsAr, $tagsStr);

		$this->getDb()->delete($this->tables["map"],
			"WHERE {$this->fields["target_type"]} = '$targetType' and {$this->fields["target_id"]} = '$targetId'"
		);

		$counter = 0;

		$ar = preg_split($this->inputGlue, $tagsStr);

		foreach ($ar as $tagTitle)
		{
			$tagTitle = trim($tagTitle);

			if (!$tagTitle)
			{
				continue;
			}

			$this->storeMapRecord($targetType, $targetId, $this->getTagIdByTitle($tagTitle));

			$counter++;
		}

		foreach ($tagsAr as $tagId)
		{
			$tagId = (int)$tagId;

			if (!$tagId)
			{
				continue;
			}

			$this->storeMapRecord($targetType, $targetId, $tagId);

			$counter++;
		}

		return $counter;
	}

	public function getTagIdByTitle($title, $createIfNotExists = true)
	{
		if ($title)
		{
			/** @var diTagModel $tag */
			$tag = diCollection::create(diTypes::tag)->filterBy('title', $title)->getFirstItem();

			if ($tag->exists())
			{
				return $tag->getId();
			}

			if ($createIfNotExists)
			{
				$tag
					->setTitle($title)
					->save();

				return $tag->getId();
			}
		}
		else
		{
			if ($createIfNotExists)
			{
				throw new Exception("Unable to create Tag with empty Title");
			}
		}

		return null;
	}

	public function storeTargetsFromPost($targetType, $tagId, $postVarKey)
	{
		if (isset($_POST[$postVarKey]) && is_array($_POST[$postVarKey]))
		{
			return $this->storeTargets($targetType, $tagId, $_POST[$postVarKey]);
		}

		return false;
	}

	public function storeTargets($targetType, $tagId, $targets)
	{
		$this->getDb()->delete($this->tables["map"], "WHERE {$this->fields["target_type"]}='$targetType' and {$this->fields["tag_id"]}='$tagId'");

		$counter = 0;

		foreach ($targets as $targetId)
		{
			$targetId = (int)$targetId;

			if (!$targetId)
			{
				continue;
			}

			$this->storeMapRecord($targetType, $targetId, $tagId);

			$counter++;
		}

		return $counter;
	}

	public static function tagRecords($type, $targetId, $template = null)
	{
		$o = new static();

		return $o->getTagRecords($type, $targetId, $template);
	}

	public static function targetRecords($type, $tagId)
	{
		$o = new static();

		return $o->getTargetRecords($type, $tagId);
	}

	public function getTagRecords($type, $targetId, $template = null)
	{
		$qAr = [
			"m.{$this->fields["target_type"]}='$type'",
			"m.{$this->fields["target_id"]}" . $this->getDb()->in($targetId),
		];

		$rs = $this->getDb()->rs("{$this->tables["map"]} m INNER JOIN {$this->tables["tags"]} t " .
			"ON m.{$this->fields["tag_id"]}=t.id",
			"WHERE " . join(" AND ", $qAr),
			"t.*"
		);

		if ($template)
		{
			$ar = [];

			while ($r = $this->getDb()->fetch($rs))
			{
				if (is_callable($template))
				{
					$ar[] = $template($r);
				}
				else
				{
					$ar1 = [];
					$ar2 = [];

					foreach ($r as $k => $v)
					{
						$ar1[] = "%$k%";
						$ar2[] = $v;
					}

					$ar[] = str_replace($ar1, $ar2, $template);
				}
			}

			return $ar;
		}

		return $rs;
	}

	public function getTargetRecords($type, $tagId)
	{
		$qAr = [
			"m.{$this->fields["target_type"]}='$type'",
			"m.{$this->fields["tag_id"]}" . $this->getDb()->in($tagId),
		];

		return $this->getDb()->rs("{$this->tables["map"]} m INNER JOIN " . diTypes::getTable($type) . " t " .
			"ON m.{$this->fields["target_id"]}=t.id",
			"WHERE " . join(" AND ", $qAr),
			"t.*"
		);
	}

	public static function tagIdsStr($type, $targetId)
	{
		$o = new static();

		return $o->getTagIdsStr($type, $targetId);
	}

	public static function tagIdsAr($type, $targetId)
	{
		$o = new static();

		return $o->getTagIdsAr($type, $targetId);
	}

	public static function targetIdsStr($type, $tagId)
	{
		$o = new static();

		return $o->getTargetIdsStr($type, $tagId);
	}

	public static function targetIdsAr($type, $tagId)
	{
		$o = new static();

		return $o->getTargetIdsAr($type, $tagId);
	}

	public function getTagIdsStr($type, $targetId)
	{
		return join(",", $this->getTagIdsAr($type, $targetId));
	}

	public function getTagIdsAr($type, $targetId)
	{
		$ar = [];

		$qAr = [
			"{$this->fields["target_type"]}='$type'",
			"{$this->fields["target_id"]}" . $this->getDb()->in($targetId),
		];

		$rs = $this->getDb()->rs($this->tables["map"], "WHERE " . join(" AND ", $qAr));
		while ($r = $this->getDb()->fetch($rs))
		{
			$ar[] = $r->{$this->fields["tag_id"]};
		}

		return $ar;
	}

	public function getTargetIdsStr($type, $tagId)
	{
		return join(",", $this->getTargetIdsAr($type, $tagId));
	}

	public function getTargetIdsAr($type, $tagId)
	{
		$ar = [];

		$qAr = [
			"{$this->fields["target_type"]}='$type'",
			"{$this->fields["tag_id"]}" . $this->getDb()->in($tagId),
		];

		$rs = $this->getDb()->rs($this->tables["map"], "WHERE " . join(" AND ", $qAr));
		while ($r = $this->getDb()->fetch($rs))
		{
			$ar[] = $r->{$this->fields["target_id"]};
		}

		return $ar;
	}
}