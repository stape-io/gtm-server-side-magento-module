<?php

namespace Stape\Gtm\Plugin\CustomerData;

use Stape\Gtm\Model\Data\DataProviderInterface;

class CartPlugin
{
    /**
     * @var DataProviderInterface
     */
    private $dataProvider;

    /**
     * @param DataProviderInterface $dataProvider
     */
    public function __construct(DataProviderInterface $dataProvider)
    {
        $this->dataProvider = $dataProvider;
    }

    /**
     * Add stape_gtm_events data to the cart section data
     *
     * @param $subject
     * @param $result
     * @return mixed
     */
    public function afterGetSectionData($subject, $result)
    {
        $eventsData = $this->dataProvider->get();
        $this->dataProvider->clear();

        if (!empty($eventsData)) {
            $result['stape_gtm_events'] = $eventsData;
        }

        return $result;
    }
}
