<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 28.05.2015
 * Time: 22:20
 */

use diCore\Entity\MailIncut\Model as IncutModel;
use diCore\Entity\MailIncut\Collection as IncutCollection;
use diCore\Data\Types;
use diCore\Admin\Submit;
use diCore\Tool\Mail\Queue;

class diNewsPage extends diAdminBasePage
{
	const DISPATCH_MODE_TEST = 1;
	const DISPATCH_MODE_STANDARD = 2;

	protected $slugFieldName = "clean_title";
	protected $slugSourceFieldName = "menu_title";

	protected $options = [
		"updateSearchIndexOnSubmit" => true,
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "date",
				"dir" => "DESC",
			],
			"sortByAr" => [
				"date" => "По дате",
			],
		],
	];

	protected $picOptions = [
		[
			"type" => Submit::IMAGE_TYPE_MAIN,
			"resize" => \diImage::DI_THUMB_FIT,
		],
		[
			"type" => Submit::IMAGE_TYPE_PREVIEW,
			"resize" => \diImage::DI_THUMB_CROP,
		],
	];

	protected function initTable()
	{
		$this->setTable("news");
	}

	protected function setupFilters()
	{
		$this->getFilters()
			->addFilter([
				"field" => "date",
				"type" => "date_str_range",
				"title" => "За период",
			])
			->buildQuery();
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			"id" => "ID",
			"#href" => [],
			"date" => [
				"title" => "Дата",
				"value" => function(diNewsModel $n) {
					return \diDateTime::format("d.m.Y H:i", $n->getDate());
				},
				"attrs" => [
					"width" => "10%",
				],
				"headAttrs" => [],
				"bodyAttrs" => [
					"class" => "dt",
				],
			],
			"title" => [
				"title" => "Заголовок",
				"attrs" => [
					"width" => "90%",
				],
			],
			"#edit" => "",
			"#del" => "",
			"#visible" => "",
		]);
	}

	public function renderForm()
	{
	}

	public function submitForm()
	{
		$this->getSubmit()
			->makeSlug()
			->storeImage("pic", $this->picOptions);
	}

	protected function afterSubmitForm()
	{
		parent::afterSubmitForm();

		$this->dispatchNewsletter();
	}

	protected function dispatchNewsletter()
	{
		$mq = Queue::basicCreate();
		/** @var diNewsModel $m */
		$m = \diModel::create(Types::news, $this->getSubmit()->getSubmittedModel()->getId());

		if (!\diRequest::post("dispatch", 0) && !\diRequest::post("dispatch_test", 0))
		{
			return $this;
		}

		$mode = \diRequest::post("dispatch_test", 0) ? self::DISPATCH_MODE_TEST : self::DISPATCH_MODE_STANDARD;

		$sender = \diConfiguration::get("newsletter_email");
		$subject = $m->getTitle();
		$content = $m->getContent();

		$newsLetterFlag = true;

		// attaches
		$attaches = [];
		$fileReplaces = [];

		$fn = \diPaths::fileSystem() . $m->getPicsFolder() . $m->getPic();
		$fileReplaces["{PIC}"] = "";

		if ($m->hasPic() && is_file($fn))
		{
			$imgContent = file_get_contents($fn);

			$ext = strtolower(\diCore\Helper\StringHelper::fileExtension($m->getPic()));

			if ($ext == "jpeg" || $ext == "jpg")
				$contentType = "image/jpeg";
			elseif ($ext == "gif" || $ext == "png")
				$contentType = "image/$ext";
			elseif ($ext == "swf")
				$contentType = "application/x-shockwave-flash";
			elseif ($ext == "exe")
				$contentType = "application/octet-stream";
			else
				$contentType = "application/octet-stream";

			$cid = get_unique_id();

			$attaches[] = [
				"filename" => "news." . $ext,
				"content_type" => $contentType,
				"data" => $imgContent,
				"content_id" => $cid,
			];

			$fileReplaces["{PIC}"] = "<img src=\"cid:$cid\">";

			IncutCollection::createBinaryAttachment()
				->filterByTargetType(Types::news)
				->filterByTargetId($m->getId())
				->hardDestroy();

			IncutModel::createBinaryAttachment(serialize($attaches), Types::news, $m->getId())
				->save();

			$content = str_replace(array_keys($fileReplaces), array_values($fileReplaces), $content);
		}
		//

		$body_prefix = <<<EOF
<!DOCTYPE html>
<html>
<head><title>1romantic.com</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<style>body{font-family:Verdana,sans-serif;font-size:14px;}</style>
</head>

<body>

<b>{$subject}</b><br />
{$content}<br />
EOF;

		$users = \diCollection::create(Types::user, $mode == self::DISPATCH_MODE_TEST
			? "WHERE email" . diDB::in(preg_split("/[\r\n\s,;]+/", \diConfiguration::get("newsletter_test_emails")))
			: "WHERE email!='' and notify_news='1' and newsletter_flag='0' and active='1' ORDER BY id ASC"
		);
		/** @var diUserCustomModel $user */
		foreach ($users as $user)
		{
			$recipient = $user->getEmail();

			$bodySuffix = <<<EOF

<br />
<br />
---<br />
<a href="https://1romantic.com/">1romantic.com</a>
</body>
EOF;

			/*
			<br /><br /><br />
			Отписаться от данной рассылки можно, перейдя по ссылке:
			<a href="http://strategy.ru/unsubscribe/$user_r->id-$user_r->activation_key/">http://strategy.ru/unsubscribe/$user_r->id-$user_r->activation_key/</a><br />
			*/

			$mq->add($sender, $recipient, $subject, $body_prefix . $bodySuffix, false, $m->getId());

			if ($newsLetterFlag)
			{
				$user
					->setNewsletterFlag(1)
					->save();
			}
		}

		if ($newsLetterFlag)
		{
			$this->getDb()->update("users", ["newsletter_flag" => 0]);
		}

		return $this;
	}

	public function getFormTabs()
	{
		return [
			//"pics" => "Фотографии",
			"meta" => "SEO",
		];
	}

	public function getFormFields()
	{
		return [
			"date" => [
				"type" => "datetime_str",
				"title" => "Дата публикации",
				"default" => date("Y-m-d H:i:s"),
			],

			"title" => [
				"type" => "string",
				"title" => "Заголовок",
				"default" => "",
			],

			$this->slugSourceFieldName => [
				"type" => "string",
				"title" => "Название для URL",
				"default" => "",
			],

			"short_content" => [
				"type" => "text",
				"title" => "Краткий текст",
				"default" => "",
			],

			"content" => [
				"type" => "wysiwyg",
				"title" => "Полный текст",
				"default" => "",
				//"notes"		=> array("Токен {PIC} будет заменен на подгруженную картинку"),
			],

			"pic" => [
				"type" => "pic",
				"title" => "Фото",
				"default" => "",
				"tab" => "pics",
			],

			"html_title" => [
				"type" => "string",
				"title" => "Meta-заголовок",
				"default" => "",
				"tab" => "meta",
			],

			"html_keywords" => [
				"type" => "string",
				"title" => "Meta-ключевые слова",
				"default" => "",
				"tab" => "meta",
			],

			"html_description" => [
				"type" => "string",
				"title" => "Meta-описание",
				"default" => "",
				"tab" => "meta",
			],
		];
	}

	public function getLocalFields()
	{
		return [
			$this->slugFieldName => [
				"type" => "string",
				"title" => "Clean title",
				"default" => "",
			],

			"order_num" => [
				"type" => "order_num",
				"title" => "Order num",
				"default" => 0,
				"direction" => -1,
			],

			"pic_t" => [
				"type" => "int",
				"title" => "",
				"default" => "",
			],

			"pic_w" => [
				"type" => "int",
				"title" => "",
				"default" => "",
			],

			"pic_h" => [
				"type" => "int",
				"title" => "",
				"default" => "",
			],

			"pic_tn_w" => [
				"type" => "int",
				"title" => "",
				"default" => "",
			],

			"pic_tn_h" => [
				"type" => "int",
				"title" => "",
				"default" => "",
			],
		];
	}

	public function getModuleCaption()
	{
		return "Новости";
	}
}