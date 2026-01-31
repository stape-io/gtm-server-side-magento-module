<?php

namespace Stape\Gtm\Model\Api\Request;

interface RequestInterface
{
    /**
     * Popuplate URL
     *
     * @param string $url
     * @return RequestInterface
     */
    public function setUrl(string $url);

    /**
     * Retrieve URL
     *
     * @return string
     */
    public function getUrl();

    /**
     * Populate data
     *
     * @param array $data
     * @return RequestInterface
     */
    public function setData(array $data);

    /**
     * Retrieve data
     *
     * @return array
     */
    public function getData();
}
