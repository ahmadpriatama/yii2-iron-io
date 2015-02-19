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
use yii\base\Security;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;


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
     * @var string Name of cluster the worker task should run on. "default", "high-mem" or "dedicated".
     */
    public $workerCluster = 'default';

    /**
     * @var string|null Optional text label for your task.
     */
    public $workerLabel = null;

    /**
     * @var string|\spacedealer\iron\Iron Iron component ID. You may change this to use different configuration settings.
     */
    public $iron = 'iron';

    /**
     * @var string|\yii\base\Security Security component ID. You may change this to use different configuration settings.
     */
    public $security = 'security';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        // init security component
        $this->security = Instance::ensure($this->security, Security::className());

        // init iron component
        $this->iron = Instance::ensure($this->iron, Iron::className());
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
            // call worker
            // TODO: wait for worker - for manual debugging
            $route = $this->getRoute() . '/' . $id;
            return $this->runAsIronWorker($route, $params);
        } else if ($this->isRunningAsWorker()) {
            // run as worker on iron worker
            // decrypt params first
            if (is_string($params)) {
                $params = $this->decryptParams($params);
            }
            if (empty($params)) {
                $params = [];
            }
            return parent::runAction($id, $params);
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
            'cluster' => $this->workerCluster,
            'label' => $this->workerLabel,
        ];

        // prepare payload with encrypted params
        $paramsEncrypted = $this->encryptParams($params);

        $payload = [
            'route' => $route,
            'params' => $paramsEncrypted,
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
        $options[] = 'security';
        return $options;
    }

    protected function getWorkerOptions()
    {
        return [
            'worker',
            'workerTimeout',
            'workerPriority',
            'workerDelay',
            'workerLabel',
            'workerCluster',
        ];
    }

    /**
     * Encrypt payload parameters
     *
     * @param array $params
     * @return string
     */
    protected function encryptParams($params)
    {
        $paramsEncrypted = Json::encode($params);
        $paramsEncrypted = $this->security->encryptByPassword($paramsEncrypted, $this->iron->workerPayloadPassword);
        return base64_encode($paramsEncrypted);
    }

    /**
     * Decrypt payload parameters
     *
     * @param string $params
     * @return array
     */
    protected function decryptParams($params)
    {
        $paramsDecrypted = base64_decode($params);
        $paramsDecrypted = $this->security->decryptByPassword($paramsDecrypted, $this->iron->workerPayloadPassword);
        return Json::decode($paramsDecrypted);
    }
} 
