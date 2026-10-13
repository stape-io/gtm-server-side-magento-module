<?php

namespace Stape\Gtm\Model\Http;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-17 URI factory backed by the PSR-7 implementation shipped with Guzzle.
 *
 * Magento's DI compiler only binds preferences to classes inside its
 * compilation scope, so the vendor implementation is wrapped here instead of
 * being referenced directly in di.xml.
 */
class UriFactory implements UriFactoryInterface
{
    /**
     * Create a URI from a string
     *
     * @param string $uri
     * @return UriInterface
     * @throws \InvalidArgumentException When the given string is not a valid URI.
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
