<?php
/**
 * WorkerController.php file.
 *
 * @author Dirk Adler <adler@spacedealer.de>
 * @link http://www.spacedealer.de
 * @copyright Copyright &copy; 2014 spacedealer GmbH
 */

namespace spacedealer\iron\console;

use spacedealer\iron\Iron;
use yii\di\Instance;
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
     * @var string|\spacedealer\iron\Iron Iron component ID. you can change this on cli to use a different configuration setting
     */
    public $iron = 'iron';

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        // init iron component
        if (is_string($this->iron)) {
            $this->iron = Instance::ensure($this->iron, Iron::className());
        }

        return parent::beforeAction($action);
    }

    /**
     * @return string name of the yii2 worker app
     */
    public function getWorkerName()
    {
        return 'worker';
    }

    /**
     * @param string $id
     * @param array $params
     * @internal param string $route
     * @return int|mixed
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
            // decrypt params
            $paramsDecrypted = base64_decode($params);
            $paramsDecrypted = Security::encrypt($paramsDecrypted, $this->iron->workerPayloadPassword);
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
        // overwrite global iron worker values first with given param values and remove them from set
        $workerOptions = $this->getWorkerOptions();

        foreach ($params as $name => $value) {
            if (!is_int($name) && in_array($name, $workerOptions)) {
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
        $paramsEncrypted = Security::encrypt($paramsEncrypted, $this->iron->workerPayloadPassword);
        $paramsEncrypted = base64_encode($paramsEncrypted);

        $payload = [
            'route' => $route,
            'params' => $paramsEncrypted,
            //		'relativeAppPath' => basename(\Yii::$app->getBasePath()),
        ];

        // run worker
        $worker = $this->iron->getWorker();
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

    public function options($actionId)
    {
        $options = ArrayHelper::merge(parent::options($actionId), $this->getWorkerOptions());
        $options[] = 'iron';
        return $options;
    }

    protected function getWorkerOptions()
    {
        return [
            'worker',
            'workerTimeout',
            'workerPriority',
            'workerDelay',
        ];
    }
} 