<?php

namespace Stape\Gtm\Model\Datalayer\Modifier;

use Magento\Framework\Exception\LocalizedException;

class Pool implements \Stape\Gtm\Model\Datalayer\Modifier\PoolInterface
{
    /**
     * @var array
     */
    protected $modifiers = [];

    /**
     * @var array
     */
    protected $modifiersInstances = [];

    /**
     * @var ModifierFactory
     */
    protected $factory;

    /**
     * @param ModifierFactory $factory
     * @param array $modifiers
     */
    public function __construct(
        ModifierFactory $factory,
        array $modifiers = []
    ) {
        $this->factory = $factory;
        $this->modifiers = $modifiers;
    }

    /**
     * Retrieve modifiers
     *
     * @return array
     */
    public function getModifiers()
    {
        return $this->modifiers;
    }

    /**
     * Retrieve modifiers instantiated
     *
     * @return ModifierInterface[]
     * @throws LocalizedException
     */
    public function getModifiersInstances()
    {
        if ($this->modifiersInstances) {
            return $this->modifiersInstances;
        }

        foreach ($this->modifiers as $modifier) {
            if (empty($modifier['class'])) {
                throw new LocalizedException(__('The parameter "class" is missing. Set the "class" and try again.'));
            }

            $this->modifiersInstances[$modifier['class']] = $this->factory->create($modifier['class']);
        }

        return $this->modifiersInstances;
    }
}
