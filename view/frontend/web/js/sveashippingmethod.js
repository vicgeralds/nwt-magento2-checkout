define([
    "jquery",
    "Svea_Checkout/js/model/bind-select-shipping",
    'Magento_Ui/js/modal/alert',
    "mage/translate",
    "jquery/ui"
], function($, bindSelectShipping, magealert, $t){
    "use strict";

    function main(config, element) {
        $(document).on('change', '.qty input', function() {
	        setTimeout(function() {
                $.ajax({
                    context: '#shipping-method-form',
                    url: '/sveacheckout/order/ReloadShippingMethods',
                    type: 'GET'
                }).done(function (data) {
                    if (data.requiredShippingAction == 2) {
                        window.scoApi.setCheckoutEnabled(false);
                        magealert({
                            content: $t("Please choose a shipping method.")
                        });
                    } else if (data.requiredShippingAction == 1) {
                        window.scoApi.setCheckoutEnabled(false);
                    }

                    $('#shipping-method-form').html(data.output);
                    bindSelectShipping.execute();
                    return true;
                });
            },2000);
        });
    };
    return main;
});
