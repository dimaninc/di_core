<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.09.2016
 * Time: 12:32
 */

namespace diCore\Tool;

use diCore\Data\Http\HttpCode;
use diCore\Entity\Redirect\Collection;
use diCore\Entity\Redirect\Model;

class Redirector
{
    public function __construct()
    {
        $urls = Collection::create()
            ->filterByOldUrl(static::currentUrlWithoutQuery())
            ->filterByActive(1);

        /** @var Model $url */
        foreach ($urls as $url) {
            if (
                $url->getOldUrl() === static::currentUrl() ||
                !$url->hasStrictForQuery()
            ) {
                static::go($url);
                die();
            }
        }
    }

    public static function checkAndRedirect()
    {
        new static();
    }

    public static function currentUrl()
    {
        return \diRequest::requestUri();
    }

    public static function urlQuery()
    {
        return \diRequest::requestQueryString();
    }

    public static function currentUrlWithoutQuery()
    {
        $q = static::urlQuery();

        return $q
            ? substr(static::currentUrl(), 0, -(strlen($q) + 1))
            : static::currentUrl();
    }

    public static function go($url, $status = null)
    {
        if ($url instanceof Model) {
            $urlModel = $url;
            $status = $url->getStatus();
            $url = $url->getNewUrl();

            if (!$urlModel->hasStrictForQuery() && static::urlQuery()) {
                $url .= '?' . static::urlQuery();
            }
        } else {
            $status = $status ?: HttpCode::MOVED_PERMANENTLY;
        }

        if ($status) {
            HttpCode::header($status);
        }

        header("Location: $url");
    }
}
