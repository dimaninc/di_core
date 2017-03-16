<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 04.01.16
 * Time: 11:19
 */

class diCommentsPage extends diAdminBasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "id",
				"dir" => "DESC",
			],
		],
	];

	protected function initTable()
	{
		$this->setTable("comments");
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			"id" => "ID",
			"#href" => [
				"href" => function (diCommentModel $m) {
					return $m->getTargetModel()->getHref();
				},
			],
			"target_id" => [
				"headAttrs" => [
					"width" => "30%",
				],
				"value" => function (diCommentModel $m) {
					return $m->getDescriptionForAdmin();
				},
			],
			"user_id" => [
				"headAttrs" => [
					"width" => "20%",
				],
				"value" => function (diCommentModel $m) {
					return $m->getUserAppearance();
				},
			],
			"content" => [
				"headAttrs" => [
					"width" => "40%",
				],
				"bodyAttrs" => [
					"class" => "lite",
				],
			],
			"date" => [
				"title" => "Дата",
				"value" => function (diCommentModel $m) {
					return date("d.m.Y H:i", strtotime($m->getDate()));
				},
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "dt",
				],
			],
			"#edit" => "",
			"#del" => "",
			"#visible" => "",
		]);
	}

	public function renderForm()
	{
		/** @var diCommentModel $comment */
		$comment = $this->getForm()->getModel();
		$user = $comment->getUserModel();

		$this->getForm()
			->setInput(
				"target_id",
				$comment->getDescriptionForAdmin() . " [<a href=\"{$comment->getTargetModel()->getHref()}\" target=\"_blank\">ссылка</a>]"
			)
			->setInput("user_id", $comment->getUserAppearance($user) . " [<a href=\"{$user->getTable()}/form/{$user->getId()}/\" target=\"_blank\">ссылка</a>]");
	}

	public function submitForm()
	{
	}

	public function getFormTabs()
	{
		return [];
	}

	public function getFormFields()
	{
		return [
			"target_type" => [
				"type" => "int",
				"title" => "Тип записи",
				"default" => "",
				"flags" => ["hidden"],
			],

			"target_id" => [
				"type" => "int",
				"title" => "Комментарий к",
				"default" => 0,
				"flags" => ["static"],
			],

			"user_id" => [
				"type" => "int",
				"title" => "Автор",
				"default" => 0,
				"flags" => ["static"],
			],

			"content" => [
				"type" => "text",
				"title" => "Комментарий",
				"default" => "",
			],

			"ip" => [
				"type" => "ip",
				"title" => "IP/Host",
				"default" => "",
				"flags" => ["static"],
			],

			"date" => [
				"type" => "datetime_str",
				"title" => "Дата публикации",
				"default" => time(),
				"flags" => ["static"],
			],

			"karma" => [
				"type" => "int",
				"title" => "Карма",
				"default" => 0,
				"flags" => ["static"],
			],

			"evil_score" => [
				"type" => "int",
				"title" => "Уровень зла (модерация)",
				"default" => 0,
				"flags" => ["static"],
			],
		];
	}

	public function getLocalFields()
	{
		return [
			"parent" => [
				"type" => "int",
				"title" => "Parent",
				"default" => 0,
			],

			"level_num" => [
				"type" => "int",
				"title" => "Level num",
				"default" => 0,
			],
		];
	}

	public function getModuleCaption()
	{
		return "Комментарии";
	}

	public function addButtonNeededInCaption()
	{
		return false;
	}
}