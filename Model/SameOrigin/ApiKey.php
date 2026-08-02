<?php

namespace Stape\Gtm\Model\SameOrigin;

/**
 * Parser for the Stape container API key used by the same-origin proxy.
 *
 * The key is colon-separated: a0:a1:a2[:a3], e.g. "usc:vzrhbaeg:cefb...:".
 * The upstream sGTM endpoint is derived as https://{a1}.{a0}.stape.{a3|io}
 * and {a1} is the container identifier for the custom-loader API.
 */
class ApiKey
{
    /*
     * Default TLD segment when the 4th key segment is missing or empty
     */
    private const DEFAULT_TLD = 'io';

    /*
     * Region and identifier are used as DNS labels of the endpoint host
     */
    private const LABEL_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i';

    /*
     * TLD segment of the endpoint host
     */
    private const TLD_PATTERN = '/^[a-z]{2,63}$/i';

    /**
     * Parse the API key into its segments
     *
     * @param string|null $key
     * @return array|null [region, identifier, secret, tld] or null on parse failure
     */
    public function parse($key)
    {
        $parts = explode(':', trim((string) $key));

        // invalid if the segment count is unexpected or the secret is empty
        if (count($parts) < 3 || count($parts) > 4 || strlen($parts[2]) < 1) {
            return null;
        }

        $tld = (isset($parts[3]) && strlen($parts[3]) > 0) ? $parts[3] : self::DEFAULT_TLD;

        // the segments end up in the endpoint host, so they must be valid host labels
        if (!preg_match(self::LABEL_PATTERN, $parts[0])
            || !preg_match(self::LABEL_PATTERN, $parts[1])
            || !preg_match(self::TLD_PATTERN, $tld)
        ) {
            return null;
        }

        return [
            'region' => $parts[0],
            'identifier' => $parts[1],
            'secret' => $parts[2],
            'tld' => $tld,
        ];
    }

    /**
     * Retrieve upstream sGTM endpoint derived from the API key
     *
     * @param string|null $key
     * @return string|null e.g. https://vzrhbaeg.usc.stape.io
     */
    public function getEndpoint($key)
    {
        if (!$parts = $this->parse($key)) {
            return null;
        }
        return sprintf('https://%s.%s.stape.%s', $parts['identifier'], $parts['region'], $parts['tld']);
    }

    /**
     * Retrieve container identifier (second key segment) used for the custom-loader API
     *
     * @param string|null $key
     * @return string|null
     */
    public function getIdentifier($key)
    {
        if (!$parts = $this->parse($key)) {
            return null;
        }
        return $parts['identifier'];
    }
}
