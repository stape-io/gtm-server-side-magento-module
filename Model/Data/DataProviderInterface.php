<?php

namespace Stape\Gtm\Model\Data;

interface DataProviderInterface
{
    public function get();
    public function add($eventName, $data);
    public function clear();
}
