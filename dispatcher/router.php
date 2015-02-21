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
    protected $_cache_chain;

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

        //Attach rules
        $rules = $config->rules->toArray();

        //Add the rules to the command chain
        foreach($rules AS $rule => $options){
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
            'default_format' => 'html',
            'basepath' => $this->getObject('request')->getBaseUrl()->getPath(),
            'caches' => array(
                'apc' => array('priority' => Library\CommandHandlerInterface::PRIORITY_HIGHEST),
            ),
            'rules' => array(
                'component' => array('priority' => Library\CommandHandlerInterface::PRIORITY_HIGH),
                'template' => array('priority' => Library\CommandHandlerInterface::PRIORITY_NORMAL),
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

        $this->getCommandChain()->addHandler($this->getObject($rule, $options));
    }


    /***
     * Parses a route according to the attached parse rules
     * @param Library\HttpUrl $url
     * @return bool|void
     */
    public function parse(Library\HttpUrlInterface $url)
    {
        $command = new Library\Command();
        $command->original = clone $url;
        $command->url = clone $url;

        //Run before chain
        if($this->invokeCommand('before.parse', $command) !== false)
        {
            // Get the path
            $path = trim($url->getPath(), '/');

            //Remove base path
            $path = substr_replace($path, '', 0, strlen($this->getObject('request')->getBaseUrl()->getPath()));

            // Set the format
            if(!empty($url->format)) {
                $url->query['format'] = $url->format;
            }

            //Find the site
            $url->query['site']  = $this->getObject('application')->getSite();

            $route = str_replace($url->query['site'], '', $path);
            $url->path = ltrim($route, '/');

            //Remove base path
            $path = substr_replace($path, '', 0, strlen($this->getObject('request')->getBaseUrl()->getPath()));

            //Set the route
            $command->url->path = trim($path , '/');

            //Run the parse chain
            $this->invokeCommand('parse.route', $command);

            //Clear the path
            $command->url->path = '';

            //Run after parse
            $this->invokeCommand('after.parse', $command);
        }

        //Set the url
        $url->path = $command->url->path;
        $url->query = $command->url->query;
        $url->format = $command->url->format;

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

        if(!$building){

            $building = true;
            $command = new Library\Command();
            $command->original = clone $url;
            $command->url = clone $url;
            $command->url->path = array();
            $command->url->format = null;

            //Run the before build chain
            if($this->invokeCommand('before.build', $command) !== true){

                //Run the build chain
                $this->invokeCommand('build.route', $command);

                //Get the path
                $path = $command->url->path;

                //Add the format to the uri if set in querystring
                if(isset($command->url->query['format'])){
                    $command->url->format = $command->url->query['format'];
                    unset($command->url->query['format']);
                }

                //Add default format if set
                if(!$command->url->format){
                    $command->url->format = $this->getConfig()->default_format;
                }

                //Add basepath if set
                if($basepath = $this->getConfig()->basepath) array_unshift($path, $basepath);

                //Build the route
                $command->url->path = implode($path,'/');

                //Set the format if not set
                if(!$command->url->format) $command->url->format = $this->getConfig()->default_format;

                //Run after build
                $this->invokeCommand('after.build', $command);
            }

            //Set the url
            $url->path = $command->url->path;
            $url->query = $command->url->query;
            $url->format = $command->url->format;

            //Clear flag
            $building = false;

            return $url;
        }
    }


    /***
     * Clears any caches in rules
     */
    public function clearCache($options = array())
    {
        $command = new Library\Command();
        $command->setAttributes($options);
        $this->invokeCommand('clear.cache', $command);
        return $this;
    }
}