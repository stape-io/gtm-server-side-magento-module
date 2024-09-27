<?php

namespace Stape\Gtm\Model\Data;

class RandomSuffix
{
    /**
     * @param string $loaderId
     * @return string
     */
    public function generate($loaderId)
    {
        $default = http_build_query(['apiKey' => mb_substr(md5($loaderId), 0, 8)]);
        $options = [
            $default,
            'page=1',
            'page=2',
            'page=3',
            'sort=asc',
            'sort=desc',
        ];
        $key = rand(0, count($options) - 1);
        return $options[$key] ?? $default;
    }
}
