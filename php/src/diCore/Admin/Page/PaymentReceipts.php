<?php
/**
 * Created by diAdminPagesManager
 * Date: 28.01.2016
 * Time: 19:09
 */

namespace diCore\Admin\Page;

use diCore\Entity\PaymentReceipt\Model;

class PaymentReceipts extends PaymentDrafts
{
	protected function initTable()
	{
		$this->setTable('payment_receipts');
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
			->removeColumn(['paid'])
			->setColumnAttr('date_reserved', 'value', function(Model $m) {
				return
                    '<div style="white-space: nowrap">' . \diDateTime::simpleFormat($m->getDatePayed()) . '</div>' .
                    ($m->hasDateUploaded()
                        ? '<div style="background: green; color: white;white-space: nowrap">' . \diDateTime::simpleFormat($m->getDateUploaded()) . '</div>'
                        : '');
			})
			->insertColumnsAfter('date_reserved', [
				'outer_number' => [
					'bodyAttrs' => [
						'class' => 'lite',
					],
				],
				'draft_id' => [
                    'bodyAttrs' => [
                        'class' => 'lite',
                    ],
                    'title' => 'Draft',
				],
			])
			->setColumnAttr('vendor', 'headAttrs', ['width' => '10%']);
	}

	public function getFormFields()
	{
		$ar = parent::getFormFields();

		unset($ar['paid']);
		unset($ar['pay_manual']);

		return extend($ar, [
			'rnd' => [
				'type' => 'string',
				'title' => 'Случайный код',
				'default' => '',
				'flags' => 'hidden',
			],

			'outer_number' => [
				'type' => 'string',
				'title' => 'Внешний номер платежа',
				'default' => '',
				'flags' => 'static',
			],

			'date_payed' => [
				'type' => 'datetime_str',
				'title' => 'Дата оплаты',
				'default' => '',
				'flags' => 'static',
			],

            'date_uploaded' => [
                'type' => 'datetime_str',
                'title' => 'Дата загрузки в ОФД',
                'default' => '',
                'flags' => 'static',
            ],

			'draft_id' => [
				'type' => 'int',
				'title' => 'ID черновика',
				'default' => '',
				'flags' => 'static',
			],
		]);
	}

	public function getModuleCaption()
	{
		return 'Платежи';
	}
}