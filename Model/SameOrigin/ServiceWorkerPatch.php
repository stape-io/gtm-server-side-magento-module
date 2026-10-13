<?php

namespace Stape\Gtm\Model\SameOrigin;

/**
 * Patches the container's service worker registration to survive the same-origin proxy.
 *
 * The bug: the container does not register its service worker from the page itself — it
 * loads "{base path}/_/service_worker/{id}/sw_iframe.html" through the proxy, and that
 * document calls navigator.serviceWorker.register('sw.js?...') with a RELATIVE url. The
 * relative url resolves under the proxy base path, where a web server's static file handler
 * answers it before PHP/Magento is reached, returning a 404 that then sits in a long-lived
 * HTTP cache. The patch below wraps register() and rewrites a same-origin ".js" script that
 * falls under the proxy base path to ".load"; Controller\SameOrigin\Proxy maps ".load" back
 * to ".js" for the upstream request, so the registration reaches the container instead of
 * the web server's static handler.
 *
 * BUNDLE is the minified form of the following ES5 source. Keep the two in sync — change
 * both together:
 *
 *     "use strict";
 *     (function () {
 *         function rewrite(script, location, basePath) {
 *             var url;
 *             try {
 *                 url = new URL(script, location.href);
 *             } catch (e) {
 *                 return null;
 *             }
 *             if (url.origin !== location.origin
 *                 || url.pathname.indexOf(basePath) !== 0
 *                 || !/\.js$/i.test(url.pathname)
 *             ) {
 *                 return null;
 *             }
 *             url.pathname = url.pathname.replace(/\.js$/i, '.load');
 *             return url.href;
 *         }
 *
 *         function patch(win, basePath) {
 *             var container = win.navigator && win.navigator.serviceWorker;
 *             if (!container || typeof container.register != 'function') {
 *                 return false;
 *             }
 *             var original = container.register;
 *             container.register = function (script, options) {
 *                 var rewritten = rewrite(String(script), win.location, basePath);
 *                 if (rewritten !== null) {
 *                     try {
 *                         return original.call(this, rewritten, options);
 *                     } catch (e) {
 *                         // fall through and retry with the original argument
 *                     }
 *                 }
 *                 return original.call(this, script, options);
 *             };
 *             return true;
 *         }
 *
 *         var basePath = window.gtmServerSideSameOriginBase;
 *         if (typeof basePath == 'string' && basePath !== '') {
 *             delete window.gtmServerSideSameOriginBase;
 *             patch(window, basePath);
 *         }
 *     })();
 */
class ServiceWorkerPatch
{
    /*
     * Global variable name the bundle reads the proxy base path from, then deletes
     */
    private const BASE_VARIABLE = 'gtmServerSideSameOriginBase';

    /*
     * Flags applied when encoding the base path for inline embedding in a <script> element
     */
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    /*
     * Minified ES5 patch bundle; see the class docblock for the readable source
     */
    private const BUNDLE = '"use strict";(function(){function c(t,i,e){var r;try{r=new URL(t,i.href)}catch(n){return null}return r.origin!==i.origin||r.pathname.indexOf(e)!==0||!/\.js$/i.test(r.pathname)?null:(r.pathname=r.pathname.replace(/\.js$/i,".load"),r.href)}function u(t,i){var e=t.navigator&&t.navigator.serviceWorker;if(!e||typeof e.register!="function")return!1;var r=e.register;return e.register=function(n,l){var o=c(String(n),t.location,i);if(o!==null)try{return r.call(this,o,l)}catch(f){}return r.call(this,n,l)},!0}var a=window.gtmServerSideSameOriginBase;typeof a=="string"&&a!==""&&(delete window.gtmServerSideSameOriginBase,u(window,a));})();';

    /**
     * Build the inline script source that patches service worker registration
     *
     * The JSON_HEX_* flags are what keep the encoded base path from being able to terminate
     * the surrounding <script> element early; Magento\Framework\Serialize\Serializer\Json is
     * not used here because it cannot express these flags.
     *
     * @param string $basePath Browser-visible proxy base path, e.g. from BasePath::get()
     * @return string Empty string when the base path is empty or not absolute
     */
    public function getScript($basePath)
    {
        $basePath = rtrim((string) $basePath, '/');

        if ($basePath === '' || strpos($basePath, '/') !== 0) {
            return '';
        }

        // the trailing slash appended here is what keeps a base path of "/data" from also
        // matching "/database/a/sw.js"; the loader rewrite in Proxy uses the same path
        // without the trailing slash
        $encoded = json_encode($basePath . '/', self::JSON_FLAGS);

        if (!is_string($encoded)) {
            return '';
        }

        return sprintf('window.%s=%s;%s', self::BASE_VARIABLE, $encoded, self::BUNDLE);
    }

    /**
     * Build the standalone inline <script> element that patches service worker registration
     *
     * @param string $basePath Browser-visible proxy base path, e.g. from BasePath::get()
     * @return string Empty string when the base path is empty or not absolute
     */
    public function getScriptElement($basePath)
    {
        $script = $this->getScript($basePath);

        if ($script === '') {
            return '';
        }

        return '<script>' . $script . '</script>';
    }

    /**
     * Prepend the patch script element to a snippet
     *
     * @param string $snippet
     * @param string $basePath Browser-visible proxy base path, e.g. from BasePath::get()
     * @return string
     */
    public function prependTo($snippet, $basePath)
    {
        $element = $this->getScriptElement($basePath);

        if ($element === '') {
            return (string) $snippet;
        }

        return $element . (string) $snippet;
    }

    /**
     * Inject the patch script element into an HTML document
     *
     * Inserted right after the opening <head> tag when present, otherwise right after the
     * opening <body> tag. The document is returned unchanged when the base path is empty or
     * neither tag is found.
     *
     * @param string $html
     * @param string $basePath Browser-visible proxy base path, e.g. from BasePath::get()
     * @return string
     */
    public function injectIntoDocument($html, $basePath)
    {
        $html = (string) $html;
        $element = $this->getScriptElement($basePath);

        if ($element === '') {
            return $html;
        }

        foreach (['#<head\b[^>]*>#i', '#<body\b[^>]*>#i'] as $pattern) {
            if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE)) {
                return substr_replace($html, $element, $match[0][1] + strlen($match[0][0]), 0);
            }
        }

        return $html;
    }
}
