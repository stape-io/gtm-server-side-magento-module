<?php

declare(strict_types=1);

namespace Stape\Gtm\Model\Datalayer\Modifier;

interface PoolInterface
{
    /**
     * Retrieve modifiers
     *
     * @return array
     */
    public function getModifiers();

    /**
     * Retrieve modifiers instantiated
     *
     * @return ModifierInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getModifiersInstances();
}
