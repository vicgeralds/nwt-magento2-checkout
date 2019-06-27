<?php

namespace Svea\Checkout\Model\Resource;


/**
 * Svea Push resource model
 */
class Push extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Define main table
     */
     
    public function _construct() {
        $this->_init('svea_push', 'entity_id');
    }

}
