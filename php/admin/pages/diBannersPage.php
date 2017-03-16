<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.05.2015
 * Time: 20:38
 */

class diBannersPage extends diAdminBasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "date2",
				"dir" => "DESC",
			],
		],
	];

	protected function initTable()
	{
		$this->setTable("banners");
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			"id" => "ID",
			"format" => [
				"title" => "Формат",
				"value" => function(diBannerModel $banner) {
					return $banner->hasPic()
						? strtoupper(get_file_ext($banner->getPic())) . " {$banner->getPicW()}x{$banner->getPicH()}"
						: "Текстовый";
				},
				"attrs" => [
					"width" => "15%",
				],
			],
			"place" => [
				"attrs" => [
					"width" => "15%",
				],
				"value" => function(diBannerModel $banner) {
					return diBanners::$placesAr[$banner->getPlace()];
				},
			],
			"title" => [
				"attrs" => [
					"width" => "35%",
				],
			],
			"href" => [
				"attrs" => [
					"width" => "25%",
				],
				"bodyAttrs" => [
					"class" => "lite",
				],
			],
			"dates" => [
				"title" => "Даты",
				"value" => function(diBannerModel $banner) {
					return date("d.m.Y - ", strtotime($banner->getDate1())) . date("d.m.Y", strtotime($banner->getDate2()));
				},
				"attrs" => [
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

	protected function getAvailablePlaces()
	{
		return array_keys(diBanners::$placesAr);
	}

	public function renderForm()
	{
		$this->getForm()
			->setSelectFromArrayInput("place", diArrayHelper::filterByKey(diBanners::$placesAr, $this->getAvailablePlaces()))
			->setSelectFromArrayInput("href_target", diBanners::$hrefTargetsAr);

		// banner uris
		$uri_ar = [
			"positive" => [],
			"negative" => [],
		];

		$last_idx_ar = [
			"positive" => 0,
			"negative" => 0,
		];

		$js = "";

		$bu_rs = $this->getDb()->rs(diBanners::urisTable, "WHERE banner_id='" . $this->getId() . "' ORDER BY uri ASC");
		while ($bu_rs && $bu_r = $this->getDb()->fetch($bu_rs))
		{
			$uri_ar[$bu_r->positive ? "positive" : "negative"][] = str_out($bu_r->uri);
		}

		if (!count($uri_ar["positive"])) $uri_ar["positive"][0] = "/*";
		if (!count($uri_ar["negative"])) $uri_ar["negative"][0] = "";

		if ($this->getForm()->static_mode)
		{
			$this->getForm()
				->setInput("uris_positive", join("<br>", $uri_ar["positive"]))
				->setInput("uris_negative", join("<br>", $uri_ar["negative"]));
		}
		else
		{
			$js .= "var diref_" . $this->getForm()->getTable() . " = new diDynamicRows();\n";

			foreach ($uri_ar as $sign => $ar)
			{
				$uri_inputs = "";
				$ref_field = "{$sign}";

				for ($i = 0; $i < count($ar); $i++)
				{
					$idx = $i + 1;

					$uri_inputs .= $this->getForm()->get_dynamic_row($idx, $sign, $ar[$i]);

					if ($idx > $last_idx_ar[$sign])
					{
						$last_idx_ar[$sign] = $idx;
					}
				}

				$uri_inputs .= "<div id=\"{$ref_field}_anchor_div\"></div>";
				$uri_inputs .= "<div id=\"js_{$ref_field}_resource\" style=\"display:none;\">" .
					$this->getForm()->get_dynamic_row("%NEWID%", $ref_field, "") .
					"</div>";

				$js .= "diref_" . $this->getForm()->table . ".init('$ref_field', 'URL', 1, {$last_idx_ar[$sign]});\n";

				$this->getForm()->setInput("uris_{$sign}",
					"<a href=\"#\" onclick=\"return diref_" . $this->getForm()->table . ".add('$ref_field');\">[+] Добавить URL</a>" . $uri_inputs
				);
			}
		}

		$this->getTpl()->assign([
			"BEFORE_FORM" => "<script type=\"text/javascript\">$js</script>",
		]);
	}

	public function submitForm()
	{
		$this->getSubmit()
			->store_pics("pic", "dias_save_file");
	}

	protected function afterSubmitForm()
	{
		parent::afterSubmitForm();

		diBanners::storeUris($this->getId());
	}

	public function getFormTabs()
	{
		return [
			"dates" => "Даты",
			"pages" => "Страницы",
		];
	}

	public function getFormFields()
	{
		return [
			"place" => [
				"type" => "string",
				"title" => "Местоположение",
				"default" => "left",
			],

			"title" => [
				"type" => "string",
				"title" => "Название (Контент для текстового баннера)",
				"default" => "",
			],

			"href" => [
				"type" => "string",
				"title" => "Ссылка",
				"default" => "",
			],

			"href_target" => [
				"type" => "string",
				"title" => "Открывать в",
				"default" => "",
			],

			"pic" => [
				"type" => "pic",
				"title" => "Изображение",
				"default" => "",
				"notes" => ["Поддерживаемые форматы - JPEG, GIF, PNG, FLASH", "В случае, если изображение не подгружено - баннер текстовый"],
			],

			"date1" => [
				"type" => "datetime_str",
				"title" => "Дата начала показа",
				"default" => date("Y-m-d H:i:s"),
				"tab" => "dates",
			],

			"date2" => [
				"type" => "datetime_str",
				"title" => "Дата конца показа",
				"default" => date("Y-m-d H:i:s", strtotime("+1 year")),
				"tab" => "dates",
			],

			"visible" => [
				"type" => "checkbox",
				"title" => "Баннер активен",
				"default" => 1,
			],

			"uris_positive" => [
				"type" => "string",
				"title" => "Показывать на страницах",
				"default" => "",
				"flags" => ["virtual"],
				"tab" => "pages",
			],

			"uris_negative" => [
				"type" => "string",
				"title" => "Не показывать на страницах",
				"default" => "",
				"flags" => ["virtual"],
				"tab" => "pages",
			],
		];
	}

	public function getLocalFields()
	{
		return [
			"pic_w" => [
				"type" => "int",
				"title" => "Ширина баннера",
				"default" => 0,
			],

			"pic_h" => [
				"type" => "int",
				"title" => "Высота баннера",
				"default" => 0,
			],

			"pic_t" => [
				"type" => "int",
				"title" => "Тип",
				"default" => "0",
			],

			"order_num" => [
				"type" => "order_num",
				"title" => "Order num",
				"default" => 0,
				"direction" => -1,
			],
		];
	}

	public function getModuleCaption()
	{
		return "Баннеры";
	}
}