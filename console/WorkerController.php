<?php
/**
 * WorkerController.php file.
 *
 * @author Dirk Adler <adler@spacedealer.de>
 * @link http://www.spacedealer.de
 * @copyright Copyright &copy; 2008-2014 spacedealer GmbH
 */

namespace spacedealer\iron\console;

use spacedealer\iron\Iron;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
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
	 * @return string name of the yii2 worker app
	 */
	public function getWorkerName()
	{
		return 'worker';
	}

	/**
	 * @param string $route
	 * @param array $params
	 */
	public function runAction($id, $params = [])
	{
		if (isset($params['worker']) && $params['worker'] == true) {
			// start worker
			// TODO: wait for worker - good for manual debugging
			$route = $this->getRoute() . '/' . $id;
			return $this->runAsIronWorker($route, $params);
		} else if ($this->isRunningAsWorker()) {
			// run as worker on iron worker
			$iron = $this->getIron();

			// decrypt params
			$paramsDecrypted = base64_decode($params);
			$paramsDecrypted = Security::encrypt($paramsDecrypted, $iron->workerPayloadPassword);
			$paramsDecrypted = Json::encode($paramsDecrypted);

			return parent::runAction($id, $paramsDecrypted);
		} else {
			// running locally
			return parent::runAction($id, $params);
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
			if (!is_int($name) && in_array($name, $workerVars)) {
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
		$paramsEncrypted = Security::encrypt($paramsEncrypted, $iron->workerPayloadPassword);
		$paramsEncrypted = base64_encode($paramsEncrypted);

		$payload = [
			'route' => $route,
			'params' => $paramsEncrypted,
			//		'relativeAppPath' => basename(\Yii::$app->getBasePath()),
		];

		// run worker
		$worker = $iron->getWorker();
		try {
			$res = $worker->postTask($this->getWorkerName(), $payload, $options);
		} catch (\Exception $e) {
			\Yii::error($e->getMessage(), 'spacedealer.iron');
			$res = false;
		}

		// return worker task id
		return $res;
	}

	/**
	 * @return bool Wheter running as iron worker or not
	 */
	protected function isRunningAsWorker()
	{
		return Iron::runningAsIronWorker();
	}

	/**
	 * @return null|\spacedealer\iron\Iron
	 */
	protected function getIron()
	{
		return \Yii::$app->getComponent($this->ironComponentId);
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