<?php
/**
 * Iron.php file.
 *
 * @author Dirk Adler <adler@spacedealer.de>
 * @link http://www.spacedealer.de
 * @copyright Copyright &copy; 2008-2014 spacedealer GmbH
 */

namespace spacedealer\iron;

use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;

class Iron extends Component
{
	/**
	 * @var
	 */
	public $token;

	/**
	 * @var
	 */
	public $projectId;

	/**
	 * @var
	 */
	public $payloadSecurityHash;

	/**
	 * @var array
	 */
	public $workerFoldersAndFiles = [
		'except' => ['.git', '.csv', '.svn', '.zip', "/runtime", "/config"],
	];

	/**
	 * @var array
	 */
	public $workerConfig = [
		'worker' => [
			'appPath' => '',
			'tmpPath' => '',
			'templatePath' => '',
			'filesOptions' => [],
		]
	];

	/**
	 * @var \IronCache
	 */
	private $_cache;

	/**
	 * @var \IronMQ
	 */
	private $_mq;

	/**
	 * @var \IronWorker
	 */
	private $_worker;


	/**
	 * @throws \yii\base\InvalidConfigException
	 */
	public function init()
	{
		// test config
		if (!isset($this->token)) {
			throw new InvalidConfigException('spacedealer\iron\Iron token not set in config.');
		}
		if (!isset($this->projectId)) {
			throw new InvalidConfigException('spacedealer\iron\Iron projectId not set in config.');
		}
		if (!isset($this->payloadSecurityHash)) {
			throw new InvalidConfigException('spacedealer\iron\Iron payloadSecurityHash not set in config.');
		}
	}

	/**
	 * @return \IronCache
	 */
	public function getCache()
	{
		return $this->_cache;
	}

	/**
	 * @return \IronMQ
	 */
	public function getMQ()
	{
		if (!isset($this->_mq)) {
			try {
				$this->_mq = new \IronMQ([
					'token' => $this->token,
					'projectId' => $this->projectId,
				]);
			} catch (\Exception $e) {
				\Yii::error($e->getMessage(), 'spacedealer.iron');
				throw new Exception('Error in IronMQ: ' . $e->getMessage());
			}
		}
		return $this->_mq;
	}

	/**
	 * @return \IronWorker
	 */
	public function getWorker()
	{
		try {
			$this->_worker = new \IronWorker([
				'token' => $this->token,
				'projectId' => $this->projectId,
			]);
		} catch (\Exception $e) {
			\Yii::error($e->getMessage(), 'spacedealer.iron');
			throw new Exception('Error in IronWorker: ' . $e->getMessage());
		}
		return $this->_worker;
	}

	public function runWorker($route, $params)
	{
		// tbd
	}
}