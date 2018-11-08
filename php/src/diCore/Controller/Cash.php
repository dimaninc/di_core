<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08/11/2018
 * Time: 18:30
 */

namespace diCore\Controller;

use diCore\Data\Types;
use diCore\Entity\PaymentReceipt\Collection;
use diCore\Entity\PaymentReceipt\Model;

class Cash extends \diBaseController
{
    const secret = null;
    const maxReceiptsAtOnce = null;

    public static function secret()
    {
        return static::secret;
    }

    public static function getSecret()
    {
        return \diRequest::post('secret');
    }

    protected function checkSecret()
    {
        if (static::secret() !== static::getSecret())
        {
            throw new \Exception('Credentials not match');
        }

        return $this;
    }

    public function _postGetNewReceiptsAction()
    {
        return $this
            ->checkSecret()
            ->getReceipts();
    }

    public function _postSetReceiptUploadedAction()
    {
        $receiptId = \diRequest::rawPost('id', 0);

        return $this
            ->checkSecret()
            ->setReceiptUploaded($receiptId);
    }

    protected function getReceipts()
    {
        /** @var Collection $receipts */
        $receipts = \diCollection::create(Types::payment_receipt);
        $receipts
            ->filterByDatePayed(\diDateTime::sqlFormat('-1 month'), '>=')
            ->filterByDateUploaded(null);

        if (static::maxReceiptsAtOnce)
        {
            $receipts
                ->setPageSize(static::maxReceiptsAtOnce);
        }

        $ar = $receipts->map(function(Model $r) {
            return $r->asArrayForCashDesk();
        });

        return $ar;
    }

    protected function setReceiptUploaded($receiptId)
    {
        /** @var Model $receipt */
        $receipt = \diModel::create(Types::payment_receipt, $receiptId);

        if (!$receipt->exists())
        {
            throw new \Exception('Receipt ID=' . $receipt . ' not found');
        }

        $receipt
            ->setDateUploaded(\diDateTime::sqlFormat())
            ->save();

        return [
            'ok' => true,
            'id' => $receiptId,
        ];
    }
}