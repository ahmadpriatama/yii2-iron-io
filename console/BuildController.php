<?php
/**
 * BuildController.php file.
 *
 * @author Dirk Adler <adler@spacedealer.de>
 * @link http://www.spacedealer.de
 * @copyright Copyright &copy; 2014 spacedealer GmbH
 */

namespace spacedealer\iron\console;

use spacedealer\iron\Iron;
use yii\di\Instance;
use yii\helpers\VarDumper;

/**
 * Class BuildController
 *
 * @package spacedealer\iron\console
 */
class BuildController extends \yii\console\Controller
{
    /**
     * @var string|\spacedealer\iron\Iron Iron component ID. you can change this on cli to use a different configuration setting
     */
    public $iron = 'iron';

    /**
     * @inheritdoc
     */
    public function init()
    {
        // init iron component
        $this->iron = Instance::ensure($this->iron, Iron::className());
    }

    public function actionList()
    {
        // list workers
        echo "\nList of worker apps:\n\n";
        foreach ($this->iron->workerConfig as $name => $config) {
            echo "- $name\n";
        }
        echo "\n";
    }

    public function actionInfo($name)
    {
        // show worker config
        if (!isset($this->iron->workerConfig[$name])) {
            echo "Unkwown worker app '$name'. Can not find worker config.\n\n";
            $this->actionList();
        } else {
            echo "Configuration for worker app '$name':'\n\n";
            VarDumper::dump($this->iron->workerConfig[$name]);
            echo "\n\n";
        }
    }

    /**
     * @param string $name
     */
    public function actionBuildWorker($name)
    {
        $this->iron->buildWorker($name);
    }

    /**
     * @param string $name
     * @param bool $build
     */
    public function actionUploadWorker($name, $build = true)
    {
        $this->iron->uploadWorker($name, $build);
    }

//	public function actionCreateApp($name) {
//		// TODO: copy worker app template
//	}
} 