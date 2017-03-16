<?php

use diCore\Payment\Yandex\Kassa;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 14.12.15
 * Time: 19:11
 */
class diPaymentController extends diBaseController
{
	/** @var  integer */
	protected $vendor;

	/** @var  Kassa */
	protected $kassa;

	/** @var  string */
	protected $subAction;

	/** @var  diPaymentDraftModel */
	protected $draft;

	/** @var  diPaymentReceiptModel */
	protected $receipt;

	public function __construct()
	{
		parent::__construct();

		if (!diConfiguration::get("epay_enabled"))
		{
			$this->returnErrorResponse("E-pay disabled at the moment " . get_user_ip());
		}
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
			/** @var diPaymentReceiptCollection $col */
			$col = diCollection::create(diTypes::payment_receipt);
			$col
				->filterByDraftId($draftId);

			if ($col->count())
			{
				$res['ok'] = true;
			}
			else
			{
				/** @var diPaymentDraftModel $draft */
				$draft = \diModel::create(diTypes::payment_draft, $draftId);

				$res['status'] = $draft->getStatus();
				$res['status_str'] = $draft->getStatusStr();
			}
		}

		return $res;
	}

	public function yandexAction()
	{
		$this->vendor = diPayment::yandex;
		$this->subAction = $this->param(0);

		$this->kassa = Kassa::create($this->subAction, [
			'init' => function(Kassa $k) {
				$this
					->initDraft(\diRequest::post("orderNumber", 0), \diRequest::post('orderSumAmount'),
						\diRequest::post("customerNumber", 0))
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
		$this->vendor = diPayment::mixplat;
		$this->subAction = $this->param(0);
		$draftId = $this->param(1, 0);

		$this->log("Mixplat request: " . $this->subAction);
		$this->log("GET:\n" . print_r($_GET, true));
		$this->log("POST:\n" . print_r($_POST, true));
		$this->log("POST BODY:\n" . print_r(file_get_contents('php://input'), true));

		$mixplat = \Romantic\Settings\Mixplat::create();

		switch ($this->subAction)
		{
			case "check":
				$result = $mixplat->handleCheck();
				break;

			case "status":
				$result = $mixplat->handleStatus();

				$this->processMixplatRequest($result->getData());

				if ($result->isSignCorrect() && $result->isStatusSuccess())
				{
					$this->log('Creating receipt');

					$this->createReceipt($result->getData('order_id'), function(diPaymentReceiptModel $r) use($result) {
						$r->setRnd($result->getData('operator'));
					});
				}

				break;

			case "get":
				if (!$draftId)
				{
					$this->returnErrorResponse("Draft ID should be specified for `get` action");
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
				$this->returnErrorResponse("Unknown action: " . $this->subAction);
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
		$this->vendor = diPayment::paypal;
		$this->subAction = $this->param(0);

		$this->log("Paypal request: " . $this->subAction);

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

	protected function createReceipt($outerNumber, callable $beforeSave = null)
	{
		$this->receipt = diModel::create(diTypes::payment_receipt, $this->getDraft());

		if (
			$vendor = (int)diYandexKassaVendors::id(diRequest::post('paymentType')) &&
			!$this->getReceipt()->hasVendor()
		   )
		{
			$this->getReceipt()
				->setVendor($vendor);
		}

		$this->getReceipt()
			->killOrig()
			->killId()
			->setDraftId($this->getDraft()->getId())
			->setOuterNumber($outerNumber);

		if ($beforeSave)
		{
			$beforeSave($this->getReceipt());
		}

		try {
			$this->getReceipt()
				->save();

			$this->log('Receipt created, ID = ' . $this->getReceipt()->getId());
			$this->log("Receipt #" . $this->getReceipt()->getId() . " created");

			$this->log("Draft #" . $this->getDraft()->getId() . " killed");
			$this->log("Receipt: " . print_r($this->getReceipt()->get(), true));

			$this->getDraft()->hardDestroy();

			diCustomPayment::postProcess($this->getReceipt());
		} catch (Exception $e) {
			$this->log('Error while creating receipt: ' . $e->getMessage());
		}

		return $this;
	}

	/*
	public function testAction()
	{
		$this->receipt = diModel::create(diTypes::payment_receipt, 9);

		diCustomPayment::postProcess($this->getReceipt());

		return ['zhopa' => 1488];
	}
	*/

	/**
	 * @return diPaymentDraftModel
	 */
	protected function getDraft()
	{
		return $this->draft;
	}

	/**
	 * @return diPaymentReceiptModel
	 */
	protected function getReceipt()
	{
		return $this->receipt;
	}

	protected function initDraft($draftId, $amount, $userId = null)
	{
		$this->draft = diModel::create(\diCore\Data\Types::payment_draft, $draftId);

		$this->log("Draft used: " . print_r($this->draft->get(), true));

		if (!$this->getDraft()->exists())
		{
			$this->returnErrorResponse("No such payment draft");
		}

		if ($userId && $userId != $this->getDraft()->getUserId())
		{
			$this->returnErrorResponse("Customer number " . $userId . " is wrong");
		}

		/* mixplat's beeline adds 10 rub so this doesn't work
		if ($amount != $this->getDraft()->getAmount())
		{
			$this->sendErrorResponse("Amount doesn't match draft");
		}
		*/

		if ($amount < 1)
		{
			$this->returnErrorResponse("Too small amount");
		}

		return $this;
	}

	protected function updateDraftDetailsIfNeeded($data = [])
	{
		$this->log('Updating draft details, id: ' . $this->getDraft()->getId() . ' data: ' . ($data ? print_r($data, true) : '-'));

		switch ($this->getDraft()->getPaySystem())
		{
			case diPayment::yandex:
				if (
					!$this->getDraft()->hasVendor() &&
					$vendor = diYandexKassaVendors::id(diRequest::post('paymentType'))
				   )
				{
					$this->getDraft()->setVendor($vendor);
				}
				break;

			case diPayment::mixplat:
				if (!empty($data['status']))
				{
					$this->getDraft()
						->setStatus($data['status']);
				}
				break;

			case diPayment::sms_online:
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
		$this->log("Start " . $this->subAction);

		$this
			->initDraft($data['merchant_order_id'], $data['amount'])
			->updateDraftDetailsIfNeeded($data);

		return $this;
	}

	private function returnErrorResponse($message)
	{
		if ($this->vendor == diPayment::yandex && $this->kassa)
		{
			$this->kassa->sendErrorResponse($message, true);
		}
		else
		{
			$this->log("Sending error response: " . $message);

			die($message);
		}
	}

	protected function log($message)
	{
		diCustomPayment::log($message);

		return $this;
	}
}