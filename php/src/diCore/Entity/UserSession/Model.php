<?php
/**
 * Created by ModelsManager
 * Date: 11.08.2023
 * Time: 18:09
 */

namespace diCore\Entity\UserSession;

use diCore\Database\FieldType;
use diCore\Helper\StringHelper;
use diCore\Traits\Model\AutoTimestamps;
use diCore\Traits\Model\UserInside;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getToken
 * @method string	getUserAgent
 * @method string	getIp
 * @method string	getSeenAt
 *
 * @method bool hasToken
 * @method bool hasUserAgent
 * @method bool hasIp
 * @method bool hasSeenAt
 *
 * @method $this setToken($value)
 * @method $this setUserAgent($value)
 * @method $this setIp($value)
 * @method $this setSeenAt($value)
 */
class Model extends \diModel
{
    use AutoTimestamps;
    use UserInside;

    const type = \diTypes::user_session;
    const connection_name = 'default';
    const table = 'user_session';
    protected $table = 'user_session';

    const token_length = 32;

    protected static $fieldTypes = [
        'id' => FieldType::int,
        'token' => FieldType::string,
        'user_id' => FieldType::int,
        'user_agent' => FieldType::string,
        'ip' => FieldType::ip_string,
        'created_at' => FieldType::string,
        'updated_at' => FieldType::string,
        'seen_at' => FieldType::string,
    ];

    public static function fastCreate(\diModel $user)
    {
        if (!$user->exists()) {
            throw new \Exception(
                'Unable to create user session for empty user'
            );
        }

        return static::create()
            ->generateToken()
            ->setUserId($user->getId())
            ->setUserAgent(\diRequest::userAgent())
            ->setIp(ip2bin())
            ->updateSeenAt();
    }

    public static function fastRestore($token, $userAgent = null, $ip = null)
    {
        if (!$token) {
            return static::create();
        }

        $sessions = Collection::create()
            ->filterByToken($token)
            ->filterByUserAgent($userAgent ?: \diRequest::userAgent());

        if ($ip) {
            $sessions->filterByIp($ip);
        }

        /** @var self $session */
        $session = $sessions->getFirstItem();

        return $session;
    }

    public function generateToken()
    {
        return $this->setToken(StringHelper::random(static::token_length));
    }

    public function updateSeenAt()
    {
        $this->setSeenAt(\diDateTime::sqlFormat());

        return $this;
    }

    public function prepareForSave()
    {
        $this->generateTimestamps();

        return parent::prepareForSave();
    }
}
