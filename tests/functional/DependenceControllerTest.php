<?php
namespace tests;

use Yii;
use yii\helpers\FileHelper;
use yii\helpers\Json;
use yii\helpers\StringHelper;
use kuaukutsu\dependencies\DependenceController;

/**
 * Class DependenceControllerTest
 * @package tests
 */
class DependenceControllerTest extends TestCase
{
    /**
     * @var string path for the test files.
     */
    protected $testFilePath = '';

    /**
     * @var string test assets path.
     */
    protected $testAssetsBasePath = '';

    /**
     * @var string test dependence path.
     */
    protected $testDependenceConfigPath = '';

    public function setUp()
    {
        $this->mockApplication();
        $this->testFilePath = Yii::getAlias('@tests/runtime') . DIRECTORY_SEPARATOR . str_replace('\\', '_', get_class($this)) . uniqid();
        $this->createDir($this->testFilePath);
        $this->testAssetsBasePath = $this->testFilePath . DIRECTORY_SEPARATOR . 'assets';
        $this->createDir($this->testAssetsBasePath);
        $this->testDependenceConfigPath = $this->testFilePath . DIRECTORY_SEPARATOR . 'dependence';
        $this->createDir($this->testDependenceConfigPath);
    }

    public function tearDown()
    {
        $this->removeDir($this->testFilePath);
    }

    /**
     * Creates directory.
     * @param string $dirName directory full name.
     */
    protected function createDir($dirName)
    {
        FileHelper::createDirectory($dirName);
    }

    /**
     * Removes directory.
     * @param string $dirName directory full name
     */
    protected function removeDir($dirName)
    {
        if (!empty($dirName)) {
            FileHelper::removeDirectory($dirName);
        }
    }

    /**
     * Creates test asset controller instance.
     * @return DependenceControllerMock
     */
    protected function createDependenceController()
    {
        /** @var \yii\base\Module $module */
        $module = $this->getMock('yii\\base\\Module', ['fake'], ['console']);
        $dependenceController = new DependenceControllerMock('dependence', $module);
        $dependenceController->interactive = false;
        return $dependenceController;
    }

    /**
     * Emulates running of the asset controller action.
     * @param  string $actionID id of action to be run.
     * @param  array  $args     action arguments.
     * @return string command output.
     */
    protected function runDependenceControllerAction($actionID, array $args = [])
    {
        $controller = $this->createDependenceController();
        $controller->run($actionID, $args);
        return $controller->flushStdOutBuffer();
    }

    /**
     * Creates test config.
     * @param  array[] $bundles asset bundles config.
     * @return array   config array.
     */
    protected function createConfig(array $bundles)
    {
        static $classNumber = 0;
        $classNumber++;
        $config = [
            'assetManager' => [
                'basePath' => $this->testAssetsBasePath,
                'baseUrl' => '',
            ],
            'dependenceManager' => [
                'configPath' => $this->testDependenceConfigPath,
                'namespaceAsset' => StringHelper::dirname(get_class($this)),
            ],
            'task' => [
                'test' => [
                    'src' => $bundles
                ],
            ]
        ];
        return $config;
    }

    /**
     * Creates test compress config file.
     * @param  string     $fileName output file name.
     * @param  array[]    $bundles  asset bundles config.
     * @throws \Exception on failure.
     */
    protected function createConfigFile($fileName, array $bundles)
    {
        $content = '<?php return ' . var_export($this->createConfig($bundles), true) . ';';
        if (file_put_contents($fileName, $content) <= 0) {
            throw new \Exception("Unable to create file '{$fileName}'!");
        }
    }

    /**
     * Creates test asset file.
     * @param  string     $fileRelativeName file name relative to [[testFilePath]]
     * @param  string     $content          file content
     * @throws \Exception on failure.
     */
    protected function createAssetSourceFile($fileRelativeName, $content)
    {
        $fileFullName = $this->testFilePath . DIRECTORY_SEPARATOR . $fileRelativeName;
        $this->createDir(dirname($fileFullName));
        if (file_put_contents($fileFullName, $content) <= 0) {
            throw new \Exception("Unable to create file '{$fileFullName}'!");
        }
    }

