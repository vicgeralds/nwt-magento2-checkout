<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Shipping\Address;

/**
 * Svea shipping location address config
 */
class Config
{
    const DEFAULT_FORMAT = '{{var street}}
    {{var city}} {{depend region}}, {{var region}} {{/depend}} {{var postcode}}, {{var countryId}}';
}
