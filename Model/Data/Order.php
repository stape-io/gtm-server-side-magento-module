<?php

namespace Stape\Gtm\Model\Data;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;

class Order
{
    /**
     * @var CollectionFactoryInterface
     */
    private $orderCollectionFactory;

    /**
     * Define class dependencies
     *
     * @param CollectionFactoryInterface $orderCollectionFactory
     */
    public function __construct(CollectionFactoryInterface $orderCollectionFactory)
    {
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Check if customer is new
     *
     * @param string $email
     * @return bool
     */
    public function isNewCustomer($email)
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        $collection = $this->orderCollectionFactory->create()
            ->addFieldToFilter('customer_email', $email);

        return $collection->getSize() <= 1;
    }

    /**
     * Retrieve total amount paid
     *
     * @param string $email
     * @return string
     */
    public function getLifetimeSpent($email)
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        $collection = $this->orderCollectionFactory->create()
            ->addFieldToFilter('customer_email', $email);
        $select = $collection->getSelect()
            ->reset(\Magento\Framework\Db\Select::COLUMNS)
            ->columns(new \Zend_Db_Expr('SUM(total_paid) - SUM(total_refunded) as lifetime_spent'));
        $result = $select->getConnection()->fetchOne($select);
        return $result ?? 0;
    }
}
