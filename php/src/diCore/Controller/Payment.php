<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 14.12.15
 * Time: 19:11
 */

namespace diCore\Controller;

use diCore\Entity\PaymentDraft\Model as DraftModel;
use diCore\Entity\PaymentReceipt\Model as ReceiptModel;
use diCore\Helper\StringHelper;
use diCore\Payment\Yandex\Kassa;
use diCore\Payment\System;
use diCore\Tool\Auth as AuthTool;

class Payment extends \diBaseController
{
	const STATUS_SUCCESS = 1;
	const STATUS_FAIL = 2;

	/** @var  integer */
	protected $system;

	/** @var  Kassa */
	protected $kassa;

	/** @var  string */
	protected $subAction;

	/** @var  DraftModel */
	protected $draft;

	/** @var  ReceiptModel */
	protected $receipt;

	public function __construct($params = [])
	{
		parent::__construct($params);

		$class = \diCore\Payment\Payment::getClass();
		if (!$class::enabled())
		{
			$this->returnErrorResponse('E-pay disabled at the moment ' . get_user_ip());
		}
	}

	public function payDraftManualAction()
	{
		$draftId = $this->param(0, 0);

		$res = [
			'ok' => false,
			'message' => null,
		];

		if ($draftId)
		{
			$this->initDraftOnly($draftId);

			if (!$this->getDraft()->exists())
			{
				$res['message'] = 'Draft #' . $draftId . ' not found';
			}
			elseif ($this->getDraft()->hasPaid())
			{
				$res['message'] = 'Draft #' . $draftId . ' already paid';
			}
			else
			{
				$this->createReceipt('manual-' . StringHelper::random(8));
				$res['ok'] = true;
			}
		}

		return $res;
	}

	public function isDraftPaidAction()
	{
		$draftId = $this->param(0, 0);

		$res = [
			'ok' => false,
			'status' => null,
			'status_str' => '',
		];

		if ($draftId)
		{
			/** @var \diCore\Entity\PaymentReceipt\Collection $col */
			$col = \diCollection::create(\diTypes::payment_receipt);
			$col
				->filterByDraftId($draftId);

			if ($col->count())
			{
				$res['ok'] = true;
			}
			else
			{
				/** @var DraftModel $draft */
				$draft = \diModel::create(\diTypes::payment_draft, $draftId);

				$res['status'] = $draft->getStatus();
				$res['status_str'] = $draft->getStatusStr();
			}
		}

		return $res;
	}

	protected function getTargetTypeForRedirect()
	{
		return $this->param(2, 0);
	}

	protected function getTargetIdForRedirect()
	{
		return $this->param(3, 0);
	}

	protected function getAmountForRedirect()
	{
		return \diRequest::get('amount', 0.0);
	}

	public function redirectAction()
	{
		$paymentSystemName = $this->param(0);
		$paymentVendorName = $this->param(1);
		$targetType = $this->getTargetTypeForRedirect();
		$targetId = $this->getTargetIdForRedirect();

		$paymentSystemId = System::id($paymentSystemName);
		$paymentVendorId = System::vendorIdByName($paymentSystemId, $paymentVendorName);

		$Payment = \diCore\Payment\Payment::basicCreate($targetType, $targetId, AuthTool::i()->getUserId());

		/** @var \diCore\Entity\PaymentDraft\Model $draft */
		$draft = $Payment->getNewDraft(
			$this->getAmountForRedirect(),
			$paymentSystemId,
			$paymentVendorId
		);

		switch ($draft->getPaySystem())
		{
			case System::mixplat:
				return $Payment->initMixplat($draft);

			case System::sms_online:
				return $Payment->initSmsOnline($draft);

			case System::paypal:
				return $Payment->initPaypal($draft);

			case System::yandex_kassa:
				return $Payment->initYandex($draft);

			case System::robokassa:
				return $Payment->initRobokassa($draft);

			case System::tinkoff:
				return $Payment->initTinkoff($draft);

			default:
				throw new \Exception('Unsupported payment system #' . $draft->getPaySystem());
		}
	}

	public function yandexAction()
	{
		$this->system = System::yandex_kassa;
		$this->subAction = $this->param(0);

		$this->kassa = Kassa::create($this->subAction, [
			'init' => function(Kassa $k) {
				$this
					->initDraft(\diRequest::post('orderNumber', 0), \diRequest::post('orderSumAmount'),
						\diRequest::post('customerNumber', 0))
					->updateDraftDetailsIfNeeded();
			},
			'onAviso' => function(Kassa $k) {
				$this->createReceipt(\diRequest::post('invoiceId', 0));
			},
		]);

		return $this->kassa->process();
	}

