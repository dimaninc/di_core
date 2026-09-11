<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 14.12.15
 * Time: 19:11
 */

namespace diCore\Controller;

use diCore\Base\Exception\HttpException;
use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Entity\PaymentReceipt\Collection as Receipts;
use diCore\Entity\PaymentReceipt\Model as Receipt;
use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;
use diCore\Payment\CloudPayments\Helper as CloudPayments;
use diCore\Payment\CryptoCloud\Helper as CryptoCloud;
use diCore\Payment\Mixplat\Helper as Mixplat;
use diCore\Payment\Paypal\Helper as Paypal;
use diCore\Payment\Robokassa\Helper as Robokassa;
use diCore\Payment\Tinkoff\Helper as Tinkoff;
use diCore\Payment\Yandex\Kassa;
use diCore\Payment\System;
use diCore\Payment\Yandex\Vendor as YandexVendor;
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

    /** @var  Draft */
    protected $draft;

    /** @var  Receipt */
    protected $receipt;

    public function __construct($params = [])
    {
        parent::__construct($params);

        $class = \diCore\Payment\Payment::getClass();
        if (!$class::enabled()) {
            $this->returnErrorResponse(
                'E-pay disabled at the moment ' . get_user_ip()
            );
        }
    }

    public function payDraftManualAction()
    {
        $draftId = $this->param(0, 0);

        $res = [
            'ok' => false,
        ];

        if ($draftId) {
            $this->initDraftOnly($draftId);

            if (!$this->getDraft()->exists()) {
                $res['message'] = 'Draft #' . $draftId . ' not found';
            } elseif ($this->getDraft()->hasPaid()) {
                $res['message'] = 'Draft #' . $draftId . ' already paid';
            } else {
                $res = $this->createReceipt('manual-' . StringHelper::random(8));
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

        if ($draftId) {
            /** @var Receipts $col */
            $col = \diCollection::create(\diTypes::payment_receipt);
            $col->filterByDraftId($draftId);

            if ($col->count()) {
                $res['ok'] = true;
            } else {
                /** @var Draft $draft */
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

        $Payment = \diCore\Payment\Payment::basicCreate(
            $targetType,
            $targetId,
            AuthTool::i()->getUserId()
        );

        return $Payment->initiateProcess(
            $this->getAmountForRedirect(),
            $paymentSystemName,
            $paymentVendorName
        );
    }

    public function yandexAction()
    {
        $this->system = System::yandex_kassa;
        $this->subAction = $this->param(0);

        $this->kassa = Kassa::create($this->subAction, [
            'init' => function (Kassa $k) {
                $this->initDraft(
                    \diRequest::post('orderNumber', 0),
                    \diRequest::post('orderSumAmount'),
                    \diRequest::post('customerNumber', 0)
                )->updateDraftDetailsIfNeeded();
            },
            'onAviso' => function (Kassa $k) {
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

        $mixplat = Mixplat::create();

        switch ($this->subAction) {
            case 'check':
                $result = $mixplat->handleCheck();
                break;

            case 'status':
                $result = $mixplat->handleStatus();

                $this->processMixplatRequest($result->getData());

                if ($result->isSignCorrect() && $result->isStatusSuccess()) {
                    $this->log('Creating receipt');

                    $this->createReceipt($result->getData('order_id'), function (
                        Receipt $r
                    ) use ($result) {
                        $r->setRnd($result->getData('operator'));
                    });
                }

                break;

            case 'get':
                if (!$draftId) {
                    $this->returnErrorResponse(
                        'Draft ID should be specified for `get` action'
                    );
                }

                $result = $mixplat->queryGet(null, $draftId);

                $ok = $result->isSuccess();
                $message = $ok
                    ? 'Payment successfully processed'
                    : 'Payment not made';

                return [
                    'ok' => $ok,
                    'message' => $message,
                ];
                break;

            default:
                $this->returnErrorResponse('Unknown action: ' . $this->subAction);
                exit();
        }

        if ($result->isSignCorrect()) {
            $this->log("Mixplat data:\n" . print_r($result->getData(), true));

            $mixplat->sendOk();
        } else {
            $this->returnErrorResponse('Error: incorrect sign');
        }

        return null;
    }

    public function paypalAction()
    {
        $this->system = System::paypal;
        $this->subAction = $this->param(0);

        $this->log('Paypal request: ' . $this->subAction);

        $pp = Paypal::create([
            'onSuccessPayment' => function (Paypal $pp) {
                $this->initDraft($pp->getItemNumber(), $pp->getTransactionAmount())
                    ->updateDraftDetailsIfNeeded()
                    ->createReceipt($pp->getTransactionId());
            },
        ]);

        switch ($this->subAction) {
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

    public function cryptoCloudAction()
    {
        $this->system = System::crypto_cloud;
        $this->subAction = $this->param(0);

        $this->log('CryptoCloud request: ' . $this->subAction);

        $cc = CryptoCloud::basicCreate();
        $cc->initDraft(function ($draftId, $amount, $currency) {
            $this->initDraft($draftId, $amount)->updateDraftDetailsIfNeeded();

            return $this->getDraft();
        });

        switch ($this->subAction) {
            case 'result':
                return $cc->result(function () {
                    $this->createReceipt();
                });

            case 'success':
                return $cc->success(function () {
                    $this->redirectTo($this->getTargetHref(self::STATUS_SUCCESS));
                });

            case 'fail':
                return $cc->fail(function () {
                    $this->redirectTo($this->getTargetHref(self::STATUS_FAIL));
                });

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

        $rk = Robokassa::basicCreate();
        $rk->initDraft(function ($draftId, $amount) {
            $this->initDraft($draftId, $amount)->updateDraftDetailsIfNeeded();

            return $this->getDraft();
        });

        $this->beforeRoboAction();

        switch ($this->subAction) {
            case 'result':
                return $rk->result(function (Robokassa $rk) {
                    $this->createReceipt(1);
                });

            case 'success':
                return $rk->success(function (Robokassa $rk) {
                    $this->redirectTo($this->getTargetHref(self::STATUS_SUCCESS));
                });

            case 'fail':
                return $rk->fail(function (Robokassa $rk) {
                    $this->onGatewayFailure(
                        System::robokassa,
                        array_merge($_GET, $_POST)
                    );
                    $this->redirectTo($this->getTargetHref(self::STATUS_FAIL));
                });

            default:
                return [
                    'ok' => false,
                    'message' => 'Unknown action: ' . $this->subAction,
                ];
        }
    }

    public function tinkoffAction()
    {
        $this->system = System::tinkoff;
        $this->subAction = $this->param(0);

        $t = Tinkoff::create();
        $t->initDraft(function ($draftId, $amount) {
            $this->initDraft($draftId, $amount)->updateDraftDetailsIfNeeded();

            return $this->getDraft();
        });

        Tinkoff::log($this->subAction . "\n" . print_r(\diRequest::rawPost(), true));

        switch ($this->subAction) {
            case 'notification':
                $params = \diRequest::rawPostParsed();

                if ($t->checkToken($params)) {
                    $t->log('Token matches');

                    if (ArrayHelper::get($params, 'Status') === 'CONFIRMED') {
                        $this->createReceipt(
                            \diRequest::rawPost('PaymentId', 0),
                            fn(Receipt $r) => $t->tuneVendor(
                                $r,
                                ArrayHelper::get($params, ['Data', 'Source'])
                            )
                        );
                    } else {
                        $this->onGatewayFailure(System::tinkoff, $params ?: []);
                    }

                    return 'OK';
                } else {
                    $t->log('Token not match');

                    return 'ERROR';
                }

            case 'success':
                return $t->success(function (Tinkoff $rk) {
                    $this->redirectTo($this->getTargetHref(self::STATUS_SUCCESS));
                });

            case 'fail':
                return $t->fail(function (Tinkoff $rk) {
                    $this->redirectTo($this->getTargetHref(self::STATUS_FAIL));
                });

            default:
                return [
                    'ok' => false,
                    'message' => 'Unknown action: ' . $this->subAction,
                ];
        }
    }

    /**
     * Routes:
     *   POST /api/payment/cloud_payments/notification/{pay,fail}
     *   GET  /api/payment/cloud_payments/{success,fail}   (?InvoiceId=…)
     *
     * One action for both because the methods differ: notifications are POSTed
     * by the gateway, the return addresses are browser redirects. A `_post`
     * prefix would route the whole thing, redirects included, so the method is
     * required in the notification branch instead.
     *
     * The notification type comes from the URL — the cabinet holds a separate
     * address per type; `Status` is the fallback for an address set up without
     * the suffix.
     */
    public function cloudPaymentsAction()
    {
        $this->system = System::cloud_payments;
        $this->subAction = $this->param(0);

        $cp = CloudPayments::create();

        switch ($this->subAction) {
            case 'notification':
                return $this->cloudPaymentsNotification($cp);

            case 'success':
                $this->initDraftForRedirect($cp);

                return $cp->success(function (CloudPayments $cp) {
                    $this->redirectTo($this->getTargetHref(self::STATUS_SUCCESS));
                });

            case 'fail':
                $this->initDraftForRedirect($cp);

                return $cp->fail(function (CloudPayments $cp) {
                    // Empty payload on purpose: this redirect is unsigned, so
                    // passing $_GET on would let anyone overwrite the real
                    // reason that arrived by notification.
                    $this->onGatewayFailure(System::cloud_payments, []);
                    $this->redirectTo($this->getTargetHref(self::STATUS_FAIL));
                });

            default:
                return [
                    'ok' => false,
                    'message' => 'Unknown action: ' . $this->subAction,
                ];
        }
    }

    private function cloudPaymentsNotification(CloudPayments $cp)
    {
        // The gateway POSTs notifications; anything else is not one.
        if (!\diRequest::isPost()) {
            throw HttpException::notFound('Notifications are POSTed');
        }

        // Before anything else: the signature covers the RAW bytes.
        $cp->readNotification();

        CloudPayments::log(
            $this->subAction .
                '/' .
                (string) $this->param(1) .
                "\n" .
                CloudPayments::sanitizeForLog($cp->getRawNotification())
        );

        // Checked BEFORE the draft is touched. There is no IP allowlist to
        // fall back on, so this signature is all that separates a real
        // notification from anyone who can guess a draft id.
        if (!$cp->checkSignature()) {
            CloudPayments::log('Signature does not match');

            // Non-zero = the gateway retries for ~45 minutes. That is what we
            // want even when the fault is ours: a wrong secret in env stays
            // recoverable without losing the notification.
            return CloudPayments::retryResponse();
        }

        $params = $cp->getNotificationParams();
        $type = (string) $this->param(1);

        // An unhandled type must not fall through: the paid branch would mark
        // a draft paid, the failure one would answer `code: 0` — which for a
        // `check` notification means "go ahead and charge".
        if (!in_array($type, ['', 'pay', 'fail'], true)) {
            CloudPayments::log('Unhandled notification type: ' . $type);

            return CloudPayments::retryResponse();
        }

        // Test mode is a state of the SITE in the cabinet and the credentials
        // are the same either way, so a test notification is properly signed
        // and would otherwise run the whole live path — down to the cash desk,
        // which fiscalises every receipt regardless of payment system.
        $testMode = CloudPayments::testModeFlag($params);

        // Unrecognised flag: do not guess. Either wrong answer costs money —
        // a fiscal cheque for nothing, or a real payment silently dropped.
        if ($testMode === null) {
            CloudPayments::log(
                'Unrecognised TestMode value: ' .
                    CloudPayments::sanitizeForLog(
                        var_export(ArrayHelper::get($params, 'TestMode'), true)
                    )
            );

            return CloudPayments::retryResponse();
        }

        if ($testMode && !$this->acceptsTestPayments()) {
            CloudPayments::log(
                'Test-mode notification refused (draft ' .
                    (string) ArrayHelper::get($params, 'InvoiceId') .
                    ')'
            );

            // `code: 0`, not a retry: nothing failed, we just will not act.
            return CloudPayments::okResponse();
        }

        // initDraftOnly, not initDraft: the latter answers a bad draft with
        // die($message), and a webhook must always reply in the protocol.
        $cp->initDraftFromNotification(function ($draftId, $amount) {
            $this->initDraftOnly($draftId);

            return $this->getDraft();
        });

        if (!$this->getDraft()->exists()) {
            CloudPayments::log(
                'No such payment draft: ' .
                    (string) ArrayHelper::get($params, 'InvoiceId')
            );

            // Retrying cannot conjure a draft that does not exist.
            return CloudPayments::okResponse();
        }

        // A signature proves WHO sent this, not WHICH draft it may act on.
        // Without the check a foreign `InvoiceId` would mark another gateway's
        // draft paid — and getting there needs no attacker: one account shared
        // by two sites, or overlapping id sequences, suffice.
        if ((int) $this->getDraft()->getPaySystem() !== System::cloud_payments) {
            CloudPayments::log(
                'Draft #' .
                    (string) $this->getDraft()->getId() .
                    ' belongs to another payment system'
            );

            return CloudPayments::okResponse();
        }

        // A hold is a third outcome: calling it paid delivers goods for money
        // that unblocks in a week, calling it failed buries a live attempt.
        if (CloudPayments::isHoldStatus(ArrayHelper::get($params, 'Status'))) {
            CloudPayments::log(
                'Payment is held, not captured (draft #' .
                    (string) $this->getDraft()->getId() .
                    '); nothing to do until it is confirmed'
            );

            return CloudPayments::okResponse();
        }

        // The address says failure, the payload says paid. Recording a failure
        // here would drop a REAL payment silently, so nothing is decided:
        // retry plus a signal. The reverse case needs no such care — `Declined`
        // on the paid address is a genuine failure with nothing to lose.
        if (
            $type === 'fail' &&
            CloudPayments::isPaidStatus(ArrayHelper::get($params, 'Status'))
        ) {
            CloudPayments::log(
                'Paid status on the failure address (draft #' .
                    (string) $this->getDraft()->getId() .
                    '): the cabinet most likely has one URL in both fields'
            );

            $this->onCloudPaymentsMisroutedNotification($params);

            return CloudPayments::retryResponse();
        }

        if ($this->isCloudPaymentsSuccessNotification($params, $type)) {
            $this->checkCloudPaymentsAmount($params);

            $result = $this->createReceipt(
                ArrayHelper::get($params, 'TransactionId') ?: 0
            );

            // createReceipt() swallows a failed save(), so answering 0 would
            // drop the notification for good. A retry cannot double-charge —
            // the receipt is reused by draft_id — but it does NOT finish an
            // interrupted job either: postProcess() shares that try, so if IT
            // threw, the retry finds the receipt and skips it, leaving the card
            // unmarked. Fixing that means changing a method five gateways
            // share; the reason meanwhile is in the payment log.
            return empty($result['ok'])
                ? CloudPayments::retryResponse()
                : CloudPayments::okResponse();
        }

        $this->onGatewayFailure(System::cloud_payments, $params ?: []);

        return CloudPayments::okResponse();
    }

    /**
     * Logs, and only logs, a mismatch between the reported and the asked sum.
     *
     * Not an anti-forgery check — the signature settled that — but a trap for
     * OUR mistake: an invoice issued for the wrong sum, an `InvoiceId` wired to
     * the wrong draft. A log line rather than a refusal, because the money is
     * already gone and refusing would lose the purchase. Override to raise it
     * to monitoring.
     */
    protected function checkCloudPaymentsAmount(array $params)
    {
        $currency = ArrayHelper::get($params, 'Currency');

        // Like with like. Every invoice is in roubles today; a currency one
        // will need the draft's own currency here instead of this literal.
        if ($currency !== null && strtoupper((string) $currency) !== 'RUB') {
            return $this;
        }

        $reported = (float) ArrayHelper::get($params, 'Amount');
        $expected = (float) $this->getDraft()->getAmount();

        // Below a kopeck is float noise, not a discrepancy.
        if ($reported <= 0 || abs($reported - $expected) < 0.01) {
            return $this;
        }

        CloudPayments::log(
            'Amount mismatch on draft #' .
                (string) $this->getDraft()->getId() .
                ': asked ' .
                (string) $expected .
                ', notified ' .
                (string) $reported
        );

        return $this;
    }

    /**
     * A notification arrived at an address contradicting its own status: the
     * cabinet is misconfigured and a real payment is at risk. Already logged by
     * the caller; override to raise it to monitoring. Must never throw.
     */
    protected function onCloudPaymentsMisroutedNotification(array $params)
    {
        return $this;
    }

    /**
     * Whether a notification flagged as a test may run the live path.
     *
     * `false` by default — see the call site for why that is the safe answer.
     * A project overrides it to allow test payments outside production.
     */
    protected function acceptsTestPayments()
    {
        return false;
    }

    /**
     * A payment when the URL says so AND the payload does not contradict it.
     *
     * The address alone is not enough — both URLs are typed into the cabinet by
     * hand, and one of them pasted into both fields would turn every `Declined`
     * into a paid receipt. The status is consulted, never required: a payload
     * without one goes by the address it arrived at.
     *
     * Without a type suffix the status is all there is. Never decide by ABSENCE
     * of a failure marker: an unrecognised payload belongs in the failure
     * branch, which only records diagnostics.
     */
    protected function isCloudPaymentsSuccessNotification(
        array $params,
        $type = null
    ) {
        $type = $type === null ? (string) $this->param(1) : (string) $type;
        $status = ArrayHelper::get($params, 'Status');

        if ($type === 'fail') {
            return false;
        }

        if ($type === 'pay') {
            return $status === null || CloudPayments::isPaidStatus($status);
        }

        return CloudPayments::isPaidStatus($status);
    }

    /**
     * The browser redirects carry only the draft id: they are addresses we
     * built ourselves, they are not signed, and they must not be trusted to
     * report an amount. Resolution only — no amount check, no save.
     */
    private function initDraftForRedirect(CloudPayments $cp)
    {
        $cp->initDraftFromRedirect(function ($draftId, $amount) {
            $this->initDraftOnly($draftId);

            return $this->getDraft();
        });

        if (!$this->getDraft()->exists()) {
            // Without this the redirect would go on to ask an empty model for
            // its target's href.
            $this->returnErrorResponse('No such payment draft');
        }

        return $this;
    }

    protected function getTargetHref($status)
    {
        return $this->getDraft()->getTargetModel()->getHref();
    }

    protected function beforeRoboAction()
    {
        return $this;
    }

    /**
     * Neutral hook fired when a gateway reports a NON-successful outcome for the
     * current draft (a non-CONFIRMED T-Bank notification, a Robokassa fail
     * redirect, …). No-op by default — the happy path is untouched, so this
     * cannot affect payment processing. Projects override it to record the
     * failure reason for diagnostics. Implementations MUST stay best-effort
     * (never throw into the callback), the draft is available via getDraft().
     *
     * @param int $system System id of the reporting gateway
     * @param array $payload raw gateway payload (for forensics / reason mapping)
     */
    protected function onGatewayFailure($system, array $payload)
    {
        return $this;
    }

    protected function createReceipt($outerNumber, callable|null $beforeSave = null)
    {
        $this->log('createReceipt begins');

        /** @var Receipts $receipts */
        $receipts = \diCollection::create(\diTypes::payment_receipt);
        $receipts->filterByDraftId($this->getDraft()->getId());

        $this->receipt = $receipts->getFirstItem();
        $existingReceipt = true;

        if (!$this->getReceipt()->exists()) {
            $this->receipt = \diModel::create(
                \diTypes::payment_receipt,
                $this->getDraft()->getDataForNewReceipt()
            );
            $existingReceipt = false;
        }

        if (!$this->getReceipt()->hasVendor()) {
            switch ($this->getReceipt()->getPaySystem()) {
                case System::yandex_kassa:
                    $vendor = (int) YandexVendor::id(
                        \diRequest::post('paymentType')
                    );
                    break;

                default:
                    $vendor = null;
                    break;
            }

            if ($vendor) {
                $this->getReceipt()->setVendor($vendor);
            }
        }

        if (!$existingReceipt) {
            $this->getReceipt()
                ->killOrig()
                // todo: remove killing: id, paid and properties are killed in getDataForNewReceipt
                ->killId()
                ->kill(['paid', 'properties'])
                ->setDraftId($this->getDraft()->getId())
                ->setOuterNumber($outerNumber);
        }

        if ($beforeSave) {
            $beforeSave($this->getReceipt());
        }

        try {
            $this->getReceipt()->save();

            if (!$existingReceipt) {
                $this->log('Receipt created, ID = ' . $this->getReceipt()->getId());
                $this->log('Receipt #' . $this->getReceipt()->getId() . ' created');
                $this->log('Draft #' . $this->getDraft()->getId() . ' set as paid');
            } else {
                $this->log('Receipt updated, ID = ' . $this->getReceipt()->getId());
                $this->log(
                    'Draft #' .
                        $this->getDraft()->getId() .
                        ' set as paid (not first time)'
                );
            }

            $this->log('Receipt: ' . print_r($this->getReceipt()->get(), true));

            $this->getDraft()->setPaid(1)->save();

            if (!$existingReceipt) {
                $class = \diCore\Payment\Payment::getClass();
                $class::postProcess($this->getReceipt());
            }
        } catch (\Exception $e) {
            $this->log('Error while creating receipt: ' . $e->getMessage());

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }

        return [
            'ok' => true,
        ];
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
     * @return Draft
     */
    protected function getDraft()
    {
        return $this->draft;
    }

    /**
     * @return Receipt
     */
    protected function getReceipt()
    {
        return $this->receipt;
    }

    protected function initDraftOnly($draftId)
    {
        $this->draft = Draft::createById($draftId);

        return $this;
    }

    protected function initDraft($draftId, $amount, $userId = null)
    {
        $this->initDraftOnly($draftId);

        $this->log('Draft used: ' . print_r($this->draft->get(), true));

        if (!$this->getDraft()->exists()) {
            $this->returnErrorResponse('No such payment draft');
        }

        if ($userId && $userId != $this->getDraft()->getUserId()) {
            $this->returnErrorResponse('Customer number ' . $userId . ' is wrong');
        }

        /* mixplat's beeline adds 10 rub so this doesn't work
		if ($amount != $this->getDraft()->getAmount())
		{
			$this->sendErrorResponse('Amount doesn't match draft');
		}
		*/

        if ($amount < 1) {
            $this->returnErrorResponse('Too small amount');
        }

        return $this;
    }

    protected function updateDraftDetailsIfNeeded($data = [])
    {
        $this->log(
            'Updating draft details, id: ' .
                $this->getDraft()->getId() .
                ' data: ' .
                ($data ? print_r($data, true) : '-')
        );

        switch ($this->getDraft()->getPaySystem()) {
            case System::yandex_kassa:
                if (
                    !$this->getDraft()->hasVendor() &&
                    ($vendor = YandexVendor::id(\diRequest::post('paymentType')))
                ) {
                    $this->getDraft()->setVendor($vendor);
                }
                break;

            case System::robokassa:
                // todo: robokassa
                /*
				if (
					!$this->getDraft()->hasVendor() &&
					$vendor = \diCore\Payment\Robokassa\Vendor::id(\diRequest::post('paymentType'))
				) {
					$this->getDraft()->setVendor($vendor);
				}
				*/
                break;

            case System::mixplat:
                if (!empty($data['status'])) {
                    $this->getDraft()->setStatus($data['status']);
                }
                break;

            case System::sms_online:
                /*
				if (!empty($data['status'])) {
					$this->getDraft()
						->setStatus($data['status']);
				}
				*/
                break;

            case System::tinkoff:
                //$this->getDraft()
                //    ->setOuterNumber(\diRequest::request('PaymentId'));
                break;
        }

        $this->getDraft()->save();

        return $this;
    }

    protected function processMixplatRequest($data = [])
    {
        $this->log('Start ' . $this->subAction);

        $this->initDraft(
            $data['merchant_order_id'],
            $data['amount']
        )->updateDraftDetailsIfNeeded($data);

        return $this;
    }

    private function returnErrorResponse($message)
    {
        if ($this->system == System::yandex_kassa && $this->kassa) {
            $this->kassa->sendErrorResponse($message, true);
        } else {
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