    /**
     * Creates a list of asset source files.
     * @param array $files assert source files in format: file/relative/name => fileContent
     */
    protected function createAssetSourceFiles(array $files)
    {
        foreach ($files as $name => $content) {
            $this->createAssetSourceFile($name, $content);
        }
    }

    /**
     * Composes asset bundle class source code.
     * @param  array  $config asset bundle config.
     * @return string class source code.
     */
    protected function composeAssetBundleClassSource(array &$config)
    {
        $config = array_merge(
            [
                'namespace' => StringHelper::dirname(get_class($this)),
                'class' => 'AppAsset',
                'basePath' => $this->testFilePath,
                'baseUrl' => '',
                'css' => [],
                'js' => [],
                'depends' => [],
            ],
            $config
        );
        foreach ($config as $name => $value) {
            if (is_array($value)) {
                $config[$name] = var_export($value, true);
            }
        }
        $source = <<<EOL
namespace {$config['namespace']};

use yii\web\AssetBundle;

class {$config['class']} extends AssetBundle
{
    public \$basePath = '{$config['basePath']}';
    public \$baseUrl = '{$config['baseUrl']}';
    public \$css = {$config['css']};
    public \$js = {$config['js']};
    public \$depends = {$config['depends']};
}
EOL;
        return $source;
    }

    /**
     * Declares asset bundle class according to given configuration.
     * @param  array  $config asset bundle config.
     * @return string new class full name.
     */
    protected function declareAssetBundleClass(array $config)
    {
        $sourceCode = $this->composeAssetBundleClassSource($config);
        eval($sourceCode);
        return $config['namespace'] . '\\' . $config['class'];
    }

    // Test:

    public function testActionTemplate()
    {
        $configFileName = $this->testFilePath . DIRECTORY_SEPARATOR . 'config.php';
        $this->runDependenceControllerAction('template', [$configFileName]);
        $this->assertTrue(file_exists($configFileName), 'Unable to create config file template!');
        $config = require($configFileName);
        $this->assertTrue(is_array($config), 'Invalid config created!');
    }


    public function testActionCreateList()
    {
        // Given :
        $cssFiles = [
            'css/test_body.css' => 'body {
                padding-top: 20px;
                padding-bottom: 60px;
            }',
            'css/test_footer.css' => '.footer {
                margin: 20px;
                display: block;
            }',
        ];
        $this->createAssetSourceFiles($cssFiles);

        $jsFiles = [
            'js/test_alert.js' => "function test() {
                alert('Test message');
            }",
            'js/test_sum_ab.js' => "function sumAB(a, b) {
                return a + b;
            }",
        ];
        $this->createAssetSourceFiles($jsFiles);

        $assetBundleClassName = $this->declareAssetBundleClass([
            'css' => array_keys($cssFiles),
            'js' => array_keys($jsFiles),
        ]);

        $configFile = $this->testFilePath . DIRECTORY_SEPARATOR . 'config2.php';
        $this->createConfigFile($configFile, [$assetBundleClassName]);

        // When :
        $this->runDependenceControllerAction('create-list', [$configFile]);

        // Then :
        $dependenceFile = $this->testDependenceConfigPath . DIRECTORY_SEPARATOR . 'test.json';
        $this->assertTrue(file_exists($dependenceFile), 'Unable to create output dependence file!');

        $dependenceConfig = Json::decode(file_get_contents($dependenceFile));
        $this->assertTrue(is_array($dependenceConfig), 'Output dependence file has incorrect format!');
        $this->assertCount(4, $dependenceConfig, 'Output bundle config contains wrong bundle count!');

        // js
        $jsFileName = $this->testFilePath . DIRECTORY_SEPARATOR . 'js/test_alert.js';
        $this->assertArraySubset([0 => $jsFileName], $dependenceConfig, 'Source bundle is lost!');
        // css
        $cssFileName = $this->testFilePath . DIRECTORY_SEPARATOR . 'css/test_footer.css';
        $this->assertArraySubset([3 => $cssFileName], $dependenceConfig, 'Source bundle is lost!');
    }
}

/**
 * Mock class for [[\yii\console\controllers\DependenceController]]
 * /yii2/tests/framework/console/controllers/StdOutBufferControllerTrait.php
 */
class DependenceControllerMock extends DependenceController
{
    use StdOutBufferControllerTrait;
}