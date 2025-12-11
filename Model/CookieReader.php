<?php

namespace Stape\Gtm\Model;

use Magento\Framework\Stdlib\Cookie\CookieReaderInterface;

class CookieReader implements CookieReaderInterface
{
    /**
     * @param string $name
     * @param string $default
     * @return mixed|null
     */
    public function getCookie($name, $default = null)
    {
        return (isset($_COOKIE[$name])) ? $_COOKIE[$name] : $default;
    }

    /**
     * @return array
     */
    public function getAllCookies()
    {
        return isset($_COOKIE) ? $_COOKIE : [];
    }
}
