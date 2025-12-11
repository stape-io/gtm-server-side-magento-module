<?php

namespace Stape\Gtm\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class Compare extends DatalayerAbstract implements ArgumentInterface
{
    /**
     * Retrieve event data
     *
     * @return array
     */
    public function getEventData()
    {
        return [];
    }
}
