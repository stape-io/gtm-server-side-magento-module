<?php

namespace Stape\Gtm\Model\Data;

class RandomSuffix
{
    /**
     * Generate random suffix
     *
     * @param null|string $loaderId
     * @return string
     */
    public function generate($loaderId = null)
    {
        $default = 'page=1';
        $options = [
            $default,
            'page=2',
            'page=3',
            'sort=asc',
            'sort=desc',
        ];
        $key = rand(0, count($options) - 1);
        return $options[$key] ?? $default;
    }
}
