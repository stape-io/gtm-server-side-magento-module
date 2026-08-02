<?php

namespace Stape\Gtm\Controller\SameOrigin;

use GuzzleHttp\ClientFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Session\Config\ConfigInterface as SessionConfig;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Stape\Gtm\Controller\Router\SameOriginProxy;
use Stape\Gtm\Model\ConfigProvider;

/**
 * Same-origin proxy endpoint: transparently forwards first-party requests
 * to the Stape sGTM container derived from the container API key.
 */
class Proxy implements ActionInterface, CsrfAwareActionInterface
{
    /*
     * Upstream request timeout in seconds
     */
    private const TIMEOUT = 20;

    /*
     * Allowed HTTP methods; anything else is treated as GET
     */
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /*
     * Methods whose raw body is forwarded upstream
     */
    private const METHODS_WITH_BODY = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /*
     * Request headers never forwarded upstream (lowercase). "cookie" and "referer"
     * are re-added in a sanitized form; "authorization" is dropped so store
     * credentials (e.g. staging basic auth) never leave the server.
     */
    private const STRIP_REQUEST_HEADERS = [
        'host', 'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
        'te', 'trailer', 'transfer-encoding', 'upgrade', 'expect', 'content-length',
        'accept-encoding', 'authorization', 'cookie', 'referer',
    ];

    /*
     * Response headers never forwarded back to the client (lowercase):
     * hop-by-hop headers plus the encoding/length of the decoded body
     */
    private const STRIP_RESPONSE_HEADERS = [
        'connection', 'keep-alive', 'transfer-encoding', 'content-encoding',
        'content-length', 'te', 'trailer', 'upgrade',
    ];

    /*
     * Applied to responses the container does not describe itself: the test probe,
     * local error responses and upstream responses without a Cache-Control header
     */
    private const NO_CACHE_HEADERS = [
        'Cache-Control' => 'no-store, no-cache, private, max-age=0',
    ];

    /*
     * Store cookies never forwarded upstream and never accepted from upstream
     * (lowercase): session, authentication, CSRF and private content cookies
     */
    private const RESERVED_COOKIES = [
        'phpsessid', 'admin', 'form_key', 'x-magento-vary', 'private_content_version',
        'persistent_shopping_cart', 'mage-cache-sessid', 'mage-messages', 'section_data_ids',
        'user_allowed_save_cookie', 'guest-view', 'login_redirect',
    ];

    /**
     * @var HttpRequest $request
     */
    private $request;

    /**
     * @var RawFactory $rawFactory
     */
    private $rawFactory;

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var ClientFactory $clientFactory
     */
    private $clientFactory;

    /**
     * @var Escaper $escaper
     */
    private $escaper;

    /**
     * @var SessionConfig $sessionConfig
     */
    private $sessionConfig;

    /**
     * @var UriFactoryInterface $uriFactory
     */
    private $uriFactory;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * Define class dependencies
     *
     * @param HttpRequest $request
     * @param RawFactory $rawFactory
     * @param ConfigProvider $configProvider
     * @param StoreManagerInterface $storeManager
     * @param ClientFactory $clientFactory
     * @param Escaper $escaper
     * @param SessionConfig $sessionConfig
     * @param UriFactoryInterface $uriFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        HttpRequest $request,
        RawFactory $rawFactory,
        ConfigProvider $configProvider,
        StoreManagerInterface $storeManager,
        ClientFactory $clientFactory,
        Escaper $escaper,
        SessionConfig $sessionConfig,
        UriFactoryInterface $uriFactory,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->rawFactory = $rawFactory;
        $this->configProvider = $configProvider;
        $this->storeManager = $storeManager;
        $this->clientFactory = $clientFactory;
        $this->escaper = $escaper;
        $this->sessionConfig = $sessionConfig;
        $this->uriFactory = $uriFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Execute controller logic
     *
     * @return Raw
     */
    public function execute()
    {
        // test probe short-circuit: never forwarded upstream
        $testUid = $this->request->getParam('test-uid');
        if (is_string($testUid) && $testUid !== '') {
            return $this->applyNoCacheHeaders($this->createResult(200))
                ->setHeader('Content-Type', 'text/plain; charset=utf-8', true)
                ->setContents($this->escaper->escapeHtml($testUid));
        }

        if (strlen($this->configProvider->getSameOriginApiKey() ?? '') < 1) {
            return $this->applyNoCacheHeaders($this->createResult(404));
        }

        $endpoint = $this->configProvider->getSameOriginEndpoint();
        if (empty($endpoint)) {
            return $this->applyNoCacheHeaders($this->createResult(502));
        }

        return $this->proxy($endpoint);
    }

