<?php

namespace Stape\Gtm\Model;

use Magento\Framework\Stdlib\Cookie\CookieReaderInterface;

class CookieReader implements CookieReaderInterface
{
    /**
     * Retrieve cookie
     *
     * @param string $name
     * @param string $default
     * @return mixed|null
     */
    public function getCookie($name, $default = null)
    {
        // phpcs:disable
        return (isset($_COOKIE[$name])) ? $_COOKIE[$name] : $default;
        // phpcs:enable
    }

    /**
     * Retrieve all cookies
     *
     * @return array
     */
    public function getAllCookies()
    {
        // phpcs:disable
        return isset($_COOKIE) ? $_COOKIE : [];
        // phpcs:enable
    }
}
