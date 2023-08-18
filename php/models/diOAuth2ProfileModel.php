<?php
/**
 * Class diOAuth2ProfileModel
 * Methods list for IDE
 *
 * @method string	getUid
 * @method string	getLogin
 * @method string	getEmail
 * @method string	getSex
 * @method string	getWww
 * @method string	getName
 * @method string	getFirstName
 * @method string	getLastName
 * @method string	getLink
 * @method string	getDob
 * @method string	getAvatar
 *
 * @method bool hasUid
 * @method bool hasLogin
 * @method bool hasEmail
 * @method bool hasSex
 * @method bool hasWww
 * @method bool hasName
 * @method bool hasFirstName
 * @method bool hasLastName
 * @method bool hasLink
 * @method bool hasDob
 * @method bool hasAvatar
 *
 * @method diOAuth2ProfileModel setUid($value)
 * @method diOAuth2ProfileModel setLogin($value)
 * @method diOAuth2ProfileModel setEmail($value)
 * @method diOAuth2ProfileModel setSex($value)
 * @method diOAuth2ProfileModel setWww($value)
 * @method diOAuth2ProfileModel setName($value)
 * @method diOAuth2ProfileModel setFirstName($value)
 * @method diOAuth2ProfileModel setLastName($value)
 * @method diOAuth2ProfileModel setLink($value)
 * @method diOAuth2ProfileModel setDob($value)
 * @method diOAuth2ProfileModel setAvatar($value)
 */
class diOAuth2ProfileModel extends diModel
{
    public function getVendorId()
    {
        return $this->table;
    }

    public function setVendorId($vendorId)
    {
        $this->table = $vendorId;

        return $this;
    }

    public function getVendorName()
    {
        return diOAuth2Vendors::name($this->table);
    }

    /**
     * @param diModel $m
     * @return $this
     */
    public function import(diModel $m)
    {
        $method = camelize('import_from_' . $this->getVendorName());

        if (method_exists($this, $method)) {
            $this->$method($m);
        } else {
            throw new \Exception("No method $method found");
        }

        return $this;
    }

    public static function convertGender($gender)
    {
        switch ($gender) {
            case '0':
            case '2':
            case 'male':
                return 'm';
                break;

            case '1':
            case 'female':
                return 'f';
                break;

            case null:
            case false:
            case '':
            default:
                return '';
                break;
        }
    }

    public function importFromFacebook(diModel $m)
    {
        $this->setUid($m['id'])
            ->setFirstName($m['first_name'])
            ->setLastName($m['last_name'])
            ->setName($m['name'])
            ->setLink($m['link'])
            ->setSex(self::convertGender($m['gender']))
            //->setAvatar(isset($profileData["picture"]["data"]["url"]) ? $profileData["picture"]["data"]["url"] : null)
            ->setAvatar(
                "http://graph.facebook.com/{$m['id']}/picture?type=large"
            )
            ->setEmail($m['email']);

        return $this;
    }

    public function importFromOk(diModel $m)
    {
        $this->setUid($m['uid'])
            ->setFirstName($m['first_name'])
            ->setLastName($m['last_name'])
            ->setName($m['name'])
            ->setDob($m['birthday'])
            ->setSex(self::convertGender($m['gender']))
            ->setAvatar($m['pic_3'] ?: $m['pic_2'] ?: $m['pic_1']);

        return $this;
    }

    public function importFromVk(diModel $m)
    {
        $this->setUid($m['id'])
            ->setLogin($m['screen_name'] ?: $m['nick_name'])
            ->setFirstName($m['first_name'])
            ->setLastName($m['last_name'])
            ->setName($m['first_name'] . ' ' . $m['last_name'])
            ->setDob(\diDateTime::format('Y-m-d', $m['bdate']))
            ->setSex(self::convertGender($m['gender']))
            ->setAvatar($m['photo_big']);

        return $this;
    }

    public function importFromGoogle(diModel $m)
    {
        $this->setUid($m['id'])
            ->setEmail($m['email'])
            ->setLogin(preg_replace("/\@.+$/", '', $m['email']))
            ->setFirstName($m['given_name'])
            ->setLastName($m['family_name'])
            ->setName($m['given_name'] . ' ' . $m['family_name'])
            ->setLink($m['link'])
            ->setSex(self::convertGender($m['gender']))
            ->setAvatar($m['picture']);

        return $this;
    }

    public function importFromYandex(diModel $m)
    {
        $this->setUid($m['id'])
            ->setEmail($m['default_email'])
            ->setLogin(preg_replace("/\@.+$/", '', $m['default_email']))
            ->setFirstName($m['first_name'])
            ->setLastName($m['last_name'])
            ->setName($m['first_name'] . ' ' . $m['last_name'])
            ->setDob($m['birthday'])
            ->setSex(self::convertGender($m['sex']))
            ->setAvatar(
                'https://avatars.mds.yandex.net/get-yapic/' .
                    $m['default_avatar_id'] .
                    '/islands-200'
            );

        return $this;
    }

    public function importFromMailru(diModel $m)
    {
        $this->setUid($m['uid'])
            ->setEmail($m['email'])
            ->setLogin(preg_replace("/\@.+$/", '', $m['email']))
            ->setLink($m['link'])
            ->setFirstName($m['first_name'])
            ->setLastName($m['last_name'])
            ->setName($m['nick'] ?: $m['first_name'] . ' ' . $m['last_name'])
            ->setDob(diDateTime::format('Y-m-d', $m['dirthday']))
            ->setSex(self::convertGender($m['sex']))
            ->setAvatar($m['pic_180'] ?: $m['pic_128'] ?: $m['pic']);

        return $this;
    }

    public function importFromInstagram(diModel $m)
    {
        $this->setUid($m['id'])
            ->setLogin($m['username'])
            ->setName($m['full_name'])
            ->setWww($m['website'])
            ->setAvatar($m['profile_picture']);

        return $this;
    }

    public function importFromTwitter(diModel $m)
    {
        $this->setUid($m['id'])
            ->setLogin($m['screen_name'])
            ->setName($m['name'])
            ->setWww(
                isset($m['entities']['url']['urls'][0]['expanded_url'])
                    ? $m['entities']['url']['urls'][0]['expanded_url']
                    : $m['url']
            )
            ->setAvatar(
                $m['profile_image_url'] ?: $m['profile_image_url_https']
            );

        return $this;
    }
}
