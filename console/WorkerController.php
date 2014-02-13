<?php
/**
 * WorkerController.php file.
 *
 * @author Dirk Adler <adler@spacedealer.de>
 * @link http://www.spacedealer.de
 * @copyright Copyright &copy; 2008-2014 spacedealer GmbH
 */

namespace spacedealer\iron\console;

use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\helpers\Security;


/**
 * Class WorkerController
 *
 * @package spacedealer\iron\console
 */
abstract class WorkerController extends \yii\console\Controller
{
	/**
	 * @var bool
	 */
	public $ironWorker = true;

	/**
	 * @var int
	 */
	public $ironWorkerTimeout = 3600;

	/**
	 * @var int
	 */
	public $ironWorkerPriority = 0;

	/**
	 * @var int
	 */
	public $ironWorkerDelay = 0;

	/**
	 * @var string
	 */
	public $ironComponentId = 'iron';

	/**
	 * @return string
	 */
	public function getIronWorkerName()
	{
		return $this->getUniqueId();
	}

	/**
	 * @param string $route
	 * @param array $params
	 */
	public function run($route, $params = [])
	{
		if (isset($params['ironWorker']) && $params['ironWorker'] == true) {
			return $this->runAsIronWorker($route, $params);

		} else {
			// run locally
			return parent::run($route, $params);
		}
	}

	/**
	 * @param $route
	 * @param $params
	 * @return mixed
	 */
	public function runAsIronWorker($route, $params)
	{
		$iron = $this->getIron();

		// overwrite global iron worker values first with given param values and remove them from set
		$ironWorkerVars = [
			'ironWorker',
			'ironWorkerTimeout',
			'ironWorkerPriority',
			'ironWorkerDelay',
		];
		foreach ($params as $name => $value) {
			if (in_array($name, $ironWorkerVars)) {
				$this->$name = $params[$name];
				unset($params[$name]);
			}
		}

		// prepare worker options
		$options = [
			'priority' => $this->ironWorkerPriority,
			'timeout' => $this->ironWorkerTimeout,
			'delay' => $this->ironWorkerDelay,
		];

		// prepare payload with encrypted params
		$paramsEncrypted = Json::encode($params);
		$paramsEncrypted = Security::encrypt($paramsEncrypted, $iron->payloadSecurityHash);
		$payload = [
			'params' => $paramsEncrypted,
			'relativeAppPath' => basename(\Yii::$app->getBasePath()),
		];

		// run worker
		$worker = $iron->getWorker();
		try {
			$res = $worker->postTask($this->getIronWorkerName(), $payload, $options);
		} catch (\Exception $e) {
			\Yii::error($e->getMessage(), 'spacedealer.iron');
			$res = false;
		}

		// return worker task id
		return $res;
	}

	protected function isRunningAsWorker()
	{
		return (defined(YII_IRON_ENV) && YII_IRON_ENV == 1);
	}

	/**
	 * @return null|\spacedealer\iron\Iron
	 */
	protected function getIron()
	{
		return \Yii::$app->getComponent($this->ironComponentId);
	}

	/**
	 *
	 */
	public function actionBuildIronWorker()
	{
		$iron = $this->getIron();
		$name = $this->getIronWorkerName();

		// get worker config
		if (!isset($iron->workerConfig[$name])) {
			throw new InvalidConfigException("Build configuration not found for worker $name.");
		}

		$config = $iron->workerConfig[$name];

		// test for required config settings
		if (!isset($config['appPath'])) {
			throw new InvalidConfigException("Parameter appPath is not set in build configuration for worker $name.");
		}

		// main focus: easy config & slim zip files

		// prepare tmp folder (create, cleanup if it already exists)
		$buildPath = $iron->buildPath . DIRECTORY_SEPARATOR . $name;

		// buildconfig? name => params like files/folder, worker tmp for bin files.. etc--?!

		// copy template dir
		if (isset($config['templatePath'])) {
			$templatePath = $config['templatePath'];
		}

		// copy app

		// copy composer dependencies

		// zip it
	}

	/**
	 *
	 */
	public function actionUploadIronWorker($build = true)
	{
		if ($build) {
			$this->actionBuildIronWorker();
		}

		// prepare config (based on current config?! extra iron.php / iron-local.php?!

		// post worker code
	}
} 