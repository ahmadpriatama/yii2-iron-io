<?php
/**
 * BuildController.php file.
 *
 * @author Dirk Adler <adler@spacedealer.de>
 * @link http://www.spacedealer.de
 * @copyright Copyright &copy; 2008-2014 spacedealer GmbH
 */

namespace spacedealer\iron\console;
use yii\helpers\Json;
use yii\helpers\VarDumper;

/**
 * Class BuildController
 *
 * @package spacedealer\iron\console
 */
class BuildController extends \yii\console\Controller
{
	public $ironComponentId = 'iron';

	/**
	 * @return null|\spacedealer\iron\Iron
	 */
	protected function getIron()
	{
		return \Yii::$app->getComponent($this->ironComponentId);
	}

	public function actionList()
	{
		// list workers
		$iron = $this->getIron();

		echo "\nList of worker apps:\n\n";
		foreach ($iron->workerConfig as $name => $config) {
			echo "- $name\n";
		}
		echo "\n";
	}

	public function actionInfo($name)
	{
		// show worker config
		$iron = $this->getIron();

		if (!isset($iron->workerConfig[$name])) {
			echo "Unkwown worker app '$name'. Can not find worker config.\n\n";
			$this->actionList();
		}
		else {
			echo "Configuration for worker app '$name':'\n\n";
			VarDumper::dump($iron->workerConfig[$name]);
			echo "\n\n";
		}
	}

	public function actionBuildWorker($name)
	{
		$iron = $this->getIron();
		$iron->buildWorker($name);
	}

	public function actionUploadWorker($name, $build = true)
	{
		$iron = $this->getIron();
		$iron->uploadWorker($name, $build);

	}

//	public function actionCreateApp($name) {
//		// TODO: copy worker app template
//	}
} 