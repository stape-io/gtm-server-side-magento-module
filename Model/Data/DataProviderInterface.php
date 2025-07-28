<?php

namespace Stape\Gtm\Model\Data;

interface DataProviderInterface
{
    /**
     * Retrieve data
     *
     * @return mixed
     */
    public function get();

    /**
     * Add data
     *
     * @param string $eventName
     * @param array $data
     * @return mixed
     */
    public function add($eventName, $data);

    /**
     * Clear data
     *
     * @return mixed
     */
    public function clear();
}
