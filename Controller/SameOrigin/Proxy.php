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
use Magento\Store\Model\StoreManagerInterface;
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
     * Request headers never forwarded upstream (lowercase)
     */
    private const STRIP_REQUEST_HEADERS = [
        'host', 'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
        'te', 'trailer', 'transfer-encoding', 'upgrade', 'expect', 'content-length',
        'accept-encoding',
    ];

    /*
     * Response headers never forwarded back to the client (lowercase):
     * hop-by-hop headers plus restrictive security headers from upstream
     */
    private const STRIP_RESPONSE_HEADERS = [
        'connection', 'keep-alive', 'transfer-encoding', 'content-encoding',
        'content-length', 'te', 'trailer', 'upgrade',
        'x-frame-options', 'x-content-type-options', 'referrer-policy',
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
     * @param LoggerInterface $logger
     */
    public function __construct(
        HttpRequest $request,
        RawFactory $rawFactory,
        ConfigProvider $configProvider,
        StoreManagerInterface $storeManager,
        ClientFactory $clientFactory,
        Escaper $escaper,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->rawFactory = $rawFactory;
        $this->configProvider = $configProvider;
        $this->storeManager = $storeManager;
        $this->clientFactory = $clientFactory;
        $this->escaper = $escaper;
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
            return $this->createResult(200)
                ->setHeader('Content-Type', 'text/plain; charset=utf-8', true)
                ->setHeader('Cache-Control', 'no-store', true)
                ->setContents($this->escaper->escapeHtml($testUid));
        }

        if (strlen($this->configProvider->getSameOriginApiKey() ?? '') < 1) {
            return $this->createResult(404);
        }

        $endpoint = $this->configProvider->getSameOriginEndpoint();
        if (empty($endpoint)) {
            return $this->createResult(502);
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
            'decode_content' => false,
        ];

        if (in_array($method, self::METHODS_WITH_BODY, true)) {
            $options['body'] = (string) $this->request->getContent();
        }

        try {
            $response = $this->clientFactory->create()->request($method, $url, $options);
            $status = (int) $response->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->debug(sprintf('[STAPE] Same-origin proxy request failed. Error: %s', $e->getMessage()));
            return $this->createResult(502);
        }

        $result = $this->createResult($status);

        foreach ($response->getHeaders() as $name => $values) {
            $lowerName = strtolower($name);

            if (in_array($lowerName, self::STRIP_RESPONSE_HEADERS, true)) {
                continue;
            }

            if ($lowerName === 'set-cookie') {
                $first = true;
                foreach ($values as $cookie) {
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
     * Build headers forwarded upstream: all client headers except the strip
     * list, plus x-stape-host with the first-party store host
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
     * Retrieve host of the store base URL for the current store view
     *
     * @return string|null
     */
    private function getStoreHost()
    {
        try {
            $parts = parse_url($this->storeManager->getStore()->getBaseUrl());
            return $parts['host'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
