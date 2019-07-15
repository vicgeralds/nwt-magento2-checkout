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

    public function _construct()
    {
        $this->_init('Svea\Checkout\Model\Resource\Push');
    }


    public static function pushExists($sID, $test)
    {
        $pKey = $sID . '|' . ($test>0 ? 1 : 0);
        $push = self::getObjectManager()->create('Svea\Checkout\Model\Push')->load($pKey, 'sid');

        return $push->getId() !== null;
    }

    public static function savePush($sID, $test, $origin)
    {
        $pKey = $sID . '|' . ($test>0 ? 1 : 0);
        $currentTime =  (new \DateTime())->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT);
        $push = self::getObjectManager()->create('Svea\Checkout\Model\Push');
        $push->setSid($pKey)->setOrigin($origin)->setCreatedAt($currentTime)->save();
        $push->setIsAlreadyStarted(false);
        return $push;
    }


    private static function getObjectManager()
    {
        return \Magento\Framework\App\ObjectManager::getInstance();
    }
}
