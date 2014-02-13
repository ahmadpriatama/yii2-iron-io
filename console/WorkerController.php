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
use yii\helpers\ArrayHelper;
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
	 * @var bool whether to run as worker or locally
	 */
	public $worker = true;

	/**
	 * @var int worker timeout in seconds. max 3600 seconds.
	 */
	public $workerTimeout = 3600;

	/**
	 * @var int worker priority: 0 = lowest ... 2 = highest
	 */
	public $workerPriority = 0;

	/**
	 * @var int seconds worker will be delayed before running
	 */
	public $workerDelay = 0;

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
		if (isset($params['worker']) && $params['worker'] == true) {
			// start worker
			// TODO: wait for worker - good for manual debugging
			return $this->runAsIronWorker($route, $params);
		} else if ($this->isRunningAsWorker()) {
			// run as worker on iron worker
			$iron = $this->getIron();

			// decrypt params
			$paramsDecrypted = Security::encrypt($params, $iron->payloadSecurityHash);
			$paramsDecrypted = Json::encode($paramsDecrypted);

			return parent::run($route, $paramsDecrypted);
		} else {
			// running locally
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
		$workerVars = $this->getWorkerVars();

		foreach ($params as $name => $value) {
			if (in_array($name, $workerVars)) {
				$this->$name = $params[$name];
				unset($params[$name]);
			}
		}

		// disable interactive mode
		$params['interactive'] = false;

		// prepare worker options
		$options = [
			'priority' => $this->workerPriority,
			'timeout' => $this->workerTimeout,
			'delay' => $this->workerDelay,
		];

		// prepare payload with encrypted params
		$paramsEncrypted = Json::encode($params);
		$paramsEncrypted = Security::encrypt($paramsEncrypted, $iron->payloadSecurityHash);
		$payload = [
			'route' => $route,
			'params' => $paramsEncrypted,
			//		'relativeAppPath' => basename(\Yii::$app->getBasePath()),
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
	public function actionBuildWorker()
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
	public function actionUploadWorker($build = true)
	{
		if ($build) {
			$this->actionBuildWorker();
		}

		// prepare config (based on current config?! extra iron.php / iron-local.php?!

		// post worker code
	}

	public function globalOptions()
	{
		$options = ArrayHelper::merge(parent::globalOptions(), $this->getWorkerVars());
		$options[] = 'ironComponentId';
		return $options;
	}

	protected function getWorkerVars()
	{
		return [
			'worker',
			'workerTimeout',
			'workerPriority',
			'workerDelay',
		];
	}
} 