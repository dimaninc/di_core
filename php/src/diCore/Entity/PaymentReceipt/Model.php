<?php
/**
 * Created by diModelsManager
 * Date: 14.12.2015
 * Time: 18:31
 */

namespace diCore\Entity\PaymentReceipt;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use diCore\Data\Types;
use diCore\Helper\ArrayHelper;
use diCore\Helper\Phone;
use diCore\Tool\CollectionCache;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getRnd
 * @method string	getDatePayed
 * @method string	getDateUploaded
 * @method integer	getDraftId
 * @method string	getFiscalMark
 * @method string	getFiscalDocId
 * @method string	getFiscalDate
 * @method integer	getFiscalSession
 * @method integer	getFiscalNumber
 * @method string	getFiscalMarkKkt
 * @method string	getFiscalFnNumber
 * @method string	getFiscalKktRegNumber
 * @method integer	getFiscalTotal
 *
 * @method bool hasRnd
 * @method bool hasDatePayed
 * @method bool hasDateUploaded
 * @method bool hasDraftId
 * @method bool hasFiscalMark
 * @method bool hasFiscalDocId
 * @method bool hasFiscalDate
 * @method bool hasFiscalSession
 * @method bool hasFiscalNumber
 * @method bool hasFiscalMarkKkt
 * @method bool hasFiscalFnNumber
 * @method bool hasFiscalKktRegNumber
 * @method bool hasFiscalTotal
 *
 * @method $this setRnd($value)
 * @method $this setDatePayed($value)
 * @method $this setDateUploaded($value)
 * @method $this setDraftId($value)
 * @method $this setFiscalMark($value)
 * @method $this setFiscalDocId($value)
 * @method $this setFiscalDate($value)
 * @method $this setFiscalSession($value)
 * @method $this setFiscalNumber($value)
 * @method $this setFiscalMarkKkt($value)
 * @method $this setFiscalFnNumber($value)
 * @method $this setFiscalKktRegNumber($value)
 * @method $this setFiscalTotal($value)
 */
class Model extends \diCore\Entity\PaymentDraft\Model
{
    const type = \diTypes::payment_receipt;
    const table = 'payment_receipts';
    protected $table = 'payment_receipts';

    const TIMESTAMP_FORMAT = 'Ymd\THi';
    const FISCAL_STORAGE_NUMBER = null;
    // tag 1008 (buyer's email or phone) of FFD holds 64 characters
    const CASH_DESK_CONTACT_MAX_LENGTH = 64;

    protected $customDateFields = [
        'date_reserved',
        'date_payed',
        'date_uploaded',
        'fiscal_date',
    ];

    /** @var \diCore\Entity\User\Model */
    protected $user;

    /** @var \diCore\Entity\PaymentDraft\Model */
    protected $draft;

    public function validate()
    {
        if (!$this->hasOuterNumber()) {
            $this->addValidationError('Outer number required', 'outer_number');
        }

        if (!$this->hasDraftId()) {
            $this->addValidationError('Draft ID required', 'draft_id');
        }

        return parent::validate();
    }

    public function getUser()
    {
        if (!$this->user) {
            $this->user = CollectionCache::getModel(
                Types::user,
                $this->getUserId(),
                true
            );
        }

        return $this->user;
    }

    public function getUserData()
    {
        $userData = ArrayHelper::filterByKey($this->getUser()->get() ?? [], [
            'name',
            'email',
            'phone',
        ]);

        return $userData;
    }

    public function getDraft()
    {
        if (!$this->draft) {
            $this->draft = CollectionCache::getModel(
                Types::payment_draft,
                $this->getDraftId(),
                true
            );
        }

        return $this->draft;
    }

    public function getMainPositionTitle()
    {
        return \diTypes::getTitle($this->getTargetModel()->modelType());
    }

    public function asArrayForCashDesk()
    {
        return [
            'id' => $this->getId(),
            'user_id' => $this->getUserId(),
            'user' => $this->getUserData(),
            'positions' => [
                [
                    'name' => $this->getMainPositionTitle(),
                    'amount' => 1,
                    'price' => $this->getAmount(),
                ],
            ],
        ];
    }

