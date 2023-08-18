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
use diCore\Tool\CollectionCache;

class Cash extends \diBaseController
{
    const secret = null;
    const maxReceiptsAtOnce = null;
    const dates = '-1 month';

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
        if (static::secret() !== static::getSecret()) {
            throw new \Exception('Credentials not match');
        }

        return $this;
    }

    public function _postGetNewReceiptsAction()
    {
        $receiptsCol = $this->checkSecret()->getReceipts();

        return [
            'receipts' => $this->processReceipts($receiptsCol),
        ];
    }

    public function _postSetReceiptUploadedAction()
    {
        $receiptId = \diRequest::rawPost('id', 0);
        $fiscal = \diRequest::rawPost('lastReceipt', []);

        return $this->checkSecret()->setReceiptUploaded($receiptId, $fiscal);
    }

    protected function getMaxReceiptsCount()
    {
        return \diRequest::post('limit', 0) ?: static::maxReceiptsAtOnce;
    }

    protected function processReceipts(Collection $receipts)
    {
        CollectionCache::addManual(
            Types::user,
            'id',
            $receipts->map('user_id')
        );

        $ar = $receipts->map(function (Model $r) {
            return $r->asArrayForCashDesk();
        });

        return $ar;
    }

    /**
     * @return Collection
     * @throws \Exception
     */
    protected function getReceipts()
    {
        $receipts = Collection::create()
            ->filterByDatePayed(\diDateTime::sqlFormat(static::dates), '>=')
            ->filterByDateUploaded(null);

        if ($this->getMaxReceiptsCount()) {
            $receipts->setPageSize($this->getMaxReceiptsCount());
        }

        return $receipts;
    }

    protected static function useFiscalMarkFromCashDesk()
    {
        return true;
    }

    protected function setReceiptUploaded($receiptId, $fiscal = [])
    {
        $receipt = Model::createById($receiptId);

        if (!$receipt->exists()) {
            throw new \Exception('Receipt ID=' . $receipt . ' not found');
        }

        if (!empty($fiscal['docId'])) {
            $receipt->setFiscalDocId($fiscal['docId']);
        }

        if (!empty($fiscal['mark']) && static::useFiscalMarkFromCashDesk()) {
            $receipt->setFiscalMark($fiscal['mark']);
        }

        if (!empty($fiscal['date'])) {
            $receipt->setFiscalDate(\diDateTime::sqlFormat($fiscal['date']));
        }

        if (!empty($fiscal['session'])) {
            $receipt->setFiscalSession($fiscal['session']);
        }

        if (!empty($fiscal['number'])) {
            $receipt->setFiscalNumber($fiscal['number']);
        }

        $receipt->setDateUploaded(\diDateTime::sqlFormat())->save();

        return [
            'ok' => true,
            'id' => $receiptId,
        ];
    }
}
