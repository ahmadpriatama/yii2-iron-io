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
use yii\helpers\Json;

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

	/**
	 * @return array
	 */
	public static function getArgs()
	{
		global $argv;
		static $args;

		if (!defined(YII_IRON_ENV) || YII_IRON_ENV == false) {
			throw new \RuntimeException('Not running as iron worker.');
		}
		if (!isset($args)) {

			$args = ['task_id' => null, 'dir' => null, 'payload' => [], 'config' => null];

			foreach ($argv as $k => $v) {
				if (empty($argv[$k + 1])) {
					continue;
				}

				if ($v == '-id') {
					$args['task_id'] = $argv[$k + 1];
				}
				if ($v == '-d') {
					$args['dir'] = $argv[$k + 1];
				}

				if ($v == '-payload' && file_exists($argv[$k + 1])) {
					$args['payload'] = file_get_contents($argv[$k + 1]);

					$parsed_payload = Json::decode($args['payload']); // decode as array

					if ($parsed_payload != null) {
						$args['payload'] = $parsed_payload;
					}
				}

				if ($v == '-config' && file_exists($argv[$k + 1])) {
					$args['config'] = file_get_contents($argv[$k + 1]);

					$parsed_config = Json::decode($args['config']);

					if ($parsed_config != null) {
						$args['config'] = $parsed_config;
					}
				}
			}
		}

		return $args;
	}

	/**
	 * @return string|null
	 */
	public static function getTaskId()
	{

		$args = self::getArgs();
		return $args['task_Id'];
	}

	/**
	 * @return array|null
	 */
	public static function getConfig()
	{
		$args = self::getArgs();
		return $args['config'];
	}

	/**
	 * @return array
	 */
	public static function getPayload()
	{
		$args = self::getArgs();
		return $args['payload'];
	}

	/**
	 * @return string
	 */
	public static function getRoute()
	{
		$payload = self::getPayload();
		return $payload['route'];
	}

	/**
	 * @return array
	 */
	public static function getParams()
	{
		$payload = self::getPayload();
		return $payload['params'];
	}

	/**
	 * @return string
	 */
	public static function getDir()
	{
		$args = self::getArgs();
		return $args['dir'];
	}
}