    /**
     * Receipt for the cash desk, contract v2: money in integer kopecks, contact
     * already chosen. Everything a project may change goes through the
     * protected cashDesk*() methods below.
     */
    public function asArrayForCashDeskV2(string $paymentsPlace): array
    {
        $total = $this->cashDeskTotal();

        return [
            'id' => (int) $this->getId(),
            'type' => $this->cashDeskReceiptType(),
            'originalId' => $this->cashDeskOriginalId(),
            'paidAt' => $this->cashDeskPaidAt(),
            'total' => $total,
            'contact' => $this->cashDeskContact(),
            'paymentsPlace' => $paymentsPlace,
            'items' => $this->cashDeskItems($total),
            'payments' => $this->cashDeskPayments($total),
        ];
    }

    /**
     * Models asArrayForCashDeskV2() reads through CollectionCache, as
     * `[type, id]` pairs: the controller loads them per type for the whole batch.
     * A project reading more (a draft) adds it here.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public function cashDeskRelatedIds(): array
    {
        return [
            [Types::user, (int) $this->getUserId()],
            // the item name is the target's type title (getMainPositionTitle())
            [(int) $this->getTunedTargetType(), (int) $this->getTargetId()],
        ];
    }

    /**
     * `amount` is a float in roubles (a FLOAT column in MySQL): 0.29 * 100 is
     * 28.999…, so a cast without round() would lose a kopeck.
     */
    public static function toKopecks($amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public function cashDeskTotal(): int
    {
        return static::toKopecks($this->getAmount());
    }

    /**
     * `sell` or `sellReturn`; a return also needs cashDeskOriginalId().
     */
    protected function cashDeskReceiptType(): string
    {
        return 'sell';
    }

    protected function cashDeskOriginalId(): ?int
    {
        return null;
    }

    protected function cashDeskPaidAt(): ?string
    {
        $date = (string) $this->getDatePayed();

        if ($date === '') {
            return null;
        }

        try {
            return (new \DateTime($date))->format(DATE_ATOM);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * A usable email that fits tag 1008, else a phone, else null (the cash desk then puts in the
     * shop's own address, as the law requires a contact for an online receipt).
     */
    public function cashDeskContact(): ?string
    {
        $email = trim($this->cashDeskBuyerEmail());

        if (
            $email !== '' &&
            mb_strlen($email) <= static::CASH_DESK_CONTACT_MAX_LENGTH &&
            $this->isCashDeskEmailUsable($email)
        ) {
            return $email;
        }

        $phone = $this->normalizeCashDeskPhone($this->cashDeskBuyerPhone());

        return $phone !== '' ? $phone : null;
    }

    /**
     * The buyer's own address only: not getUserData(), which a project may pad
     * with a fallback, and a fallback here would hide the phone behind it.
     */
    protected function cashDeskBuyerEmail(): string
    {
        return (string) ($this->getUser()->get('email') ?? '');
    }

    protected function cashDeskBuyerPhone(): string
    {
        return (string) ($this->getUser()->get('phone') ?? '');
    }

    protected function isCashDeskEmailUsable(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * E.164 or '' when ambiguous (the receipt then goes to the fallback contact):
     * a Russian number in any common spelling, a foreign one only with an explicit
     * `+`/`00` – a receipt sent to a wrong number is worse than none.
     */
    protected function normalizeCashDeskPhone(string $phone): string
    {
        $e164 = $this->e164CashDeskPhone($phone);

        // 8-800 is the shop's toll-free line, never the buyer's own
        return str_starts_with($e164, '+7800') ? '' : $e164;
    }

    private function e164CashDeskPhone(string $phone): string
    {
        // An explicit country code is taken as written: Phone::clean() would read
        // +81 3… (11 digits on 8) or +961 1… (10 on 9) as Russian numbers
        if (preg_match('/^\s*(\+|00)([\d\s().-]+)\z/', $phone, $m)) {
            $digits = preg_replace('/\D+/', '', $m[2]);

            // no country code starts with 89: +8 9XX… is a Russian 8 with a stray plus
            if (preg_match('/^8(9\d{9})\z/', $digits, $ru)) {
                return '+7' . $ru[1];
            }

            return preg_match('/^[1-9]\d{7,14}\z/', $digits) ? "+$digits" : '';
        }

        if (preg_match('/^\s*(\+|00)/', $phone)) {
            // a prefix plus letters (an extension, a note): not a number we can dial
            return '';
        }

        // 8XXXXXXXXXX and 9XXXXXXXXX become 7XXXXXXXXXX
        $digits = Phone::clean($phone);

        return strlen($digits) === 11 && $digits[0] === '7' ? "+$digits" : '';
    }

    protected function cashDeskItems(int $total): array
    {
        return [
            [
                'name' => $this->cashDeskItemName(),
                'price' => $total,
                'quantity' => 1,
                'sum' => $total,
                'paymentObject' => $this->cashDeskPaymentObject(),
                'paymentMethod' => $this->cashDeskPaymentMethod(),
            ],
        ];
    }

    protected function cashDeskItemName(): string
    {
        return (string) $this->getMainPositionTitle();
    }

    protected function cashDeskPaymentObject(): string
    {
        return 'service';
    }

    protected function cashDeskPaymentMethod(): string
    {
        return 'fullPayment';
    }

    protected function cashDeskPayments(int $total): array
    {
        return [
            [
                'type' => 'electronically',
                'sum' => $total,
            ],
        ];
    }

    public static function getFiscalStorageNumber()
    {
        return static::FISCAL_STORAGE_NUMBER;
    }

    public function isQrAvailable()
    {
        return $this->hasFiscalDate() &&
            $this->hasFiscalDocId() &&
            $this->hasFiscalMark();
    }

    /*
     * @link https://qna.habr.com/q/463983
     * t=20180311T150100&s=53.00&fn=8710000100603283&i=51219&fp=408618133&n=1
     * t=ГГГГММДДTЧЧММСС (буква T между ДД и ЧЧ пишем на английском, если секунд нет просто пишем 00),
     * s=₽₽.Коп,
     * fn=16цифр ФН (номер фискального накопителя),
     * i=ФД (номер фискального документа)
     * fp=ФПД (фискальный признак документа)
     * n=тип системы налогообложения (0-ОСН,1-УСН Доход,2-УСН Доход-Расход, 3-ЕНВД, 4-ЕСН, 5-Патент)
     */
    public function getCashDeskQrCodeData()
    {
        return [
            't' => \diDateTime::format(
                static::TIMESTAMP_FORMAT,
                $this->getFiscalDate()
            ),
            's' => sprintf('%.2f', $this->getAmount()),
            'fn' => static::getFiscalStorageNumber(),
            'i' => $this->getFiscalDocId(),
            'fp' => $this->getFiscalMark(),
            'n' => 1, // УСН Доход
        ];
    }

    public function getCashDeskQrCodeStr()
    {
        return ArrayHelper::toString($this->getCashDeskQrCodeData(), '=', '&');
    }

    public function getCashDeskQrCodeContents(
        $type = QRCode::OUTPUT_MARKUP_SVG,
        $extraOptions = []
    ) {
        $options = new QROptions(
            extend(
                [
                    'version' => 5,
                    'outputType' => $type,
                    'eccLevel' => QRCode::ECC_L,
                ],
                $extraOptions
            )
        );
        $qrCode = new QRCode($options);

        return $qrCode->render($this->getCashDeskQrCodeStr());
    }

    public function getCashDeskQrCodeAsInlineSvg()
    {
        return $this->getCashDeskQrCodeContents();
    }

    public function getCashDeskQrCodeAsInlinePng()
    {
        return $this->getCashDeskQrCodeContents(QRCode::OUTPUT_IMAGE_PNG);
    }

    public function getCashDeskQrCodeAsPng()
    {
        return $this->getCashDeskQrCodeContents(QRCode::OUTPUT_IMAGE_PNG, [
            'imageBase64' => false,
        ]);
    }

    public function getAppearanceFeedForAdmin()
    {
        return [
            $this->exists()
                ? "Произведена {$this['date_payed_date']} в {$this['date_payed_time']}"
                : '&mdash;',
        ];
    }

    public function appearanceForAdmin($options = [])
    {
        $options = extend(
            [
                'showLink' => false,
            ],
            $options
        );

        $str = $this->getStringAppearanceForAdmin();

        if ($options['showLink']) {
            $str .= " <a href='{$this->getAdminHref()}'>#{$this->getId()}</a>";
        }

        return $str;
    }
}
