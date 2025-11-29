<?php

namespace Stape\Gtm\ViewModel;

interface DatalayerInterface
{

    /**
     * Retrieve event data
     *
     * @return array
     */
    public function getEventData();

    /**
     * Retrieve json data
     *
     * @return string
     */
    public function getJson();
}
