<?php

namespace Stape\Gtm\ViewModel;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Compare implements ArgumentInterface, DatalayerInterface
{
    /**
     * @var Json $json
     */
    private $json;

    public function __construct(Json $json)
    {
        $this->json = $json;
    }

    /**
     * Retrieve json
     *
     * @return bool|string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getJson()
    {
        return $this->json->serialize([]);
    }
}
