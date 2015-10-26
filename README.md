yii2-dependencies
==================

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist kuaukutsu/yii2-dependencies "*"
```

or add

```
"kuaukutsu/yii2-dependencies": "*"
```

to the require section of your composer.json.

Usage
-------------

Add the following in config/console.php:

```php
    'controllerMap' => [
        'dependence' => [
            'class' => 'kuaukutsu\dependencies\DependenceController'
        ]
    ]
```

Configuration
-------------

To use this command, you should first create a configuration file to describe what asset bundles should be combined and how they should be grouped. You can use the ```dependence/template``` sub-command to generate a template first and then modify it to fit for your needs.
```bash
yii dependence/template dependence.php
```

The command generates a file named ```dependence.php``` in the current directory. The content of this file looks like the following:
```php
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
            'basePath' => '@webroot/assets',
            'baseUrl' => '@web',
        ],
        // Dependence manager configuration:
        'dependenceManager' => [
            'configPath' => '@webroot',
            'namespaceAsset' => 'app\\assets\\',
        ],
        // The list of asset bundles to dependence:
        'task' => [
            'all' => [
                'src' => [
                    '@app/assets'
                ]
            ],
            'yii' => [
                'src' => [
                    'yii\web\YiiAsset',
                    'yii\grid\GridViewAsset',
                    'yii\captcha\CaptchaAsset',
                    'yii\validators\ValidationAsset',
                    'yii\widgets\ActiveFormAsset',
                    'yii\widgets\MaskedInputAsset',
                    'yii\authclient\widgets\AuthChoiceAsset',
                ],
                'exclude' => [
                    'jquery.pjax.js'
                ]
            ],
            'single-page' => [
                'src' => [
                    '@app/views/default/index.php'
                ]
            ],
            'controller' => [
                'src' => [
                    '@app/controllers/DefaultController.php'
                ]
            ],
        ]
    ];

```
You should modify this file and specify which bundles you plan to include in the file dependencies.

With the configuration file, you can run the ```dependence``` command to generate a new files dependencies ```@webroot/all.json``` and ```@webroot/all-js.json``` and ```@webroot/all-css.json``` (```dependenceManager['configPath']``` + ```task['name'].json ```):
```bash
yii dependence dependence.php
```
The generated files contain a list of dependencies in json format. How to use it, see [Asset processing with Grunt](https://yii2-cookbook.readthedocs.org/structure-asset-processing-with-grunt/)