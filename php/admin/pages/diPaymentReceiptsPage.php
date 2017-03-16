<?php
/**
 * Created by diAdminPagesManager
 * Date: 28.01.2016
 * Time: 19:09
 */

class diPaymentReceiptsPage extends diPaymentDraftsPage
{
	protected function initTable()
	{
		$this->setTable("payment_receipts");
	}

	protected function setupFilters()
	{
		parent::setupFilters();

		$this->getFilters()
			->addFilter([
				'field' => 'draft_id',
				'type' => 'int',
				'title' => 'ID черновика',
			])
			->buildQuery();
	}

	public function renderList()
	{
		parent::renderList();

		$this->getList()
			->setColumnAttr('date_reserved', 'value', function(diPaymentReceiptModel $m) {
				return diDateTime::format("d.m.Y H:i", $m->getDatePayed());
			})
			->insertColumnsAfter("date_reserved", [
				"outer_number" => [
					"bodyAttrs" => [
						"class" => "lite",
					],
				],
				"draft_id" => [
				],
			])
			->setColumnAttr('vendor', 'headAttrs', ['width' => '10%']);
	}

	public function getFormFields()
	{
		return extend(parent::getFormFields(), [
			"rnd" => [
				"type" => "string",
				"title" => "Случайный код",
				"default" => "",
				"flags" => "hidden",
			],

			"outer_number" => [
				"type" => "int",
				"title" => "Внешний номер платежа",
				"default" => "",
				"flags" => "static",
			],

			"date_payed" => [
				"type" => "datetime_str",
				"title" => "Дата оплаты",
				"default" => "",
				"flags" => "static",
			],

			"draft_id" => [
				"type" => "int",
				"title" => "ID черновика",
				"default" => "",
				"flags" => "static",
			],
		]);
	}

	public function getModuleCaption()
	{
		return "Платежи";
	}
}