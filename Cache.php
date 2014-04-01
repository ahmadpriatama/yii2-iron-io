<?php
/**
 * Cache.php file.
 *
 * @author Dirk Adler <adler@spacedealer.de>
 * @link http://www.spacedealer.de
 * @copyright Copyright &copy; 2014 spacedealer GmbH
 */


namespace spacedealer\iron;

use yii\di\Instance;

/**
 * Class Cache
 *
 * TODO: php comments
 * TODO: unit tests
 *
 * @package spacedealer\iron
 */
class Cache extends \yii\caching\Cache
{

    /**
     * @var string|\spacedealer\iron\Iron ID of the iron component.
     */
    public $iron = 'iron';

    /**
     * @var string Cache name. Default value is 'iron'.
     */
    public $name = 'iron';

    /**
     * @var \IronCache
     */
    private $_cache;

    public function init()
    {
        parent::init();

        // init iron component
        if (is_string($this->iron)) {
            $this->iron = Instance::ensure($this->iron, Iron::className());
        }
        $this->_cache = $this->iron->getCache();
    }


    /**
     * Retrieves a value from cache with a specified key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key a unique key identifying the cached value
     * @return string|boolean the value stored in cache, false if the value is not in the cache or expired.
     */
    protected function getValue($key)
    {

        try {
            $item = $this->_cache->getItem($this->name, $key);
        } catch (\Http_Exception $e) {
            \Yii::error($e->getMessage(), 'spacedealer.iron');
            $item = false;
        } catch (\JSON_Exception $e) {
            \Yii::error($e->getMessage(), 'spacedealer.iron');
            $item = false;
        }

        if ($item != null && $item->value != null) {
            return $item->value;
        } else {
            return false;
        }
    }

    /**
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached
     * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function setValue($key, $value, $expire)
    {
        try {
            $this->_cache->putItem($this->name, $key, [
                "value" => $value,
                'expires_in' => $expire,
                "replace" => true,
            ]);
            $success = true;
        } catch (\Http_Exception $e) {
            \Yii::error($e->getMessage(), 'spacedealer.iron');
            $success = false;
        } catch (\JSON_Exception $e) {
            \Yii::error($e->getMessage(), 'spacedealer.iron');
            $success = false;
        }

        return $success;
    }

    /**
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached
     * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function addValue($key, $value, $expire)
    {
        return $this->setValue($key, $value, $expire);
    }

    /**
     * Deletes a value with the specified key from cache
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key of the value to be deleted
     * @return boolean if no error happens during deletion
     */
    protected function deleteValue($key)
    {
        try {
            $this->_cache->deleteItem($this->name, $key);
            $success = true;
        } catch (\Http_Exception $e) {
            \Yii::error($e->getMessage(), 'spacedealer.iron');
            $success = false;
        } catch (\JSON_Exception $e) {
            \Yii::error($e->getMessage(), 'spacedealer.iron');
            $success = false;
        }

        return $success;
    }

    /**
     * Deletes all values from cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @return boolean whether the flush operation was successful.
     */
    protected function flushValues()
    {
        try {
            $this->_cache->clear($this->name);
            $success = true;
        } catch (\Http_Exception $e) {
            \Yii::error($e->getMessage(), 'spacedealer.iron');
            $success = false;
        } catch (\JSON_Exception $e) {
            \Yii::error($e->getMessage(), 'spacedealer.iron');
            $success = false;
        }

        return $success;
    }
} 