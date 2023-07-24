<?php

namespace Stape\Gtm\Plugin\CustomerData;

use Magento\Customer\CustomerData\Customer;
use Magento\Customer\Helper\Session\CurrentCustomer;

class CustomerPlugin
{

    /**
     * @var CurrentCustomer $currentCustomer
     */
    private $currentCustomer;

    /**
     * Define class dependencies
     *
     * @param CurrentCustomer $currentCustomer
     */
    public function __construct(CurrentCustomer $currentCustomer)
    {
        $this->currentCustomer = $currentCustomer;
    }

    /**
     * Add more cusotmer info
     *
     * @param Customer $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData(Customer $subject, $result)
    {
        if ($this->currentCustomer->getCustomerId()) {
            $customer = $this->currentCustomer->getCustomer();
            $result['id'] = $customer->getId();
            $result['email'] = $customer->getEmail();
            $result['lastname'] = $customer->getLastname();
        }
        return $result;
    }
}
