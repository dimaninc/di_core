<?php
/**
 * Created by diAdminPagesManager
 * Date: 28.01.2016
 * Time: 19:09
 */

namespace diCore\Admin\Page;

use diCore\Admin\BasePage;
use diCore\Data\Types;
use diCore\Entity\PaymentDraft\Model;
use diCore\Payment\Payment;
use diCore\Tool\CollectionCache;

class PaymentDrafts extends BasePage
{
	protected $options = [
		'filters' => [
			'defaultSorter' => [
				'sortBy' => 'id',
				'dir' => 'DESC',
			],
		],
	];

	protected function cacheDataForList()
	{
		parent::cacheDataForList();

		CollectionCache::addManual(Types::user, 'id', $this->getListCollection()->map('user_id'));

		return $this;
	}

	protected function initTable()
	{
		$this->setTable('payment_drafts');
	}

	protected function setupFilters()
	{
		$paymentClass = \diCore\Payment\Payment::getClass();

		$this->getFilters()
			->addFilter([
				'field' => 'user_id',
				'type' => 'string',
				'title' => 'Пользователь',
				'where_tpl' => function($field, $value) {
					return "(`$field` = '$value' OR `$field` in (SELECT id FROM users WHERE INSTR(email, '$value') > 0))";
				},
			])
			->addFilter([
				'field' => 'pay_system',
				'type' => 'int',
				'title' => 'Провайдер',
			])
			->addFilter([
				'field' => 'date_reserved',
				'type' => 'date_str_range',
				'title' => 'За период',
			])
			->buildQuery()
			->setSelectFromArrayInput('pay_system', $paymentClass::getCurrentSystems(), ['' => 'Все провайдеры']);
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			'id' => 'ID',
			'target_type' => [
				'headAttrs' => [
					'width' => '20%',
				],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
				'value' => function(Model $m) {
					return $m->hasTargetType() ? \diTypes::getTitle($m->getTargetType()) : '&mdash;';
				},
			],
			'target_id' => [
				'headAttrs' => [
					'width' => '10%',
				],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
			],
			'user_id' => [
				'headAttrs' => [
					'width' => '20%',
				],
				'value' => function(Model $m) {
					/** @var \diCore\Entity\User\Model $user */
					$user = CollectionCache::getModel(Types::user, $m->getUserId());

					return $user;
				},
			],
			/*
			'pay_system' => array(
				'headAttrs' => array(
					'width' => '20%',
				),
			),
			*/
			'vendor' => [
				'headAttrs' => [
					'width' => '20%',
				],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
				'value' => function(Model $m) {
					return join(' / ', array_filter([$m->getPaySystemStr(), $m->getVendorStr()]));
				},
			],
			'currency' => [
				'headAttrs' => [
					'width' => '10%',
				],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
				'value' => function(Model $m) {
					return $m->getCurrencyStr();
				},
			],
			'amount' => [
				'headAttrs' => [
					'width' => '10%',
				],
			],
			'date_reserved' => [
				'title' => 'Дата',
				'value' => function(Model $m) {
					return \diDateTime::simpleFormat($m->getDateReserved());
				},
				'headAttrs' => [
					'width' => '10%',
				],
				'bodyAttrs' => [
					'class' => 'dt',
				],
			],
			'paid' => [
				'value' => function(Model $m) {
					return $m->hasPaid() ? '+' : '';
				},
			],
			'#edit' => '',
			'#del' => [
				'active' => function(Model $m) {
					return $this->getAdmin()->isAdminSuper();
				},
			],
		]);
	}

	protected function getTargetTitle(\diModel $target)
	{
		return $target->get('title');
	}

	public function renderForm()
	{
		$this->setAfterFormTemplate('admin/payment_drafts/after_form');

		/** @var Model $draft */
		$draft = $this->getForm()->getModel();
		/** @var \diCore\Entity\User\Model $user */
		$user = \diModel::create(Types::user, $draft->getUserId());

		$target = $draft->hasTargetType() && $draft->hasTargetId()
			? \diModel::create($draft->getTargetType(), $draft->getTargetId())
			: new \diModel();

		$this->getForm()
			->setInput('pay_manual', '<button type="button" data-purpose="pay-manual">Провести</button>')
			->setInput('vendor', $draft->getVendorStr())
			->setInput('status', $draft->getStatusStr())
			->setInput('currency', Payment::currencyTitle($draft->getCurrency()))
			->setInput('pay_system', Payment::systemTitle($draft->getPaySystem()))
			->setInput('user_id', sprintf('%s [<a href="%s">ссылка</a>]', $user, $user->getAdminHref()))
			->setInput('target_id', sprintf('%s [<a href="%s">ссылка</a>]', $this->getTargetTitle($target), $target->getAdminHref()));
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
			'target_type' => [
				'type' => 'int',
				'title' => 'Тип покупки',
				'default' => '',
				'flags' => ['hidden'],
			],

			'target_id' => [
				'type' => 'int',
				'title' => 'Товар',
				'default' => '',
				'flags' => ['static'],
			],

			'user_id' => [
				'type' => 'int',
				'title' => 'Покупатель',
				'default' => '',
				'flags' => ['static'],
			],

			'pay_system' => [
				'type' => 'string',
				'title' => 'Платежный шлюз',
				'default' => '',
				'flags' => ['static'],
			],

			'vendor' => [
				'type' => 'int',
				'title' => 'Способ оплаты',
				'default' => '',
				'flags' => ['static'],
			],

			'currency' => [
				'type' => 'string',
				'title' => 'Валюта',
				'default' => '',
				'flags' => ['static'],
			],

			'amount' => [
				'type' => 'string',
				'title' => 'Стоимость',
				'default' => '',
				'flags' => ['static'],
			],

			'date_reserved' => [
				'type' => 'datetime_str',
				'title' => 'Дата создания',
				'default' => '',
				'flags' => ['static'],
			],

			'status' => [
				'type' => 'int',
				'title' => 'Статус',
				'default' => '',
				'flags' => ['static'],
			],

			'paid' => [
				'type' => 'checkbox',
				'title' => 'Оплачен',
				'default' => '',
				'flags' => ['static'],
			],

			'pay_manual' => [
				'type' => 'string',
				'title' => 'Оплатить вручную',
				'default' => '',
				'flags' => ['static', 'virtual'],
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return 'Неоконченные платежи';
	}

	public function addButtonNeededInCaption()
	{
		return false;
	}
}