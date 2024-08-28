<?php
class diOAuth2Vk extends diOAuth2
{
    const loginUrlBase = 'https://oauth.vk.com/authorize';
    const authUrlBase = 'https://oauth.vk.com/access_token';
    const profileUrlBase = 'https://api.vk.com/method/users.get';

    protected $vendorId = diOAuth2Vendors::vk;

    protected function downloadData()
    {
        parent::downloadData();

        $tokenInfo = json_decode(
            static::makeHttpRequest(static::authUrlBase, $this->getAuthUrlParams()),
            true
        );

        if (count($tokenInfo)) {
            if (isset($tokenInfo['access_token'])) {
                $params = [
                    'v' => 5.131,
                    'access_token' => $tokenInfo['access_token'],
                    'uids' => $tokenInfo['user_id'],
                    'fields' =>
                        'uid,email,first_name,last_name,nickname,screen_name,sex,bdate,city,country,timezone,photo,photo_medium,photo_big,has_mobile,rate,contacts,education,online,counters',
                ];

                $data = json_decode(
                    static::makeHttpRequest(static::profileUrlBase, $params),
                    true
                );

                if ($data) {
                    if (!empty($data['error'])) {
                        $this->setProfileError(
                            'Error retrieving data: ' .
                                $data['error']['error_msg'] .
                                ' (code#' .
                                $data['error']['error_code'] .
                                ')'
                        );
                    } else {
                        $this->setProfileRawData(current($data['response']));
                    }
                } else {
                    $this->setProfileError('Error retrieving data');
                }
            } else {
                $this->setProfileError(
                    $tokenInfo['error'] . ': ' . $tokenInfo['error_description']
                );
            }
        } else {
            $this->setProfileError('Error during first request');
        }

        return $this;
    }

    function simple_retrieve_profile()
    {
        // responsed ok, getting access_token
        if (isset($_GET['code'])) {
            $code = urlencode($_GET['code']);

            $info_ar = json_decode(
                join(
                    file(
                        "https://oauth.vk.com/access_token?client_id={$this->options->app_id}&client_secret={$this->options->secret}" .
                            "&code=$code&redirect_uri={$this->options->callback_uri}"
                    )
                )
            );

            $access_token = $info_ar->access_token;
            //$expires = $info_ar->expires;
            $this->uid = $info_ar->user_id;

            $user_profile = json_decode(
                join(
                    file(
                        "https://api.vk.com/method/users.get?access_token=$access_token&uids={$this->uid}" .
                            '&fields=uid,email,first_name,last_name,nickname,screen_name,sex,bdate(birthdate),city,country,timezone,photo' .
                            ',photo_medium,photo_big,has_mobile,rate,contacts,education,online,counters'
                    )
                )
            )->response[0];

            //var_debug($user_profile, "vk");

            if ($user_profile->sex == 1) {
                $sex = 'f';
            } elseif ($user_profile->sex == 2) {
                $sex = 'm';
            } else {
                $sex = '';
            }

            $this->profile = (object) [
                'login' => $user_profile->screen_name,
                'name' => "{$user_profile->last_name} {$user_profile->first_name}",
                'first_name' => $user_profile->first_name,
                'last_name' => $user_profile->last_name,
                'sex' => $sex,
                'avatar' => $user_profile->photo_medium,
                'email' => '',
                'orig_login' => $user_profile->screen_name,
            ];
        }
        // responsed w/error
        elseif (isset($_GET['error'])) {
            diOAuth2::redirect_back();
            //var_dump($_GET["error"], $_GET["error_desciption"]);
            //die();
        }
        // 1st request
        else {
            die(
                header(
                    "Location: https://oauth.vk.com/authorize?client_id={$this->options->app_id}&scope=" .
                        "&redirect_uri={$this->options->callback_uri}&response_type=code"
                )
            );
        }
    }
}