    /**
     * Forward the request upstream and mirror the response
     *
     * @param string $endpoint
     * @return Raw
     */
    private function proxy($endpoint)
    {
        $subPath = (string) $this->request->getParam(SameOriginProxy::PARAM_SUB_PATH);

        // web servers often serve/404 ".js"-like paths as static files, so the loader
        // uses the ".load" extension; map it back to ".js" for the upstream request
        $forceJsContentType = false;
        if (preg_match('/\.load$/i', $subPath)) {
            $subPath = preg_replace('/\.load$/i', '.js', $subPath);
            $forceJsContentType = true;
        }

        $query = (string) $this->request->getServer('QUERY_STRING');
        $url = $endpoint . $subPath . ($query !== '' ? '?' . $query : '');

        $method = strtoupper($this->request->getMethod());
        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            $method = 'GET';
        }

        $options = [
            'headers' => $this->buildUpstreamHeaders(),
            'timeout' => self::TIMEOUT,
            'allow_redirects' => false,
            'http_errors' => false,
            'verify' => true,
            'decode_content' => true,
        ];

        if (in_array($method, self::METHODS_WITH_BODY, true)) {
            $options['body'] = (string) $this->request->getContent();
        }

        try {
            $response = $this->clientFactory->create()->request($method, $url, $options);
            $status = (int) $response->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->debug(sprintf('[STAPE] Same-origin proxy request failed. Error: %s', $e->getMessage()));
            return $this->applyNoCacheHeaders($this->createResult(502));
        }

        $result = $this->createResult($status);
        $hasCacheControl = false;

        foreach ($response->getHeaders() as $name => $values) {
            $lowerName = strtolower($name);

            if (in_array($lowerName, self::STRIP_RESPONSE_HEADERS, true)) {
                continue;
            }

            if ($lowerName === 'cache-control') {
                $hasCacheControl = true;
                $result->setHeader($name, $this->forcePrivateCacheControl(implode(', ', $values)), true);
                continue;
            }

            if ($lowerName === 'set-cookie') {
                $first = true;
                foreach ($values as $cookie) {
                    // upstream must not be able to set or overwrite store cookies
                    if ($this->isReservedCookie((string) strstr($cookie, '=', true))) {
                        continue;
                    }

                    $cookie = preg_replace('/;\s*domain=[^;]*/i', '', $cookie);
                    $result->setHeader($name, $cookie, $first);
                    $first = false;
                }
                continue;
            }

            $result->setHeader($name, implode(', ', $values), true);
        }

        if ($forceJsContentType) {
            $result->setHeader('Content-Type', 'application/javascript; charset=utf-8', true);
        }

        if (!$hasCacheControl) {
            $this->applyNoCacheHeaders($result);
        }

        $result->setContents((string) $response->getBody());

