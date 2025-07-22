<?php

namespace Stape\Gtm\Model\Datalayer\Formatter;

use Stape\Gtm\Model\ConfigProvider;

class Event
{
    /*
     * Stape event suffix
     */
    const STAPE_EVENT_SUFFIX = '_stape';

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     */
    public function __construct(ConfigProvider $configProvider)
    {
        $this->configProvider = $configProvider;
    }

    /**
     * @param string $eventName
     * @param string|int $scopeCode
     * @return string
     */
    public function formatName($eventName, $scopeCode = null)
    {
        if ($this->configProvider->isStapeEventSuffixActive($scopeCode)) {
            return $eventName . self::STAPE_EVENT_SUFFIX;
        }

        return $eventName;
    }
}
