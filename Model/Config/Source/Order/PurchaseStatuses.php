<?php

namespace Stape\Gtm\Model\Config\Source\Order;

class PurchaseStatuses extends \Magento\Sales\Model\Config\Source\Order\Status
{
    /** @var string[] */
    protected $_stateStatuses = [
        \Magento\Sales\Model\Order::STATE_NEW,
        \Magento\Sales\Model\Order::STATE_PROCESSING,
        \Magento\Sales\Model\Order::STATE_COMPLETE,
    ];
}
