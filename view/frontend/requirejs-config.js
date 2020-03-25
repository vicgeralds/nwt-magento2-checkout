var config = {
    map: {
        '*': {
            sveaCheckout: 'Svea_Checkout/js/checkout',
            sveaShippingMethod: 'Svea_Checkout/js/sveashippingmethod',
        }
    },
    paths: {
        slick: 'Svea_Checkout/js/lib/slick.min'
    },
    shim: {
        slick: {
            deps: ['jquery']
        }
    }
};
