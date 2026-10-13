<?php

namespace Stape\Gtm\Model\SameOrigin;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Request-scoped marker for a same-origin proxy passthrough response.
 *
 * Flags that the current response is being served by the same-origin proxy — a passthrough
 * of upstream container bytes — rather than being Magento-rendered storefront HTML.
 *
 * This is a DI-shared marker rather than a request parameter because a request parameter
 * (query string, header, route param) would be spoofable from any page and would become a
 * way for a visitor to suppress the store's CSP header on arbitrary storefront requests.
 * Only Controller\SameOrigin\Proxy itself is in a position to call markActive(), so the flag
 * can only ever be true for the request it actually applies to.
 */
class ProxyContext implements ResetAfterRequestInterface
{
    /**
     * @var bool $active
     */
    private $active = false;

    /**
     * Mark the current request as a same-origin proxy passthrough
     *
     * @return void
     */
    public function markActive()
    {
        $this->active = true;
    }

    /**
     * Check whether the current request is a same-origin proxy passthrough
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Reset state between requests when running under a long-lived process
     *
     * @return void
     */
    public function _resetState(): void
    {
        $this->active = false;
    }
}