	public function mixplatAction()
	{
		$this->system = System::mixplat;
		$this->subAction = $this->param(0);
		$draftId = $this->param(1, 0);

		$this->log('Mixplat request: ' . $this->subAction);
		$this->log("GET:\n" . print_r($_GET, true));
		$this->log("POST:\n" . print_r($_POST, true));
		$this->log("POST BODY:\n" . print_r(file_get_contents('php://input'), true));

		$mixplat = \diCore\Payment\Mixplat\Helper::create();

		switch ($this->subAction)
		{
			case 'check':
				$result = $mixplat->handleCheck();
				break;

			case 'status':
				$result = $mixplat->handleStatus();

				$this->processMixplatRequest($result->getData());

				if ($result->isSignCorrect() && $result->isStatusSuccess())
				{
					$this->log('Creating receipt');

					$this->createReceipt($result->getData('order_id'), function(ReceiptModel $r) use($result) {
						$r->setRnd($result->getData('operator'));
					});
				}

				break;

			case 'get':
				if (!$draftId)
				{
					$this->returnErrorResponse('Draft ID should be specified for `get` action');
				}

				$result = $mixplat->queryGet(null, $draftId);

				$ok = $result->isSuccess();
				$message = $ok ? 'Payment successfully processed' : 'Payment not made';

				return [
					'ok' => $ok,
					'message' => $message,
				];
				break;

			default:
				$this->returnErrorResponse('Unknown action: ' . $this->subAction);
				exit;
		}

		if ($result->isSignCorrect())
		{
			$this->log("Mixplat data:\n" . print_r($result->getData(), true));

			$mixplat->sendOk();
		}
		else
		{
			$this->returnErrorResponse('Error: incorrect sign');
		}

		return null;
	}

	public function paypalAction()
	{
		$this->system = System::paypal;
		$this->subAction = $this->param(0);

		$this->log('Paypal request: ' . $this->subAction);

		$pp = \diCore\Payment\Paypal\Helper::create([
			'onSuccessPayment' => function(\diCore\Payment\Paypal\Helper $pp) {
				$this
					->initDraft($pp->getItemNumber(), $pp->getTransactionAmount())
					->updateDraftDetailsIfNeeded()
					->createReceipt($pp->getTransactionId());
			},
		]);

		switch ($this->subAction)
		{
			case 'notification':
				$pp->notification();
				return null;

			default:
				return [
					'ok' => false,
					'message' => 'Unknown action: ' . $this->subAction,
				];
		}
	}

	public function roboAction()
	{
		$this->system = System::robokassa;
		$this->subAction = $this->param(0);

		$this->log('Robokassa request: ' . $this->subAction);
		$this->log('POST: ' . print_r($_POST, true));

		$rk = \diCore\Payment\Robokassa\Helper::basicCreate();
		$rk->initDraft(function($draftId, $amount) {
			$this
				->initDraft($draftId, $amount)
				->updateDraftDetailsIfNeeded();

			return $this->getDraft();
		});

		$this->beforeRoboAction();

		switch ($this->subAction)
		{
			case 'result':
				return $rk->result(function(\diCore\Payment\Robokassa\Helper $rk) {
					$this->createReceipt(1);
				});

			case 'success':
				return $rk->success(function(\diCore\Payment\Robokassa\Helper $rk) {
					$this->redirectTo($this->getTargetHref(self::STATUS_SUCCESS));
				});

			case 'fail':
				return $rk->fail(function(\diCore\Payment\Robokassa\Helper $rk) {
					$this->redirectTo($this->getTargetHref(self::STATUS_FAIL));
				});

			default:
				return [
					'ok' => false,
					'message' => 'Unknown action: ' . $this->subAction,
				];
		}
	}

	protected function getTargetHref($status)
	{
		return $this->getDraft()->getTargetModel()->getHref();
	}

	protected function beforeRoboAction()
	{
		return $this;
	}

