<?php
namespace Svea\Checkout\Logger\Handler;

use Svea\Checkout\Logger\Logger;

class Error extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::ERROR;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/svea_checkout_error.log';
}
