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
 */
class Model extends \diCore\Entity\PaymentDraft\Model
{
    const type = \diTypes::payment_receipt;
    const table = 'payment_receipts';
    protected $table = 'payment_receipts';

    const TIMESTAMP_FORMAT = 'Ymd\THi';
    const FISCAL_STORAGE_NUMBER = null;

    protected $customDateFields = [
        'date_reserved',
        'date_payed',
        'date_uploaded',
        'fiscal_date',
    ];

    /** @var \diCore\Entity\User\Model */
    protected $user;

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
                ? 'Произведена ' .
                    $this['date_payed_date'] .
                    ' в ' .
                    $this['date_payed_time']
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
