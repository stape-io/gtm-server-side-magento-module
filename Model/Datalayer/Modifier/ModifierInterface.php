<?php

namespace Stape\Gtm\Model\Datalayer\Modifier;

interface ModifierInterface
{
    /**
     * Modify event data
     *
     * @param array $data
     * @return array
     */
    public function modifyEventData(array $data);
}
