<?php
/**
 * Created by diModelsManager
 * Date: 14.12.2015
 * Time: 18:31
 */

namespace diCore\Entity\PaymentReceipt;
use diCore\Data\Types;
use diCore\Helper\ArrayHelper;
use diCore\Tool\CollectionCache;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getRnd
 * @method string	getOuterNumber
 * @method string	getDatePayed
 * @method string	getDateUploaded
 * @method integer	getDraftId
 *
 * @method bool hasRnd
 * @method bool hasOuterNumber
 * @method bool hasDatePayed
 * @method bool hasDateUploaded
 * @method bool hasDraftId
 *
 * @method $this setRnd($value)
 * @method $this setOuterNumber($value)
 * @method $this setDatePayed($value)
 * @method $this setDateUploaded($value)
 * @method $this setDraftId($value)
 */
class Model extends \diCore\Entity\PaymentDraft\Model
{
	const type = \diTypes::payment_receipt;
    const table = 'payment_receipts';
	protected $table = 'payment_receipts';

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
            $this->user = CollectionCache::getModel(Types::user, $this->getUserId(), true);
        }

        return $this->user;
    }

    public function getUserData()
    {
        $userData = ArrayHelper::filterByKey($this->getUser()->get(), [
            'name',
            'email',
            'phone',
        ]);

        return $userData;
    }

	public function asArrayForCashDesk()
    {
        $userData = $this->getUserData();
        $target = $this->getTargetModel();

        return [
            'id' => $this->getId(),
            'user_id' => $this->getUserId(),
            'user' => $userData,
            'positions' => [
                [
                    'name' => \diTypes::getTitle($target->modelType()),
                    'amount' => 1,
                    'price' => $this->getAmount(),
                ],
            ],
        ];
    }

    public function getAppearanceFeedForAdmin()
    {
        return [
            $this->exists()
                ? 'Произведена ' . $this['date_payed_date'] . ' в ' . $this['date_payed_time']
                : '&mdash;',
        ];
    }

    public function appearanceForAdmin($options = [])
    {
        $options = extend([
            'showLink' => false,
        ], $options);

        $str = $this->getStringAppearanceForAdmin();

        if ($options['showLink']) {
            $str .= " <a href='{$this->getAdminHref()}'>#{$this->getId()}</a>";
        }

        return $str;
    }
}