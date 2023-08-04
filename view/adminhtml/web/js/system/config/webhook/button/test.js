define(['jquery'], function($) {
    "use strict"
    return function(config, element) {
        $(element).on('click', function() {
            $.getJSON(config.actionUrl, [], function(response) {
                $(config.messageContainer)
                    .removeClass('message')
                    .removeClass('message-success')
                    .removeClass('message-warning')
                    .addClass('message message-' + (response.status === 'ok' ? 'success' : 'warning'))
                    .text(response.message);
            })
        })
    }
});
