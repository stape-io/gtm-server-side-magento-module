<?php

namespace Stape\Gtm\Model\Api\Request;

class Request implements RequestInterface
{
    /** @var string $endpoint */
    private $endpoint;

    /** @var array $data  */
    private $data = [];

    /**
     * Set URL
     *
     * @param string $url
     * @return $this
     */
    public function setUrl(string $url)
    {
        $this->endpoint = $url;
        return $this;
    }

    /**
     * Set data
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Retrieve URL
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->endpoint;
    }

    /**
     * Retrieve data
     *
     * @return array
     */
    public function getData()
    {
        return $this->data ?? [];
    }
}
