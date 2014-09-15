<?php
/**
 * Iron.php file.
 *
 * @author Dirk Adler <adler@spacedealer.de>
 * @link http://www.spacedealer.de
 * @copyright Copyright &copy; 2014 spacedealer GmbH
 */

namespace spacedealer\iron;

use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\helpers\Json;

class Iron extends Component
{
    const SERVICE_CACHE = 'cache';
    const SERVICE_MQ = 'mq';
    const SERVICE_WORKER = 'worker';

    /**
     * @var array Supported iron services
     */
    public static $services = array(
        self::SERVICE_CACHE,
        self::SERVICE_MQ,
        self::SERVICE_WORKER,
    );
    /**
     * @var string|array
     */
    public $token;

    /**
     * @var string|array
     */
    public $projectId;

    /**
     * @var
     */
    public $workerPayloadPassword;

    /**
     * @var
     */
    public $workerBuildPath = '@runtime/ironworker';

    /**
     * @var array
     */
    public $workerConfig;

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


        // use default worker config if none is configured
        if (empty($this->workerConfig)) {
            $this->workerConfig = $this->getDefaultWorkerConfig();
        }
    }

    public function getToken($service)
    {
        if (!in_array($service, self::$services)) {
            throw new InvalidConfigException("spacedealer\iron\Iron '$service' is not supported.");
        }
        if (is_array($this->token)) {
            if (!isset($this->token[$service])) {
                throw new InvalidConfigException("spacedealer\iron\Iron token for service '$service' not set in config.");
            }
            $token = $this->token[$service];
        } else {
            $token = $this->token;
        }
        return $token;
    }

    public function getProjectId($service)
    {
        if (!in_array($service, self::$services)) {
            throw new InvalidConfigException("spacedealer\iron\Iron '$service' is not supported.");
        }

        if (is_array($this->projectId)) {
            if (!isset($this->projectId[$service])) {
                throw new InvalidConfigException("spacedealer\iron\Iron projectId for service '$service' not set in config.");
            }
            $projectId = $this->projectId[$service];
        } else {
            $projectId = $this->projectId;
        }
        return $projectId;
    }

    public function getDefaultWorkerConfig()
    {
        return [
            self::SERVICE_WORKER => [
                'app' => [
                    'source' => '@ironWorkerApp',
                    'destination' => 'app',
                    'options' => [
                        'except' => [
                            '/config',
                            '/runtime',
                            '.git',
                            '.csv',
                            '.svn',
                            '.zip',
                        ]
                    ],
                ],
                'vendor' => [
                    'source' => '@vendor',
                    'destination' => 'vendor',
                    'options' => [
                        'except' => [
                            '/config',
                            '/runtime',
                            '.git',
                            '.csv',
                            '.svn',
                            '.zip',
                        ]
                    ],
                ],
            ],
        ];
    }

    /**
     * @return \IronCache
     */
    public function getCache()
    {
        if (!isset($this->_cache)) {
            // init cache
            try {
                $this->_cache = new \IronCache([
                    'token' => $this->getToken(self::SERVICE_CACHE),
                    'project_id' => $this->getProjectId(self::SERVICE_CACHE),
                ]);
            } catch (\Exception $e) {
                \Yii::error($e->getMessage(), 'spacedealer.iron');
                throw new Exception('Error in IronCache: ' . $e->getMessage(), 0, $e);
            }
        }
        return $this->_cache;
    }

    /**
     * @return \IronMQ
     */
    public function getMQ()
    {
        if (!isset($this->_mq)) {
            // init mq
            try {
                $this->_mq = new \IronMQ([
                    'token' => $this->getToken(self::SERVICE_MQ),
                    'project_id' => $this->getProjectId(self::SERVICE_MQ),
                ]);
            } catch (\Exception $e) {
                \Yii::error($e->getMessage(), 'spacedealer.iron');
                throw new Exception('Error in IronMQ: ' . $e->getMessage(), 0, $e);
            }
        }
        return $this->_mq;
    }

    /**
     * @return \IronWorker
     */
    public function getWorker()
    {
        if (!isset($this->_worker)) {
            // test worker config & init worker
            if (!isset($this->workerPayloadPassword)) {
                throw new InvalidConfigException('spacedealer\iron\Iron workerPayloadPassword not set in config.');
            }

            if (!isset($this->workerBuildPath)) {
                throw new InvalidConfigException('spacedealer\iron\Iron workerBuildPath not set in config.');
            }

            // init worker
            try {
                $this->_worker = new \IronWorker([
                    'token' => $this->getToken(self::SERVICE_WORKER),
                    'project_id' => $this->getProjectId(self::SERVICE_WORKER),
                ]);
            } catch (\Exception $e) {
                \Yii::error($e->getMessage(), 'spacedealer.iron');
                throw new Exception('Error in IronWorker: ' . $e->getMessage(), 0, $e);
            }
        }

        return $this->_worker;
    }

    public function runWorker($route, $params)
    {
        // tbd
    }

    public static function runningAsIronWorker()
    {
        global $argv;
        // test for argv structure and getArgs function in default bootstrap file runner.php
        return (isset($argv['-id']) && isset($argv['-d']) && isset($argv['-payload']) && function_exists('getArgs'));
    }

    /**
     * Get arguments provided by iron environment
     *
     * @return array
     */
    public static function getArgs()
    {
        global $argv;
        static $args;

        if (!self::runningAsIronWorker()) {
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
     * Get configuration for given worker.
     *
     * @param string $name Worker name
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    protected function getConfigForWorker($name)
    {
        // get worker config
        if (!isset($this->workerConfig[$name])) {
            throw new InvalidConfigException("Build configuration not found for worker $name.");
        }

        $config = $this->workerConfig[$name];

        // test for required config settings
        // worker app directory required
        if (!isset($config['app'])) {
            throw new InvalidConfigException("Parameter app is not set in build configuration for worker $name.");
        }
        // vendor directory required - yii2 framework & iron extension
        if (!isset($config['vendor'])) {
            throw new InvalidConfigException("Parameter vendor is not set in build configuration for worker $name.");
        }

        return $this->workerConfig[$name];
    }

    /**
     * Build worker
     *
     * @param $name string Name of worker app.
     * @throws \yii\base\InvalidConfigException
     * @todo git & composer dependencies?
     */
    public function buildWorker($name)
    {
        $config = $this->getConfigForWorker($name);

        // prepare build folder (create, cleanup if it already exists)
        $buildPath = \Yii::getAlias($this->workerBuildPath . DIRECTORY_SEPARATOR . $name);
        \Yii::setAlias('@ironworkerBuildPath', $buildPath);

        // remove old build directory
        FileHelper::removeDirectory($buildPath);

        // create new empty build directory
        FileHelper::createDirectory($buildPath);

        // copy directories
        foreach ($config as $dirConfig) {

            // resolve source path - src supports aliases
            $src = \Yii::getAlias($dirConfig['source']);

            // destination path - always relative to build path
            $dst = $buildPath . DIRECTORY_SEPARATOR . $dirConfig['destination'];

            $options = $dirConfig['options'];

            if (!isset($options['basePath'])) {
                $options['basePath'] = realpath($src);
            }

            \Yii::info("Copy $src > $dst");

            // copy directory
            FileHelper::copyDirectory($src, $dst, $options);
        }

        // zip all
        \IronWorker::zipDirectory($buildPath, $buildPath . DIRECTORY_SEPARATOR . 'ironworker.zip', true);
    }

    /**
     * @param string $name Worker name
     * @param bool $build Wheter to build worker before upload or not
     * @return mixed
     */
    public function uploadWorker($name, $build = true)
    {
        if ($build) {
            $this->buildWorker($name);
        }

        $config = $this->getConfigForWorker($name);
        $worker = $this->getWorker();
        $appSrcPath = \Yii::getAlias($config['app']['source']);
        $appPathDst = \Yii::getAlias($config['app']['destination']);
        $buildPath = \Yii::getAlias($this->workerBuildPath . DIRECTORY_SEPARATOR . $name);

        // prepare worker app bootstrap file path - relative to worker path
        $bootstrapFile = $appPathDst . DIRECTORY_SEPARATOR . 'run.php';

        // prepare zip file path
        $zipFile = $buildPath . DIRECTORY_SEPARATOR . "ironworker.zip";

        // prepare yii2 worker app config (used when running app as iron worker)
        $appConfigFile = $appSrcPath . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "main.php";
        $appConfig = Json::encode(require($appConfigFile));

        // push and deploy worker code
        $res = $worker->postCode($bootstrapFile, $zipFile, $name, ['config' => $appConfig]);
        return $res;
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