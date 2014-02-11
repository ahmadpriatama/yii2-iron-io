<?php
/**
 * Controller.php file.
 *
 * @author Dirk Adler <adler@spacedealer.de>
 * @link http://www.spacedealer.de
 * @copyright Copyright &copy; 2008-2014 spacedealer GmbH
 */


namespace spacedealer\iron\console;


/**
 * Class Controller
 *
 * @package spacedealer\iron\console
 */
class Controller extends \yii\console\Controller
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

		// run as worker
		/** @var \spacedealer\iron\Iron $iron */
		$iron = \Yii::$app->getComponent($this->ironComponentId);
		$worker = $iron->getWorker();

		$payload = [
			'params' => $params,
			'relativeAppPath' => basename(\Yii::$app->getBasePath()),
		];

		$options = [
			'priority' => $this->ironWorkerPriority,
			'timeout' => $this->ironWorkerTimeout,
			'delay' => $this->ironWorkerDelay,
		];

		try {
			$res = $worker->postTask($this->getUniqueId(), $payload, $options);
		} catch (\Exception $e) {
			\Yii::error($e->getMessage(), 'spacedealer.iron');
			$res = false;
		}

		return $res;
	}

	/**
	 *
	 */
	public function actionBuildIronWorker()
	{

	}

	/**
	 *
	 */
	public function actionUploadIronWorker()
	{

	}
} 