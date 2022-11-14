require([
    'Magento_Customer/js/customer-data'
], function (customerData) {
    const sections = ['cart'];
    customerData.reload(sections, true);
});