        return $result;
    }

    /**
     * Create a raw result with the given status code
     *
     * @param int $status
     * @return Raw
     */
    private function createResult($status)
    {
        $result = $this->rawFactory->create();
        $result->setHttpResponseCode($status);

        return $result;
    }

    /**
     * Mark the result as non-cacheable
     *
     * @param Raw $result
     * @return Raw
     */
    private function applyNoCacheHeaders($result)
    {
        foreach (self::NO_CACHE_HEADERS as $name => $value) {
            $result->setHeader($name, $value, true);
        }

        return $result;
    }

    /**
     * Keep the upstream freshness directives but bar shared caches
     *
     * Proxy responses are keyed by URL only while their content is visitor specific,
     * so a shared cache (Varnish, CDN) must never store them. "private" is also what
     * Magento's own Varnish VCL matches on to mark a response uncacheable.
     *
     * @param string $value
     * @return string
     */
    private function forcePrivateCacheControl($value)
    {
        $directives = [];
        $isPrivate = false;

        foreach (explode(',', $value) as $directive) {
            $directive = trim($directive);
            $name = strtolower((string) strstr($directive . '=', '=', true));

            // "public" would contradict the "private" appended below
            if ($name === '' || $name === 'public') {
                continue;
            }

            $isPrivate = $isPrivate || $name === 'private' || $name === 'no-store';
            $directives[] = $directive;
        }

        if (empty($directives)) {
            return self::NO_CACHE_HEADERS['Cache-Control'];
        }

        if (!$isPrivate) {
            $directives[] = 'private';
        }

        return implode(', ', $directives);
    }

    /**
     * Build headers forwarded upstream
     *
     * All client headers except the strip list, a cookie header without store
     * cookies, a referer reduced to its origin, plus x-stape-host with the
     * first-party store host.
     *
     * @return array
     */
    private function buildUpstreamHeaders()
    {
        $headers = [];

        foreach ($this->request->getHeaders() as $header) {
            $name = $header->getFieldName();
            if (in_array(strtolower($name), self::STRIP_REQUEST_HEADERS, true)) {
                continue;
            }
            $headers[$name][] = $header->getFieldValue();
        }

        if ($cookie = $this->filterCookieHeader((string) $this->request->getHeader('Cookie'))) {
            $headers['Cookie'] = [$cookie];
        }

        if ($referer = $this->reduceRefererToOrigin((string) $this->request->getHeader('Referer'))) {
            $headers['Referer'] = [$referer];
        }

        if ($host = $this->getStoreHost()) {
            $headers['x-stape-host'] = [$host];
        }

        return array_map(
            function (array $values) {
                return count($values) === 1 ? $values[0] : $values;
            },
            $headers
        );
    }

    /**
     * Drop store cookies from the client cookie header
     *
     * Analytics cookies the container relies on are preserved.
     *
     * @param string $value
     * @return string
     */
    private function filterCookieHeader($value)
    {
        $kept = [];

        foreach (explode(';', $value) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            $name = strstr($pair, '=', true);
            if ($this->isReservedCookie($name === false ? $pair : $name)) {
                continue;
            }

            $kept[] = $pair;
        }

        return implode('; ', $kept);
    }

    /**
     * Reduce the referer to its origin
     *
     * Keeps storefront paths and query strings (search terms, checkout or
     * password reset parameters) from being disclosed upstream.
     *
     * @param string $value
     * @return string
     */
    private function reduceRefererToOrigin($value)
    {
        try {
            $uri = $this->uriFactory->createUri(trim($value));
        } catch (\Exception $e) {
            return '';
        }

        $host = $uri->getHost();

        if ($host === '') {
            return '';
        }

        $port = $uri->getPort();

        return ($uri->getScheme() ?: 'https') . '://' . $host . ($port === null ? '' : ':' . $port) . '/';
    }

    /**
     * Check whether the cookie name belongs to the store itself
     *
     * @param string $name
     * @return bool
     */
    private function isReservedCookie($name)
    {
        $name = strtolower(trim($name));

        if ($name === '') {
            return false;
        }

        return in_array($name, self::RESERVED_COOKIES, true) || $name === $this->getSessionCookieName();
    }

    /**
     * Retrieve the configured session cookie name
     *
     * @return string
     */
    private function getSessionCookieName()
    {
        try {
            return strtolower((string) $this->sessionConfig->getName());
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Retrieve host of the store base URL for the current store view
     *
     * @return string|null
     */
    private function getStoreHost()
    {
        try {
            $host = $this->uriFactory->createUri($this->storeManager->getStore()->getBaseUrl())->getHost();
            return $host !== '' ? $host : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
