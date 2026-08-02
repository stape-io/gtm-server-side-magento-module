define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    /**
     * Generate a one-time random uid for the connection probe.
     *
     * @returns {String}
     */
    function generateUid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
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

    return function (config, element) {
        var $button = $(element),
            $message = $(config.messageContainer),
            $pathField = $(config.pathFieldSelector),
            savedPath = config.savedPath || '',
            baseUrl = (config.baseUrl || '').replace(/\/+$/, '');

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
        function syncButtonState() {
            var current = $pathField.length ? String($pathField.val()).trim() : savedPath,
                ready = savedPath !== '' && current === savedPath;

            $button.prop('disabled', !ready);

            if (!ready) {
                showMessage('notice', $t('Save settings first, then run Test connection.'));
            } else {
                $message.removeClass('message message-notice message-success message-warning').text('');
            }
        }

        syncButtonState();
        $pathField.on('input change keyup', syncButtonState);

        $button.on('click', function (e) {
            var uid = generateUid(),
                probeUid = generateUid(),
                path = '/' + savedPath.replace(/^\/+/, ''),
                plainUrl = baseUrl + path + (path.indexOf('?') === -1 ? '?' : '&') + 'test-uid=' + encodeURIComponent(uid),
                loadUrl = baseUrl + path.replace(/\/+$/, '') + '/probe.load?test-uid=' + encodeURIComponent(probeUid);

            e.preventDefault();
            showMessage('notice', $t('Testing connection…'));

            $.when(probe(plainUrl, uid), probe(loadUrl, probeUid)).done(function (plainOk, loadOk) {
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
