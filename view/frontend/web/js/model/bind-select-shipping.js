define(['jquery'], function ($) { 
    'use strict';

    return {
        callback: null,
        setCallback: function (callback) {
            this.callback = callback;
        },
        execute: function () {
            $('#shipping-method-form').find('input[type=radio]').on('change', this.callback);
        }
    };
});
