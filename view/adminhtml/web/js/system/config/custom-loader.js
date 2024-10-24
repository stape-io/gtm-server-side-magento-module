define(['jquery'], function($) {
    "use strict"
    return function(config, element) {
        $(config?.trackOptions).prop('disabled', $(element)?.val()?.length < 1 ? 'disabled' : '');
        $(element).on('change', function() {
            $(config?.trackOptions).prop('disabled', $(this).val().length < 1 ? 'disabled': '');
        })
    }
});
