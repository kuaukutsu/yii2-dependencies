<?php

namespace kuaukutsu\dependencies;

use Yii;
use yii\helpers\Json;
use yii\helpers\Console;
use yii\console\Exception;
use yii\console\controllers\AssetController;

class DependenceController extends AssetController
{
    /**
     * @var string controller default action ID.
     */
    public $defaultAction = 'create-list';

    /**
     * @var array list of asset to be list Dependencies.
     */
    public $task = [];

    /**
     * @var array
     */
    public $dependenceManager = [];

    /**
     * @var array list resource
     */
    private $data = [];

    /**
     * Create dependencies file (json) for Grunt, Webpack.
     * @param string $configFile configuration file name.
     * @throws Exception
     */
    public function actionCreateList($configFile)
    {
        // Applies configuration from the given file to self instance.
        $this->loadConfiguration($configFile);

        if (!isset($this->dependenceManager['configPath']) || empty($this->dependenceManager['configPath'])) {
            throw new Exception("Please specify 'configPath' for the 'dependenceManager' option.");
        }

        if (!isset($this->dependenceManager['namespaceAsset']) || empty($this->dependenceManager['namespaceAsset'])) {
            throw new Exception("Please specify 'namespaceAsset' for the 'dependenceManager' option.");
        }

        foreach($this->task as $taskName => $task) {
            $this->stdout("Parse section '$taskName'\n");

            // exclude list
            $excludeList = (isset($task['exclude'])) ? (array) $task['exclude'] : [];

            $this->loadTask($task, $excludeList);
            if (count($this->bundles) > 0) {
                /** @var \yii\web\AssetBundle[] $bundles */
                $bundles = $this->loadBundles($this->bundles);
                foreach ($bundles as $bundle) {
                    $this->doParseBunble($bundle, 'js', $excludeList);
                    $this->doParseBunble($bundle, 'css', $excludeList);
                }

                $this->doWriteFile($taskName, $this->data);
            }

            $this->data = [];
            $this->bundles = [];
        }
    }

    /**
     * @param array $task
     * @param array $excludeList
     * @throws Exception
     */
    protected function loadTask(array $task, $excludeList)
    {
        if (!isset($task['src']) || empty($task['src'])) {
            throw new Exception("Please specify 'src' for the 'task' option.");
        }

        /** @var array $src */
        $src = (! is_array($task['src'])) ? [$task['src']] : $task['src'];
        foreach($src as $srcElement) {
            $path = Yii::getAlias($srcElement);
            if (is_dir($path)) {
                $this->doParsePath($path);
            } elseif (preg_match('#\\\(\w+Asset)#', $path, $nameBoundle)) {
                if (! in_array($path, $this->bundles)) {
                    $this->bundles[] = $path;
                }
            } elseif (file_exists($path)) {
                $this->doParseFile($path);
            }

            unset($srcElement);
        }

        // exclude bundle
        if (count($excludeList) > 0) {
            $this->bundles = array_diff($this->bundles, $excludeList);
        }
    }

    /**
     * @param string $path
     */
    protected function doParsePath($path)
    {
        foreach(glob($path . '/*.php') as $filename) {
            $this->doParseFile($filename);
        }
    }

    /**
     * @param string $filename
     * @throws Exception
     */
    protected function doParseFile($filename)
    {
        $basename = basename($filename);

        if (strrpos(basename($filename),'Asset.php')) {
            $nameBundle = $this->dependenceManager['namespaceAsset'] . substr($basename, 0, -4);
            if (! in_array($nameBundle, $this->bundles)) {
                $this->bundles[] = $nameBundle;
            }
        } elseif (strrpos(basename($filename),'Controller.php')) {
            $this->doParseControllerFile($filename);
        } else {
            $this->doParseViewFile($filename);
        }
    }