	protected function createReceipt($outerNumber, callable $beforeSave = null)
	{
		$this->log('createReceipt begins');

		/** @var \diCore\Entity\PaymentReceipt\Collection $receipts */
		$receipts = \diCollection::create(\diTypes::payment_receipt);
		$receipts
			->filterByDraftId($this->getDraft()->getId());

		$this->receipt = $receipts->getFirstItem();
		$existingReceipt = true;

		if (!$this->getReceipt()->exists())
		{
			$this->receipt = \diModel::create(\diTypes::payment_receipt, $this->getDraft());
			$existingReceipt = false;
		}

		if (!$this->getReceipt()->hasVendor())
		{
			switch ($this->getReceipt()->getPaySystem())
			{
				case System::yandex_kassa:
					$vendor = (int)\diCore\Payment\Yandex\Vendor::id(\diRequest::post('paymentType'));
					break;

				default:
					$vendor = null;
					break;
			}

			if ($vendor)
			{
				$this->getReceipt()
					->setVendor($vendor);
			}
		}

		if (!$existingReceipt)
		{
			$this->getReceipt()
				->killOrig()
				->killId()
				->kill('paid')
				->setDraftId($this->getDraft()->getId())
				->setOuterNumber($outerNumber);
		}

		if ($beforeSave)
		{
			$beforeSave($this->getReceipt());
		}

		try {
			$this->getReceipt()
				->save();

			if (!$existingReceipt)
			{
				$this->log('Receipt created, ID = ' . $this->getReceipt()->getId());
				$this->log('Receipt #' . $this->getReceipt()->getId() . ' created');

				$this->log('Draft #' . $this->getDraft()->getId() . ' set as paid');
			}
			else
			{
				$this->log('Receipt updated, ID = ' . $this->getReceipt()->getId());
				$this->log('Draft #' . $this->getDraft()->getId() . ' set as paid (not first time)');
			}

			$this->log('Receipt: ' . print_r($this->getReceipt()->get(), true));

			$this->getDraft()
				->setPaid(1)
				->save();
				//->hardDestroy();

			if (!$existingReceipt)
			{
				$class = \diCore\Payment\Payment::getClass();
				$class::postProcess($this->getReceipt());
			}
		} catch (\Exception $e) {
			$this->log('Error while creating receipt: ' . $e->getMessage());
		}

		return $this;
	}

	/*
	public function testAction()
	{
		$this->receipt = diModel::create(diTypes::payment_receipt, 9);

		$class = \diCore\Payment\Payment::getClass();
		$class::postProcess($this->getReceipt());

		return ['zhopa' => 1488];
	}
	*/

	/**
	 * @return DraftModel
	 */
	protected function getDraft()
	{
		return $this->draft;
	}

	/**
	 * @return ReceiptModel
	 */
	protected function getReceipt()
	{
		return $this->receipt;
	}

	protected function initDraftOnly($draftId)
	{
		$this->draft = \diModel::create(\diCore\Data\Types::payment_draft, $draftId);

		return $this;
	}

	protected function initDraft($draftId, $amount, $userId = null)
	{
		$this->initDraftOnly($draftId);

		$this->log('Draft used: ' . print_r($this->draft->get(), true));

		if (!$this->getDraft()->exists())
		{
			$this->returnErrorResponse('No such payment draft');
		}

		if ($userId && $userId != $this->getDraft()->getUserId())
		{
			$this->returnErrorResponse('Customer number ' . $userId . ' is wrong');
		}

		/* mixplat's beeline adds 10 rub so this doesn't work
		if ($amount != $this->getDraft()->getAmount())
		{
			$this->sendErrorResponse('Amount doesn't match draft');
		}
		*/

		if ($amount < 1)
		{
			$this->returnErrorResponse('Too small amount');
		}

		return $this;
	}

	protected function updateDraftDetailsIfNeeded($data = [])
	{
		$this->log('Updating draft details, id: ' . $this->getDraft()->getId() . ' data: ' . ($data ? print_r($data, true) : '-'));

		switch ($this->getDraft()->getPaySystem())
		{
			case System::yandex_kassa:
				if (
					!$this->getDraft()->hasVendor() &&
					$vendor = \diCore\Payment\Yandex\Vendor::id(\diRequest::post('paymentType'))
				)
				{
					$this->getDraft()->setVendor($vendor);
				}
				break;

			case System::robokassa:
				// todo: robokassa
				/*
				if (
					!$this->getDraft()->hasVendor() &&
					$vendor = \diCore\Payment\Robokassa\Vendor::id(\diRequest::post('paymentType'))
				)
				{
					$this->getDraft()->setVendor($vendor);
				}
				*/
				break;

			case System::mixplat:
				if (!empty($data['status']))
				{
					$this->getDraft()
						->setStatus($data['status']);
				}
				break;

			case System::sms_online:
				/*
				if (!empty($data['status']))
				{
					$this->getDraft()
						->setStatus($data['status']);
				}
				*/
				break;
		}

		$this->getDraft()
			->save();

		return $this;
	}

	protected function processMixplatRequest($data = [])
	{
		$this->log('Start ' . $this->subAction);

		$this
			->initDraft($data['merchant_order_id'], $data['amount'])
			->updateDraftDetailsIfNeeded($data);

		return $this;
	}

	private function returnErrorResponse($message)
	{
		if ($this->system == System::yandex_kassa && $this->kassa)
		{
			$this->kassa->sendErrorResponse($message, true);
		}
		else
		{
			$this->log('Sending error response: ' . $message);

			die($message);
		}
	}

	protected function log($message)
	{
		$class = \diCore\Payment\Payment::getClass();
		$class::log($message);

		return $this;
	}
}