<?php
/**
 * Created by ModelsManager
 * Date: 11.08.2023
 * Time: 18:09
 */

namespace diCore\Entity\UserSession;

use diCore\Database\FieldType;
use diCore\Traits\Model\AutoTimestamps;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getToken
 * @method integer	getUserId
 * @method string	getUserAgent
 * @method string	getIp
 * @method string	getSeenAt
 *
 * @method bool hasToken
 * @method bool hasUserId
 * @method bool hasUserAgent
 * @method bool hasIp
 * @method bool hasSeenAt
 *
 * @method $this setToken($value)
 * @method $this setUserId($value)
 * @method $this setUserAgent($value)
 * @method $this setIp($value)
 * @method $this setSeenAt($value)
 */
class Model extends \diModel
{
    use AutoTimestamps;

    const type = \diTypes::user_session;
    const connection_name = 'default';
    const table = 'user_session';
    protected $table = 'user_session';

    protected static $fieldTypes = [
        'id' => FieldType::int,
        'token' => FieldType::string,
        'user_id' => FieldType::int,
        'user_agent' => FieldType::string,
        'ip' => FieldType::string,
        'created_at' => FieldType::string,
        'updated_at' => FieldType::string,
        'seen_at' => FieldType::string,
    ];

    public function prepareForSave()
    {
        $this->generateTimestamps();

        return parent::prepareForSave();
    }
}
