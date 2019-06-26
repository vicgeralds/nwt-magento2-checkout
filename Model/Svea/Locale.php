<?php


namespace Svea\Checkout\Model\Svea;


class Locale
{

    /**
     * Swedish, Norway, Danish Kronor
     * @var array $allowedCurrencies
     */
    protected $allowedCurrencies = [
      "SEK","NOK","DKK"
    ];


    protected $allowedCountries = [
        "SE","NO","DK",
    ];

    public function getCountryIdByIso3Code($iso3)
    {
        foreach ($this->allowedShippingCountries as $key => $countryId)
        {
            if ($key === $iso3) {
                return $countryId;
            }
        }

        // we return it back if we cant find anything... We should throw an exception in the future!
        return $iso3;
    }


    /**
     * @return array
     */
    public function getAllowedCurrencies()
    {
        return $this->allowedCurrencies;
    }

    /**
     * @return array
     */
    public function getAllowedShippingCountries($code = null)
    {
        if ($code === "iso2") {
            return array_values($this->allowedShippingCountries);
        } else if ($code === "iso3") {
            return array_keys($this->allowedShippingCountries);
        }

        return $this->allowedShippingCountries;
    }

    /**
     * @return array
     */
    public function getAllowedCountries()
    {
        return $this->allowedCountries;
    }

}