    /**
     * @param string $filename
     * @throws Exception
     */
    protected function doParseControllerFile($filename)
    {
        if (! file_exists($filename)) {
            throw new Exception("No such file or directory ($filename)");
        }

        $controllerId = strtolower(str_replace('Controller.php', '', basename($filename)));

        if ($handle = fopen($filename, "r")) {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('#render\((.*?)(\)|,)#', $line, $display)) {
                    if (strlen($display[1]) > 2) {
                        $fileViewPath = Yii::$app->getViewPath() . DIRECTORY_SEPARATOR
                            . $controllerId . DIRECTORY_SEPARATOR
                            . substr(trim($display[1]), 1, -1) . ".php";

                        $this->doParseViewFile($fileViewPath);
                    }
                }
            }
            fclose($handle);
        }
    }

    /**
     * @param string $filename
     * @throws Exception
     */
    protected function doParseViewFile($filename)
    {
        if (! file_exists($filename)) {
            throw new Exception("No such file or directory ($filename)");
        }

        // pattern: #^(\w+Asset)::register#
        if ($handle = fopen($filename, "r")) {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('#(\w+Asset)::register#', $line, $display)) {
                    if (strlen($display[1]) > 2) {
                        $nameBundle = $this->dependenceManager['namespaceAsset'] . $display[1];
                        if (! in_array($nameBundle, $this->bundles)) {
                            $this->bundles[] = $nameBundle;
                        }
                    }
                }
            }
            fclose($handle);
        }
    }

    /**
     * @param \yii\web\AssetBundle $bundle
     * @param string $property (css|js)
     * @param array $exclude
     */
    protected function doParseBunble($bundle, $property, $exclude=[])
    {
        if (property_exists($bundle, $property)) {

            $sourcePath = ($bundle->sourcePath === null) ? $bundle->basePath : $bundle->sourcePath;
            foreach($bundle->{$property} as $element) {

                $basename = str_replace('.min', '', $element);
                // exclude file
                if (count($exclude) > 0 && in_array($basename, $exclude)) {
                    continue;
                }

                $filename = $sourcePath . DIRECTORY_SEPARATOR . $basename;
                if (file_exists($filename)) {
                    $this->data[$property][] = $filename;
                }
            }
        }
    }

    /**
     * @param string $filename
     * @param string $data
     */
    protected function doWriteFile($filename, $data)
    {
        $dataCommon = [];

        if (isset($data['css']) && count($data['css']) > 0) {
            $dataCommon = $data['css'];
            $filenameCss = Yii::getAlias($this->dependenceManager['configPath']) . DIRECTORY_SEPARATOR . $filename . "-css.json";
            if (file_put_contents($filenameCss, Json::encode($data['css'])) !== false) {
                $this->stdout("Data written to a file $filenameCss\n");
            }
        }

        if (isset($data['js']) && count($data['js']) > 0) {
            $dataCommon = array_merge($dataCommon, $data['js']);
            $filenameJs = Yii::getAlias($this->dependenceManager['configPath']) . DIRECTORY_SEPARATOR . $filename . "-js.json";
            if (file_put_contents($filenameJs, Json::encode($data['js'])) !== false) {
                $this->stdout("Data written to a file $filenameJs\n");
            }
        }

        if (count($dataCommon) > 0) {
            $filenameCommon = Yii::getAlias($this->dependenceManager['configPath']) . DIRECTORY_SEPARATOR . $filename . ".json";
            if (file_put_contents($filenameCommon, Json::encode($dataCommon)) !== false) {
                $this->stdout("Data written to a file $filenameCommon\n");
            }
        }
    }

    /**
     * Creates template of configuration file for [[actionCreateList]].
     * @param string $configFile output file name.
     * @return integer CLI exit code
     * @throws \yii\console\Exception on failure.
     */
    public function actionTemplate($configFile)
    {
        $template = <<<EOD
<?php
/**
 * Configuration file for the "yii dependence" console command.
 */

// In the console environment, some path aliases may not exist. Please define these:
//Yii::setAlias('@webroot', __DIR__ . '/../web');
//Yii::setAlias('@web', '/');

return [
    // Asset manager configuration:
    'assetManager' => [
        //'basePath' => '@webroot/assets',
        //'baseUrl' => '@web/assets',
    ],
    // Dependence manager configuration:
    'dependenceManager' => [
        //'configPath' => '@webroot',
        //'namespaceAsset' => 'app\\\\assets\\\\',
    ],
    // The list of asset bundles to dependence:
    'task' => [
        //'all' => [
        //    'src' => [
        //        '@app/assets'
        //    ]
        //],
        //'yii' => [
        //    'src' => [
        //        'yii\\web\\YiiAsset',
        //        'yii\\grid\\GridViewAsset',
        //        'yii\\captcha\\CaptchaAsset',
        //        'yii\\validators\\ValidationAsset',
        //        'yii\\widgets\\ActiveFormAsset',
        //        'yii\\widgets\\MaskedInputAsset',
        //        'yii\\authclient\\widgets\\AuthChoiceAsset',
        //    ],
        //    'exclude' => [
        //        'jquery.pjax.js'
        //    ]
        //],
        //'single-page' => [
        //    'src' => [
        //        '@app/views/default/index.php'
        //    ]
        //],
        //'controller' => [
        //    'src' => [
        //        '@app/controllers/DefaultController.php'
        //    ]
        //],
    ]
];
EOD;
        if (file_exists($configFile)) {
            if (!$this->confirm("File '{$configFile}' already exists. Do you wish to overwrite it?")) {
                return self::EXIT_CODE_NORMAL;
            }
        }
        if (!file_put_contents($configFile, $template)) {
            throw new Exception("Unable to write template file '{$configFile}'.");
        } else {
            $this->stdout("Configuration file template created at '{$configFile}'.\n\n", Console::FG_GREEN);
            return self::EXIT_CODE_NORMAL;
        }
    }
}