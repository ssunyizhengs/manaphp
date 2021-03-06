<?php

namespace ManaPHP\Di;

use ManaPHP\Di;

/**
 * Class ManaPHP\Di\FactoryDefault
 *
 * @package di
 */
class FactoryDefault extends Di
{
    /**
     * \ManaPHP\Di\FactoryDefault constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->_components = [
            'eventsManager' => 'ManaPHP\Event\Manager',
            'alias' => 'ManaPHP\Alias',
            'dotenv' => 'ManaPHP\Dotenv',
            'configure' => 'ManaPHP\Configuration\Configure',
            'settings' => 'ManaPHP\Configuration\Settings\Adapter\Redis',
            'modelsMetadata' => 'ManaPHP\Db\Model\Metadata\Adapter\Memory',
            'modelsValidator' => 'ManaPHP\Model\Validator',
            'queryBuilder' => ['class' => 'ManaPHP\Db\Model\Query', 'shared' => false],
            'filter' => 'ManaPHP\Http\Filter',
            'crypt' => 'ManaPHP\Security\Crypt',
            'logger' => 'ManaPHP\Logger',
            'renderer' => 'ManaPHP\Renderer',
            'html' => 'ManaPHP\Renderer\Html',
            'assetBundle' => 'ManaPHP\Renderer\AssetBundle',
            'password' => 'ManaPHP\Password',
            'serializer' => 'ManaPHP\Serializer\Adapter\JsonPhp',
            'cache' => 'ManaPHP\Cache',
            'ipcCache' => 'ManaPHP\IpcCache',
            'counter' => 'ManaPHP\Counter',
            'httpClient' => 'ManaPHP\Curl\Easy',
            'identity' => 'ManaPHP\Identity\Adapter\Session',
            'paginator' => 'ManaPHP\Paginator',
            'filesystem' => 'ManaPHP\Filesystem\Adapter\File',
            'random' => 'ManaPHP\Security\Random',
            'messageQueue' => 'ManaPHP\Message\Queue',
            'crossword' => 'ManaPHP\Text\Crossword',
            'rateLimiter' => 'ManaPHP\Security\RateLimiter',
            'linearMeter' => 'ManaPHP\Meter\Linear',
            'roundMeter' => 'ManaPHP\Meter\Round',
            'secint' => 'ManaPHP\Security\Secint',
            'swordCompiler' => 'ManaPHP\Renderer\Engine\Sword\Compiler',
            'stopwatch' => 'ManaPHP\Stopwatch',
            'tasksManager' => 'ManaPHP\Task\Manager',
            'htmlPurifier' => 'ManaPHP\Security\HtmlPurifier',
            'netConnectivity' => 'ManaPHP\Net\Connectivity',
            'db' => 'ManaPHP\Db\Adapter\Mysql',
            'redis' => 'ManaPHP\Redis',
            'mongodb' => 'ManaPHP\Mongodb',
            'translator' => 'ManaPHP\I18n\Translator',
            'rabbitmq' => 'ManaPHP\Amqp',
            'relationsManager' => 'ManaPHP\Model\Relation\Manager',
            'authenticationToken' => 'ManaPHP\Authentication\Token\Adapter\Mwt',
            'mailer' => 'ManaPHP\Mailer\Adapter\Smtp',
            'aclBuilder' => 'ManaPHP\Authorization\AclBuilder',
        ];
    }
}