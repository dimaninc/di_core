<?php

namespace diCore\Admin\Page;

use diCore\Controller\Db as dbController;
use diCore\Helper\FileSystemHelper;

class Db extends \diCore\Admin\BasePage
{
	protected $excludedTables = [
		"banner_stat2",
		"mail_queue",
		"search_results",
	];

	public function renderList()
	{
		$this->getTpl()
			->define("`db", [
				"page",
				"dump_file_row",
				"dump_folder_row",
				"dump_subfolder_row",
			]);

		$this->printTablesSelect();
		$this->printDumpFiles();

		$this->getTpl()
			->assign([
				"URI" => \diLib::getAdminWorkerPath("db"),
				"URI_UPLOAD" => \diLib::getAdminWorkerPath("db", "upload"),
			], "WORKER_");
	}

	public function renderForm()
	{
		throw new \Exception("No form in " . get_class($this));
	}

	private function printTablesSelect()
	{
		$tablesAr = dbController::getTablesList($this->getDb());

		$tablesSel = new \diSelect("tables");
		$tablesSel->setCurrentValue(function($table) use ($tablesSel) {
			return !in_array($table, $this->excludedTables) && substr($table, 0, 13) != "search_index_"
				&& !preg_match('/\[[^\]]+\]$/', $tablesSel->getTextByValue($table));
		});

		$tablesSel
			->setAttr("multiple")
			->setAttr("size", 10)
			->addItemArray($tablesAr["tablesForSelectAr"]);

		$this->getTpl()
			->assign([
				"TABLES_SELECT" => $tablesSel,
				"TOTAL_SIZE" => size_in_bytes($tablesAr["totalSize"]),
				"TOTAL_IDX_SIZE" => size_in_bytes($tablesAr["totalIndexSize"]),
			]);
	}

	private function printDumpFiles()
	{
		/** @var dbController $controllerClass */
		$controllerClass = \diLib::getChildClass(dbController::class);

		foreach ($controllerClass::$foldersIdsAr as $folderId)
		{
			$folder = $controllerClass::getFolderById($folderId);
			$filesAr = $this->getDumpFilesFromFolder($folder, $folderId);

			$this->getTpl()
				->clear_parse("DUMP_FILE_ROWS")
				->assign("DUMP_FILE_ROWS", "");

			foreach ($filesAr as $a)
			{
				if ($a["type"] == "folder")
				{
					$this->getTpl()
						->assign($a["templateAr"], "SF_")
						->parse("DUMP_FILE_ROWS", ".dump_subfolder_row");
				}
				elseif ($a["type"] == "file")
				{
					$this->getTpl()
						->assign($a["templateAr"], "D_")
						->parse("DUMP_FILE_ROWS", ".dump_file_row");
				}
			}

			$this->getTpl()
				->assign([
					"ID" => $folderId,
					"NAME" => $folder,
				], "F_")
				->parse("DUMP_FOLDER_ROWS", ".dump_folder_row");
		}
	}

	private function getDumpFilesFromFolder($folder, $folderId = null)
	{
		$ar = [];

		$dir = FileSystemHelper::folderContents($folder, true, true);
		$filesAr = $dir["f"];

		$filesAr = array_map(function($v) use($folder) {
			return substr($v, 0, strlen($folder)) == $folder ? substr($v, strlen($folder)) : $v;
		}, $filesAr);

		usort($filesAr, function($a, $b) {
			$aDir = dirname($a);
			$bDir = dirname($b);

			if ($aDir > $bDir) return 1;
			elseif ($aDir < $bDir) return -1;
			else
			{
				if ($a > $b) return 1;
				elseif ($a < $b) return -1;
			}

			return 0;
		});

		$currentFolder = "";

		foreach ($filesAr as $f)
		{
			unset($regs);
			unset($regs2);

			// we're inside folder
			if (basename($f) != $f && $currentFolder != dirname($f))
			{
				$currentFolder = dirname($f);

				$ar[] = [
					"type" => "folder",
					"name" => $currentFolder,
					"templateAr" => [
						"NAME" => add_ending_slash($currentFolder),
					],
				];
			}

			preg_match("/^(.*)__dump_(.{4})_(.{2})_(.{2})__(.{2})_(.{2})_(.{2})\.sql(\.gz)?$/i", basename($f), $regs);
			preg_match("/^(.*)\.sql(\.gz)?$/i", basename($f), $regs2);

			if ($regs || $regs2)
			{
				if ($regs)
				{
					$standard = true;

					for ($i = 2; $i < count($regs) - 1; $i++)
					{
						if (lead0(intval($regs[$i])) != $regs[$i])
						{
							$standard = false;

							break;
						}
					}
				}
				else
				{
					$standard = false;
				}

				if ($standard)
				{
					$name = $regs[1];
					$dy = $regs[2];
					$dm = $regs[3];
					$dd = $regs[4];
					$th = $regs[5];
					$tm = $regs[6];
					$ts = $regs[7];
					$compressed = isset($regs[8]) && strtolower($regs[8]) == ".gz";
				}
				else
				{
					$name = $regs2[1];
					list($dy, $dm, $dd, $th, $tm, $ts) = explode(",", date("Y,m,d,H,i,s", filemtime($folder.$f)));
					$compressed = isset($regs[2]) && strtolower($regs[2]) == ".gz";
				}

				$ext = $compressed ? "gz" : "sql";

				$ar[] = [
					"type" => "file",
					"fullFilename" => $folder . $f,
					"datetime" => strtotime("$dd.$dm.$dy $th:$tm:$ts"),
					"templateAr" => [
						"NAME" => $name,
						"DATE" => "$dy.$dm.$dd",
						"TIME" => "$th:$tm:$ts",
						"SIZE" => size_in_bytes(filesize($folder . $f)),
						"EXT" => $ext,
						"FULL_FILENAME" => $folder . $f,
						"FILENAME" => $f,
					],
				];
			}
		}

		/*
		usort($ar, function($a, $b) use($folderId) {
			if ($folderId == diDbController::FOLDER_CORE_SQL)
			{
				return $a["templateAr"]["NAME"] > $b["templateAr"]["NAME"];
			}

			return $a["datetime"] < $b["datetime"];
		});
		*/

		return $ar;
	}

	public function getModuleCaption()
	{
		return [
			'ru' => 'Резервное копирование базы данных',
			'en' => 'Database dump/restore',
		];
	}

	public function addButtonNeededInCaption()
	{
		return false;
	}
}