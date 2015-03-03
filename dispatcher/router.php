<?php
/**
 * Author: Oli Griffiths <github.com/oligriffiths>
 * Date: 16/05/2014
 * Time: 16:47
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

class DispatcherRouter extends Library\DispatcherRouter
{
    /**
     * @var \Nooku\Library\CommandChain
     */
    protected $_cache_chain;

    /**
     * @var \Nooku\Library\CommandChain
     */
    protected $_rule_chain;

    /**
     * Object constructor
     * @param Library\ObjectConfig $config
     */
    public function __construct(Library\ObjectConfig $config = null)
    {
        parent::__construct($config);

        $this->_cache_chain = $this->getObject('com://oligriffiths/router.command.chain', array('break_condition' => true));

        $this->_rule_chain = $this->getObject('com://oligriffiths/router.command.chain', array('break_condition' => true));

        //Add the caches to the command chain
        foreach($config->caches->toArray() AS $cache => $options){
            $this->addCache($cache, $options);
        }

        //Add the rules to the command chain
        foreach($config->rules->toArray() AS $rule => $options){

            if(is_string($options)){
                $rule = $options;
                $options = array();
            }

            $this->addRule($rule, $options);
        }
    }

    /**
     * Initializes the config
     * @param Library\ObjectConfig $config
     */
    protected function _initialize(Library\ObjectConfig $config)
    {
        $config->append(array(
            'basepath' => null,
            'caches' => array(
                'memory' => array('priority' => Library\CommandHandlerInterface::PRIORITY_HIGH),
                'apc' => array('priority' => Library\CommandHandlerInterface::PRIORITY_NORMAL),
//                'database' => array('priority' => Library\CommandHandlerInterface::PRIORITY_HIGHEST),
            ),
            'rules' => array(
                'component' => array('priority' => Library\CommandHandlerInterface::PRIORITY_NORMAL),
                'template' => array('priority' => Library\CommandHandlerInterface::PRIORITY_HIGH)
            )
        ));

        parent::_initialize($config);
    }

    /**
     * Adds a rule to the chain
     *
     * @param $rule
     * @param array $options
     */
    public function addRule($rule, $options)
    {
        //Create relative identifier
        if(strpos($rule,'.') === false){
            $identifier = $this->getIdentifier()->toArray();
            $identifier['path'] = array('rule');
            $identifier['name'] = $rule;
            $rule = $identifier;
        }

        $this->_rule_chain->addHandler($this->getObject($rule, $options));
    }

    /**
     * Adds a cache to the chain
     *
     * @param $rule
     * @param array $options
     */
    public function addCache($cache, $options)
    {
        //Create relative identifier
        if(strpos($cache,'.') === false){
            $identifier = $this->getIdentifier()->toArray();
            $identifier['path'] = array('cache');
            $identifier['name'] = $cache;
            $cache = $identifier;
        }

        $this->_cache_chain->addHandler($this->getObject($cache, $options));
    }

    /***
     * Parses a route according to the attached parse rules
     * @param Library\HttpUrl $url
     * @return bool|void
     */
    public function parse(Library\HttpUrlInterface $url)
    {
        $command = new Library\Command();
        $command->url = clone $url;

        //Temp: Remove extension from path
        $path = $command->url->getPath();
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $command->url->path = substr($path, 0, $path - strlen($extension) - 1);

        $command->result = clone $command->url;

        if($this->_cache_chain->execute('fetch.parse', $command) !== true){

            //Run the before parse, allows rules to perform pre-parse checks
            $this->_rule_chain->execute('before.parse', $command);

            // Get the path
            $path = trim($url->getPath(), '/');

            //Remove base path
            $path = substr_replace($path, '', 0, strlen($this->getObject('request')->getBaseUrl()->getPath()));

            //Find the site
            $url->query['site']  = $this->getObject('application')->getSite();

            $route = str_replace($url->query['site'], '', $path);
            $url->path = ltrim($route, '/');

            //Remove base path
            $path = substr_replace($path, '', 0, strlen($this->getConfig()->basepath));

            //Set the route
            $command->result->path = trim($path , '/');

            //Run the parse chain
            $this->_rule_chain->execute('parse.route', $command);

            //Clear the path
            $command->result->path = '';

            //Store the result
            $this->_cache_chain->execute('store.parse', $command);
        }

        //Run after parse
        $this->_rule_chain->execute('after.parse', $command);

        //Set the url
        $url->path = $command->result->path;
        $url->query = $command->result->query;

        return $url;
    }

    /**
     * Builds a route according to the attached build rules
     * @param Library\HttpUrl $url
     * @return bool|Library\HttpUrl
     */
    public function build(Library\HttpUrlInterface $url)
    {
        static $building;

        //Ensure this can't recurse
        if($building) return $url;
        $building = true;

        $command = new Library\Command();
        $command->url = clone $url;
        $command->result = clone $url;
        $command->result->path = array();

        //Run the before build chain
        if($this->_cache_chain->execute('fetch.build', $command) !== true){

            //Run the before build, allows rules to perform pre-build checks
            $this->_rule_chain->execute('before.build', $command);

            //Run the build chain
            $this->_rule_chain->execute('build.route', $command);

            //Get the path
            $path = $command->result->path;

            //Add basepath if set
            if($basepath = $this->getConfig()->basepath) array_unshift($path, $basepath);

            //Build the route
            $command->result->path = implode($path,'/');

            //Store the result
            $this->_cache_chain->execute('store.build', $command);
        }

        //Run after build
        $this->_rule_chain->execute('after.build', $command);

        //Set the url
        $url->path = $command->result->path;
        $url->query = $command->result->query;

        //Clear flag
        $building = false;

        return $url;
    }

    /***
     * Clears any caches in rules
     */
    public function clearCache($options = array())
    {
        $command = new Library\Command();
        $command->setAttributes($options);
        $this->_cache_chain->execute('clear.cache', $command);
        return $this;
    }
}