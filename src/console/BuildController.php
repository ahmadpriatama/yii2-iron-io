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
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\Json;
use yii\helpers\VarDumper;

/**
 * Class BuildController
 *
 * @package spacedealer\iron\console
 */
class BuildController extends \yii\console\Controller
{
    /**
     * @event Event ist triggered during build process before cleanup of build directory.
     */
    const EVENT_BUILD_BEFORE_CLEANUP = 'build-before-cleanup';

    /**
     * @event Event ist triggered during build process before files and folders are copied to build directory.
     */
    const EVENT_BUILD_BEFORE_COPY = 'build-before-copy';

    /**
     * @event Event ist triggered during build process before build directory is zipped.
     */
    const EVENT_BUILD_BEFORE_ZIP = 'build-before-zip';

    /**
     * @var string|array|\spacedealer\iron\Iron Iron component ID. you can change this on cli to use a different configuration setting
     */
    public $iron = 'iron';

    /**
     * @var string
     */
    public $defaultAction = 'list';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

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
    public function actionBuild($name)
    {
        $this->build($name);
    }

    /**
     * @param string $name
     * @throws \yii\base\InvalidConfigException
     */
    protected function build($name)
    {
        $config = $this->iron->getConfigForWorker($name);

        $this->stdout("\nBuilding worker '$name'\n", Console::FG_BLUE);

        // prepare build folder (create, cleanup if it already exists)
        $buildPath = \Yii::getAlias($this->iron->workerBuildPath . DIRECTORY_SEPARATOR . $name);
        \Yii::setAlias('@ironworkerBuildPath', $buildPath);

        if (!file_exists($buildPath)) {
            $this->stdout("\nCreate build directory:\n", Console::FG_BLUE);
            $this->stdout("\n  - $buildPath\n");

            // create new empty build directory
            FileHelper::createDirectory($buildPath);
        }

        $this->trigger(self::EVENT_BUILD_BEFORE_COPY);

        // copy directories
        $this->stdout("\nCopy directories:\n", Console::FG_BLUE);

        // prepare destination directory index
        $destinationDirs = [];

        foreach ($config['directories'] as $dir => $dirConfig) {

            // resolve source path - src supports aliases


            $options = isset($dirConfig['options']) ? $dirConfig['options'] : null;

            // copy directory
            if (isset($dirConfig['mode']) && isset($dirConfig['mode']) == 'composer') {

                if (!isset($dirConfig['source'])) {
                    $dirConfig['source'] = getenv('COMPOSER_VENDOR_DIR') ?: 'vendor';
                }
                if (!isset($dirConfig['destination'])) {
                    $dirConfig['destination'] = getenv('COMPOSER_VENDOR_DIR') ?: 'vendor';
                }
                if (!isset($dirConfig['update'])) {
                    $dirConfig['update'] = false;
                }
                $src = \Yii::getAlias($dirConfig['source']);
                $dst = $buildPath . DIRECTORY_SEPARATOR . $dirConfig['destination'];

                // install composer requirements
                // copy composer.json
                $composerFile = dirname($src) . DIRECTORY_SEPARATOR . 'composer.json';
                copy($composerFile, $buildPath . DIRECTORY_SEPARATOR . 'composer.json');

                // prepare command
                $commandOptions = [
                    '--working-dir=' . escapeshellarg($buildPath),
                ];
                // fall back to composer options in config
                if (empty($options)) {
                    $options = $this->iron->composerOptions;
                }
                foreach ($options as $key => $val) {
                    if (is_int($key)) {
                        $commandOptions[] = $val;
                    } else {
                        $commandOptions[] = $key . '=' . escapeshellarg($val);
                    }
                }
                $commandOptions = array_unique($commandOptions);
                $commandMode = $dirConfig['update'] ? 'update' : 'install';
                $command = $this->iron->composerBin . ' ' . $commandMode . ' ' . implode(' ', $commandOptions);

                // run command
                $this->stdout("\n  Composer: $command\n  - $src\n  > $dst\n\n");
                passthru($command);
            } else {

                if (!isset($dirConfig['source'])) {
                    $dirConfig['source'] = $dir;
                }
                if (!isset($dirConfig['destination'])) {
                    $dirConfig['destination'] = $dir;
                }
                $src = \Yii::getAlias($dirConfig['source']);

                // destination path - always relative to build path
                $dst = $buildPath . DIRECTORY_SEPARATOR . $dirConfig['destination'];

                if (!isset($options['basePath'])) {
                    $options['basePath'] = realpath($src);
                }

                // copy files
                $this->stdout("\n  File copy:\n  - $src\n  > $dst\n");
                FileHelper::copyDirectory($src, $dst, $options);
            }

            $destinationDirs[$dirConfig['destination']] = true;
        }

        $this->trigger(self::EVENT_BUILD_BEFORE_CLEANUP);

        // cleanup - get top dirs > remove if not in config > destination
        $this->stdout("\nCleanup build directory:\n", Console::FG_BLUE);
        $found = false;
        if ($handle = opendir($buildPath)) {
            while (false !== ($file = readdir($handle))) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                $path = $buildPath . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path) && !isset($destinationDirs[$file])) {
                    $this->stdout("\n  Remove directory:\n  - $path\n");
                    FileHelper::removeDirectory($path);
                    $found = true;
                }
            }
            closedir($handle);
        }
        if (!$found) {
            $this->stdout("\n  Nothing found to cleanup.\n");
        }

        $this->trigger(self::EVENT_BUILD_BEFORE_ZIP);

        // zip all
        $this->zip($name);

        $this->stdout("\n  Building done.\n\n", Console::FG_BLUE);
    }

    /**
     * (Re-)zips worker zip file.
     *
     * @param string $name
     * @throws \yii\base\InvalidConfigException
     */
    public function actionZip($name)
    {
        $this->zip($name);
    }

    /**
     * @param string $name
     */
    protected function zip($name)
    {
        // test config first
        $this->iron->getConfigForWorker($name);

        $buildPath = \Yii::getAlias($this->iron->workerBuildPath . DIRECTORY_SEPARATOR . $name);
        $zipFile = $buildPath . '.zip';

        $this->stdout("\nZipping worker '$name'\n", Console::FG_BLUE);
        $this->stdout("\n  - $buildPath\n  > " . $zipFile . "\n");

        \IronWorker::zipDirectory($buildPath, $zipFile, true);

        $this->stdout("\n  Size: " . sprintf("%.2f", filesize($zipFile) / pow(1024, 2)) . "M\n");

        $this->stdout("\n  Zipping done.\n\n", Console::FG_BLUE);
    }

    /**
     * Uploads worker zip file.
     *
     * @param string $name
     */
    public function actionUpload($name)
    {
        $this->upload($name);
    }

    /**
     * (Re-)zips and uploads worker zip file.
     *
     * @param string $name
     */
    public function actionZipUpload($name)
    {
        $this->zip($name);
        $this->upload($name);
    }

    /**
     * (Re-)builds, zips and uploads worker zip file.
     *
     * @param string $name
     */
    public function actionBuildUpload($name)
    {
        $this->build($name);
        $this->upload($name);
    }

    /**
     * @param string $name Worker name
     * @return array
     */
    protected function upload($name)
    {
        $this->stdout("\nUploading worker '$name'\n", Console::FG_BLUE);

        $config = $this->iron->getConfigForWorker($name);
        $worker = $this->iron->getWorker();
        $appSrcPath = \Yii::getAlias($config['directories']['app']['source']);
        $appPathDst = \Yii::getAlias($config['directories']['app']['destination']);
        $buildPath = \Yii::getAlias($this->iron->workerBuildPath . DIRECTORY_SEPARATOR . $name);

        // prepare worker app bootstrap file path - relative to worker path
        $bootstrapFile = $appPathDst . DIRECTORY_SEPARATOR . 'run.php';

        // prepare zip file path
        $zipFile = $buildPath . '.zip';

        // prepare yii2 worker app config (used when running app as iron worker)
        $appConfigFile = $appSrcPath . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "main.php";
        $appConfig = require($appConfigFile);

        // remove directories from config
        unset($config['directories']);
        $config['config'] = Json::encode($appConfig);

        // get stack - minimum required stack is php 5.4
        $config['stack'] = isset($config['stack']) ? $config['stack'] : 'php-5.4';

        // add environment var
        if (!isset($config['set_env'])) {
            $config['set_env'] = [];
        }
        if (!isset($config['set_env']['YII_ON_IRON'])) {
            $config['set_env']['YII_ON_IRON'] = true;
        }

        // push and deploy worker code
        $res = $worker->postCode($bootstrapFile, $zipFile, $name, $config);

        $this->stdout("\n  Uploading done.\n\n", Console::FG_BLUE);

        return $res;
    }

//	public function actionCreateApp($name) {
//		// TODO: copy worker app template
//	}
} 
