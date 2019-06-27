<?php

namespace Svea\Checkout\Model;

/**
 * Svea Push model
 */

class Push extends \Magento\Framework\Model\AbstractModel
{


    /**
     * Define resource model
     */
     
    public function _construct() {
        
        $this->_init('Svea\Checkout\Model\Resource\Push');
    }


    public function loadBySid($sID,$test) {
        $pKey = $sID.'|'.($test>0?1:0);
        return $this->load($pKey,'sid');
    }

    static public function getRequest($sID,$test,$origin) {

        $pKey = $sID.'|'.($test>0?1:0);
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        // load push
        $push = $objectManager->create('Svea\Checkout\Model\Push')
		    ->load($pKey,'sid')
		    ->setIsAlreadyStarted(true);


        if(!$push->getId()) {
            try {
                $currentTime =  (new \DateTime())->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT);
                $push->setSid($pKey)->setOrigin($origin)->setCreatedAt($currentTime)->save();
                $push->setIsAlreadyStarted(false);
            } catch(\Exception $e) {

                //duplicate key? try to reload
                $push->load($pKey,'sid');
                if(!$push->getId()) {
                    //nope, no duplicate key, cant do anything
                    throw $e;
                }
            }
        } else {
            $push->setIsAlreadyPlaced(true);
        }

        return $push;
    }

    public function getAge() {
        $now  = time();
        $rup  = strtotime($this->getCreatedAt());
        $age  = round( ($now-$rup)/60, 2); //minutes
        return $age;
    }


}
