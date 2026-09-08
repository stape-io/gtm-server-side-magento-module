<?php

namespace Stape\Gtm\Model\SameOrigin;

use Magento\Framework\App\RequestInterface;

/**
 * Resolves the visitor's IP address behind the same-origin proxy.
 *
 * The proxy turns what was a browser request into a server-to-server request to the
 * container's upstream endpoint. Left alone, the container would see the web server making
 * the request as the client and geo-locate every visitor to the hosting datacenter.
 * REMOTE_ADDR is only the connecting peer, which behind a CDN or load balancer is that hop
 * rather than the visitor, so the real address has to be read from forwarding headers.
 *
 * resolve() and isProxyTrusted() are deliberately not final so merchants can override
 * either with a di.xml plugin.
 */
class ClientIp
{
    /*
     * Headers checked for the visitor address, in order. CDN-specific headers are checked
     * first because they are set closest to the visitor; X-Forwarded-For is checked last
     * because intermediate proxies can each append to it.
     */
    private const HEADER_SOURCES = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_TRUE_CLIENT_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
    ];

    /**
     * @var RequestInterface $request
     */
    private $request;

    /**
     * Define class dependencies
     *
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Check whether forwarding headers can be trusted for this deployment
     *
     * Returns true by default. Sites that terminate connections directly, where these
     * headers are attacker-controlled and REMOTE_ADDR is already correct, should disable
     * this with a di.xml plugin.
     *
     * @return bool
     */
    public function isProxyTrusted()
    {
        return true;
    }

    /**
     * Resolve the visitor's IP address
     *
     * @return string Empty string when no valid address can be resolved
     */
    public function resolve()
    {
        if ($this->isProxyTrusted()) {
            foreach (self::HEADER_SOURCES as $header) {
                $value = (string) $this->request->getServer($header);

                if ($value === '') {
                    continue;
                }

                foreach (explode(',', $value) as $candidate) {
                    $normalized = $this->normalize($candidate, true);

                    if ($normalized !== '') {
                        return $normalized;
                    }
                }
            }
        }

        return $this->normalize((string) $this->request->getServer('REMOTE_ADDR'), false);
    }

    /**
     * Normalize and validate a single address candidate
     *
     * @param string $candidate
     * @param bool $publicOnly Reject private and reserved ranges
     * @return string Empty string when the candidate is not a valid address
     */
    private function normalize($candidate, $publicOnly)
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return '';
        }

        if (strpos($candidate, '[') === 0) {
            $end = strpos($candidate, ']');
            $candidate = $end === false ? substr($candidate, 1) : substr($candidate, 1, $end - 1);
        } elseif (substr_count($candidate, ':') === 1 && strpos($candidate, '.') !== false) {
            $candidate = (string) strstr($candidate, ':', true);
        }

        $flags = $publicOnly ? FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE : 0;
        $valid = filter_var($candidate, FILTER_VALIDATE_IP, $flags);

        return $valid === false ? '' : (string) $valid;
    }
}
