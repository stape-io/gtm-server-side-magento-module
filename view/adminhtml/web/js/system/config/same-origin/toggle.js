define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $switch = $(element),
            $select = $(config.selectSelector),
            $inherit = $(config.inheritSelector);

        if (!$select.length) {
            return;
        }

        /**
         * Reflect the select value on the switch
         */
        function syncSwitch() {
            $switch.prop('checked', String($select.val()) === '1');
        }

        /**
         * Reflect the "Use Default" state on the switch
         */
        function syncDisabled() {
            var disabled = $select.prop('disabled');

            $switch.prop('disabled', disabled).toggleClass('disabled', disabled);
        }

        syncSwitch();
        syncDisabled();

        $switch.on('change', function () {
            $select.val(this.checked ? '1' : '0');

            // the config form dependencies are bound with native listeners
            $select.get(0).dispatchEvent(new Event('change', {
                bubbles: true
            }));
        });

        $select.on('change', syncSwitch);
        $inherit.on('change', syncDisabled);
    };
});
