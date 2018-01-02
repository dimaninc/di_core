<?php
class diConfigurationPage extends \diCore\Admin\BasePage
{
	protected $vocabulary = [
		'ru' => [
			'form.submit.title' => 'Сохранить',
			'form.cancel.title' => 'Закрыть',
		],
		'en' => [
			'form.submit.title' => 'Save',
			'form.cancel.title' => 'Cancel',
		],
	];

	/** @var \diConfiguration */
	protected $cfg;

	public function __construct($X)
	{
		global $cfg;

		parent::__construct($X);

		$this->cfg = $cfg;
    }

	protected function initTable()
	{
		$this->setTable("configuration");
	}

	public function renderList()
	{
	}

	public function printList()
	{
		$this->getTpl()
			->define("`configuration", [
				"page",
				"saved_message",
			])
			->assign([
				'SUBMIT_TITLE' => $this->getVocabularyTerm('form.submit.title'),
				'CANCEL_TITLE' => $this->getVocabularyTerm('form.cancel.title'),
			]);

		$this->printConfigurationTable();

		$saved = \diRequest::get("saved", 0);

		if ($saved)
		{
			$this->getTpl()->parse("saved_message");
		}
		else
		{
			$this->getTpl()->assign([
				"SAVED_MESSAGE" => "",
			]);
		}
	}

	public function renderForm()
	{
		throw new \Exception("No form in " . get_class($this));
	}

	public function printConfigurationTable()
	{
		$this->cfg
			->setAdminPage($this)
			->checkOtherTabInList(true);

		$this->getTpl()->define("`configuration", [
			"head_tab_row",

			"tab_page",
			"property_row",

			"note_row",
			"notes_block",
		]);

		$tabPagesAr = [];

		foreach (diConfiguration::getData() as $k => $v)
		{
			if (!isset($v["title"]) || diConfiguration::hasFlag($k, "hidden"))
			{
				continue;
			}

			$titleSuffix = "";
			$valueSuffix = "";

			switch ($v["type"])
			{
				case "checkbox":
					$checked = $v["value"] ? " checked=\"checked\"" : "";
					$value = "<input type=\"checkbox\" id='$k' name=\"$k\" value=\"1\" {$checked}>";
					break;

				case "select":
					$prefix_ar = isset($v["select_prefix_ar"]) ? $v["select_prefix_ar"] : [];
					$suffix_ar = isset($v["select_suffix_ar"]) ? $v["select_suffix_ar"] : [];
					$template_text = isset($v["select_template_text"]) ? $v["select_template_text"] : "%title%";
					$template_value = isset($v["select_template_value"]) ? $v["select_template_value"] : "%id%";

					$value = diSelect::fastCreate($k, $v["value"], $v["select_values"], $prefix_ar, $suffix_ar, $template_text, $template_value);
					break;

				case "text":
					$value = "<textarea name=\"$k\" id='$k'>{$v["value"]}</textarea>";
					break;

				case "pic":
				case "file":
					$ff = diPaths::fileSystem() . $this->cfg->getFolder() . $v["value"];
					$ff_orig = "/" . $this->cfg->getFolder() . $v["value"];
					$path = "/" . $this->cfg->getFolder();
					$ext = strtoupper(get_file_ext($ff));

					$info = "$ext";

					if (is_file($ff))
					{
						list($ff_w, $ff_h, $ff_t) = getimagesize($ff);
						$ff_s = str_filesize(filesize($ff));
						$info .= $ff_w || $ff_h ? " {$ff_w}x{$ff_h}, $ff_s" : " $ff_s";
					}
					else
					{
						$ff_w = $ff_h = $ff_t = 0;
					}

					if ($v["type"] == "pic")
					{
						$img_tag = $ff_t == 4 || $ff_t == 13
							? "<script type=\"text/javascript\">run_movie(\"{$path}{$v["value"]}\", \"$ff_w\", \"$ff_h\", \"opaque\");</script>"
							: "<img src='$path{$v["value"]}' border='0'>";

						//$ff_w2 = $ff_w > 500 ? 500 : $ff_w;
						$img_tag = "<div class='uploaded-pic'>$img_tag</div>";
						// style='width: {$ff_w2}px; overflow-x: auto;'
					}
					// video
					elseif (in_array($ext, ["MP4", "M4V", "OGV", "WEBM", "AVI"]))
					{
						$mime_type = \diCore\Admin\Form::get_mime_type_by_ext($ext);
																										// type=\"$mime_type\"
						$img_tag = "<div><video preload=\"none\" controls width=400 height=225><source src=\"$ff_orig\" /></video></div>";
					}
					else
					{
						$img_tag = "";
					}

					$valueSuffix = $v["value"]
						? "<div>$img_tag</div><div class=\"file-info\">$info <a href=\"" . diLib::getAdminWorkerPath("configuration", "del_pic", $k) . "\">удалить</a></div>"
						: "";

					$value = "<input type=\"file\" name=\"$k\" id='$k' size=\"40\" />";
					break;

				default:
					$value = isset($v["flags"]) && in_array("static", $v["flags"])
						? diStringHelper::out($v["value"], true)
						: "<input type=\"text\" name=\"$k\" id='$k' value=\"" . diStringHelper::out($v["value"], true) . "\" />";
					break;
			}

			$tab = isset($v["tab"]) ? $v["tab"] : $this->cfg->getOtherTabName();
			if (!isset($tabPagesAr[$tab]))
			{
				$tabPagesAr[$tab] = "";
			}

			if (!empty($v["notes"]))
			{
				if (!is_array($v["notes"]))
				{
					$v["notes"] = [$v["notes"]];
				}

				$this->getTpl()->clear_parse("NOTE_ROWS");

				foreach ($v["notes"] as $_note)
				{
					$this->getTpl()->assign([
						"NOTE" => $_note,
					]);

					$this->getTpl()->parse("P_NOTE_ROWS", ".note_row");
				}

				$this->getTpl()->parse("P_NOTES_BLOCK", "notes_block");
			}
			else
			{
				$this->getTpl()
					->clear_parse("P_NOTES_BLOCK")
					->assign([
						"P_NOTES_BLOCK" => "",
					]);
			}

			$this->getTpl()->assign([
				"TITLE" => $v["title"] . $titleSuffix,
				"VALUE" => $value . $valueSuffix,
				"FIELD" => $k,
			], "P_");

			$tabPagesAr[$tab] .= $this->getTpl()->parse("property_row");
		}

		$this->getTpl()->assign([
			"TABS_LIST" => join(",", array_keys($this->cfg->getTabsAr())),
			"FIRST_TAB" => current(array_keys($this->cfg->getTabsAr())),
			"WORKER_URI" => diLib::getAdminWorkerPath("configuration", "store"),
		]);

		foreach ($this->cfg->getTabsAr() as $k => $v)
		{
			if (empty($tabPagesAr[$k]))
			{
				continue;
			}

			$this->getTpl()
				->assign([
					"NAME" => $k,
					"TITLE" => $v,
					"PROPERTY_ROWS" => $tabPagesAr[$k],
				], "T_")
				->process("HEAD_TAB_ROWS", ".head_tab_row")
				->process("TAB_PAGES", ".tab_page");
		}
	}

	public function getModuleCaption()
	{
		return "Настройки";
	}

	public function addButtonNeededInCaption()
	{
		return false;
	}
}