<?php
/**
 * Created by diAdminPagesManager
 * Date: 28.01.2016
 * Time: 19:09
 */

use diCore\Tool\CollectionCache;
use \diCore\Entity\PaymentDraft\Model;

class diPaymentDraftsPage extends diAdminBasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "id",
				"dir" => "DESC",
			],
		],
	];

	protected function cacheDataForList()
	{
		parent::cacheDataForList();

		CollectionCache::addManual(\diTypes::user, 'id', $this->getListCollection()->map('user_id'));

		return $this;
	}

	protected function initTable()
	{
		$this->setTable("payment_drafts");
	}

	protected function setupFilters()
	{
		/** @var diPayment $paymentClass */
		$paymentClass = class_exists('diCustomPayment') ? 'diCustomPayment' : 'diPayment';

		$this->getFilters()
			->addFilter([
				"field" => "user_id",
				"type" => "string",
				"title" => "Пользователь",
				"where_tpl" => function($field, $value) {
					return "(`$field` = '$value' OR `$field` in (SELECT id FROM users WHERE INSTR(email, '$value') > 0))";
				},
			])
			->addFilter([
				"field" => 'pay_system',
				"type" => "int",
				"title" => "Провайдер",
			])
			->addFilter([
				"field" => "date_reserved",
				"type" => "date_str_range",
				"title" => "За период",
			])
			->buildQuery()
			->setSelectFromArrayInput('pay_system', $paymentClass::getCurrentSystems(), ['' => 'Все провайдеры']);
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			"id" => "ID",
			"target_type" => [
				"headAttrs" => [
					"width" => "20%",
				],
				"value" => function(Model $m) {
					return diTypes::getTitle($m->getTargetType());
				},
			],
			"target_id" => [
				"headAttrs" => [
					"width" => "10%",
				],
			],
			"user_id" => [
				"headAttrs" => [
					"width" => "20%",
				],
				"value" => function(Model $m) {
					/** @var diUserModel $user */
					$user = CollectionCache::getModel(\diTypes::user, $m->getUserId());

					return $user;
				},
			],
			/*
			"pay_system" => array(
				"headAttrs" => array(
					"width" => "20%",
				),
			),
			*/
			"vendor" => [
				"headAttrs" => [
					"width" => "20%",
				],
				"value" => function(Model $m) {
					return join(' / ', array_filter([$m->getPaySystemStr(), $m->getVendorStr()]));
				},
			],
			"currency" => [
				"headAttrs" => [
					"width" => "10%",
				],
				"value" => function(Model $m) {
					return $m->getCurrencyStr();
				},
			],
			"amount" => [
				"headAttrs" => [
					"width" => "10%",
				],
			],
			"date_reserved" => [
				"title" => "Дата",
				"value" => function(Model $m) {
					return diDateTime::format("d.m.Y H:i", $m->getDateReserved());
				},
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "dt",
				],
			],
			"#edit" => "",
			"#del" => [
				'active' => function(Model $model) {
					return $this->getAdmin()->isAdminSuper();
				},
			],
		]);
	}

	protected function getTargetTitle(diModel $target)
	{
		return $target->get("title");
	}

	public function renderForm()
	{
		/** @var Model $draft */
		$draft = $this->getForm()->getModel();
		/** @var diUserModel $user */
		$user = diModel::create(diTypes::user, $draft->getUserId());

		$target = diModel::create($draft->getTargetType(), $draft->getTargetId());

		$this->getForm()
			->setInput("vendor", $draft->getVendorStr())
			->setInput('status', $draft->getStatusStr())
			->setInput("currency", diPayment::currencyTitle($draft->getCurrency()))
			->setInput("pay_system", diPayment::systemTitle($draft->getPaySystem()))
			->setInput("user_id", sprintf("%s [<a href='%s'>ссылка</a>]", $user, $user->getAdminHref()))
			->setInput("target_id", sprintf("%s [<a href='%s'>ссылка</a>]", $this->getTargetTitle($target), $target->getAdminHref()));
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
				"title" => "Тип покупки",
				"default" => "",
				"flags" => ["hidden"],
			],

			"target_id" => [
				"type" => "int",
				"title" => "Товар",
				"default" => "",
				"flags" => ["static"],
			],

			"user_id" => [
				"type" => "int",
				"title" => "Покупатель",
				"default" => "",
				"flags" => ["static"],
			],

			"pay_system" => [
				"type" => "string",
				"title" => "Платежный шлюз",
				"default" => "",
				"flags" => ["static"],
			],

			"vendor" => [
				"type" => "int",
				"title" => "Способ оплаты",
				"default" => "",
				"flags" => ["static"],
			],

			"currency" => [
				"type" => "string",
				"title" => "Валюта",
				"default" => "",
				"flags" => ["static"],
			],

			"amount" => [
				"type" => "string",
				"title" => "Стоимость",
				"default" => "",
				"flags" => ["static"],
			],

			"date_reserved" => [
				"type" => "datetime_str",
				"title" => "Дата создания",
				"default" => "",
				"flags" => ["static"],
			],

			"status" => [
				"type" => "int",
				"title" => "Статус",
				"default" => "",
				"flags" => ["static"],
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return "Неоконченные платежи";
	}

	public function addButtonNeededInCaption()
	{
		return false;
	}
}