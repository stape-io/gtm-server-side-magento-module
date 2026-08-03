define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    var uidCounter = 0;

    /**
     * Build a one-time token for the connection probe. The token is not a
     * secret: it is echoed back by the proxy so the browser can tell a real
     * proxy response apart from a cached page or an unrelated route.
     *
     * @returns {String}
     */
    function generateUid() {
        uidCounter += 1;

        return Date.now().toString(36) + '-' + uidCounter.toString(36);
    }

    /**
     * Browser-side GET probe: succeeds only when the response is 2xx and
     * the body echoes the uid back exactly.
     *
     * @param {String} url
     * @param {String} uid
     * @returns {Promise}
     */
    function probe(url, uid) {
        return $.ajax({
            url: url,
            method: 'GET',
            dataType: 'text',
            timeout: 20000
        }).then(function (body) {
            return String(body).trim() === uid;
        }, function () {
            return $.Deferred().resolve(false).promise();
        });
    }

    /**
     * Copy the given text to the clipboard, falling back to a temporary
     * selection for browsers without the async clipboard API.
     *
     * @param {String} text
     * @returns {Promise}
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return $.Deferred(function (deferred) {
            var $temp = $('<textarea>').val(text).css({position: 'fixed', opacity: 0}).appendTo('body');

            $temp[0].select();

            try {
                document.execCommand('copy') ? deferred.resolve() : deferred.reject();
            } catch (e) {
                deferred.reject(e);
            } finally {
                $temp.remove();
            }
        }).promise();
    }

    return function (config, element) {
        var $button = $(element),
            $message = $(config.messageContainer),
            $panel = $(config.panelContainer),
            $checking = $(config.checkingContainer),
            $success = $(config.successContainer),
            $error = $(config.errorContainer),
            $errorText = $(config.errorTextContainer),
            $url = $(config.urlContainer),
            $copy = $(config.copyButton),
            $feedback = $(config.copyFeedback),
            $pathField = $(config.pathFieldSelector),
            savedPath = config.savedPath || '',
            baseUrl = (config.baseUrl || '').replace(/\/+$/, ''),
            feedbackTimer = null;

        /**
         * Update the message element state.
         *
         * @param {String} type notice|success|warning
         * @param {String} text
         */
        function showMessage(type, text) {
            $message
                .removeClass('message message-notice message-success message-warning')
                .addClass('message message-' + type)
                .text(text);
        }

        /**
         * Post-save only: disabled until the saved path is non-empty and
         * equals the current input value.
         */
        function syncState() {
            var current = $pathField.length ? String($pathField.val()).trim() : savedPath,
                ready = savedPath !== '' && current === savedPath;

            $button.prop('disabled', !ready);

            if (!ready) {
                showMessage('notice', $t('Save settings first, then run Test connection.'));
            } else {
                $message.removeClass('message message-notice message-success message-warning').text('');
            }
        }

        /**
         * Run both probes against the saved path.
         *
         * @returns {Promise} resolved with the plain and ".load" probe results
         */
        function runProbes() {
            var uid = generateUid(),
                probeUid = generateUid(),
                path = '/' + savedPath.replace(/^\/+/, ''),
                plainUrl = baseUrl + path + (path.indexOf('?') === -1 ? '?' : '&') + 'test-uid=' + encodeURIComponent(uid),
                loadUrl = baseUrl + path.replace(/\/+$/, '') + '/probe.load?test-uid=' + encodeURIComponent(probeUid);

            return $.when(probe(plainUrl, uid), probe(loadUrl, probeUid));
        }

        /**
         * Check the saved proxy path once, on page load, and render the result.
         */
        function checkStatus() {
            if (savedPath === '' || !config.checkStatus) {
                $panel.hide();

                return;
            }

            $panel.show();
            $checking.prop('hidden', false);
            $success.prop('hidden', true);
            $error.prop('hidden', true);

            runProbes().done(function (plainOk, loadOk) {
                $checking.prop('hidden', true);

                if (plainOk && loadOk) {
                    $success.prop('hidden', false);

                    return;
                }

                $errorText.text(plainOk ?
                    $t('The proxy path responds, but extension-suffixed requests (".load") do not reach Magento. Your web server likely serves such paths as static files — route the proxy path to Magento explicitly.') :
                    $t('The proxy path is not reachable. It is likely shadowed by an existing route, redirect, URL rewrite, or a CDN/web-server rule.')
                );
                $error.prop('hidden', false);
            });
        }

        syncState();
        $pathField.on('input change keyup', syncState);
        checkStatus();

        $copy.on('click', function (e) {
            e.preventDefault();

            copyToClipboard($url.text()).then(function () {
                $feedback.prop('hidden', false);
                clearTimeout(feedbackTimer);
                feedbackTimer = setTimeout(function () {
                    $feedback.prop('hidden', true);
                }, 2000);
            });
        });

        $button.on('click', function (e) {
            e.preventDefault();
            showMessage('notice', $t('Testing connection…'));

            runProbes().done(function (plainOk, loadOk) {
                if (plainOk && loadOk) {
                    showMessage('success', $t('Success! The proxy path is reachable and responds correctly.'));
                } else if (plainOk) {
                    showMessage('warning', $t('The proxy path responds, but extension-suffixed requests (".load") do not reach Magento. Your web server likely serves such paths as static files — route the proxy path to Magento explicitly.'));
                } else {
                    showMessage('warning', $t('Test failed. The path is likely shadowed by an existing route, redirect, URL rewrite, or a CDN/web-server rule. Saving is not blocked — this check is informational only.'));
                }
            });
        });
    };
});
