define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $switch = $(element),
            $select = $(config.selectSelector),
            $inherit = $(config.inheritSelector),
            $groupRows = $((config.groupRows || []).join(', '));

        if (!$select.length) {
            return;
        }

        /**
         * Highlight the same-origin rows as a single group
         */
        function syncGroup() {
            var $visible = $groupRows.filter(':visible'),
                $first = $visible.first();

            $('.stape-so-group_before').removeClass('stape-so-group_before');
            $groupRows.addClass('stape-so-group')
                .removeClass('stape-so-group_first stape-so-group_last');
            $first.addClass('stape-so-group_first');
            $visible.last().addClass('stape-so-group_last');
            $first.prevAll('tr:visible').first().addClass('stape-so-group_before');
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
        syncGroup();

        $switch.on('change', function () {
            $select.val(this.checked ? '1' : '0');

            // the config form dependencies are bound with native listeners
            $select.get(0).dispatchEvent(new Event('change', {
                bubbles: true
            }));

            syncGroup();
        });

        $select.on('change', function () {
            syncSwitch();
            syncGroup();
        });
        $inherit.on('change', syncDisabled);
    };
});
