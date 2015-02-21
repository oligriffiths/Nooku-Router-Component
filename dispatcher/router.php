<?php
/**
 * Author: Oli Griffiths <github.com/oligriffiths>
 * Date: 16/05/2014
 * Time: 16:47
 */

namespace Nooku\Component\Router;

use Nooku\Library;

class DispatcherRouter extends Library\DispatcherRouter implements Library\ObjectInstantiable
{
    /**
     * The rule command chain
     *
     * @var RouterCommandChain
     */
    protected $_chain;


    /**
     * Object constructor
     * @param Library\ObjectConfig $config
     */
    public function __construct(Library\ObjectConfig $config = null)
    {
        parent::__construct($config);

        //Attach rules
        $rules = $config->rules->toArray();

        //If apc is not loaded, remove cache rule
        if(!extension_loaded('apc')) unset($rules['cache']);

        //Set the mixer
        $config->mixer = $this;
        $config->break_condition = true;

        // Mixin the command interface
        $this->mixin('lib:command.mixin', $config);

        //Add the rules to the command chain
        foreach($rules AS $rule => $options)
        {
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
            'command_chain' => 'com:router.command.chain',
            'suffix' => 'html',
            'rules' => array(
//                'cache' => array('priority' => Library\CommandHandlerInterface::PRIORITY_HIGHEST),
                'component' => array('priority' => Library\CommandHandlerInterface::PRIORITY_HIGH),
                'template' => array('priority' => Library\CommandHandlerInterface::PRIORITY_NORMAL),
            )
        ));

        parent::_initialize($config);
    }


    /**
     * Force creation of a singleton
     *
     * @param 	object 	An optional Library\ObjectConfig object with configuration options
     * @param 	object	A Library\ServiceInterface object
     * @return KDispatcherDefault
     */
    public static function getInstance(Library\ObjectConfig $config, Library\ObjectManagerInterface $manager)
    {
        // Check if an instance with this identifier already exists or not
        if (!$manager->isRegistered($config->object_identifier))
        {
            $class     = $manager->getClass($config->object_identifier);
            $instance  = new $class($config);
            $manager->setObject($config->object_identifier, $instance);

            //Add the service alias to allow easy access to the singleton
            $manager->registerAlias($config->object_identifier, 'router');
        }

        return $manager->getObject($config->object_identifier);
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
    public function parse(Library\HttpUrl $url)
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

            //Ensure a format is set
            if(!isset($command->url->query['format'])){
                $command->url->format = 'html';
            }

            // Set the format as a query var
            if($command->url->format && !isset($command->url->query['format'])) {
                $command->url->query['format'] = $command->url->format;
            }

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
    public function build(Library\HttpUrl $url)
    {
        static $building;

        if(isset($url->query['option']) && !$building){

            $building = true;
            $command = new Library\Command();
            $command->original = clone $url;
            $command->url = clone $url;
            $command->url->path = array();

            //Run the before build chain
            if($this->invokeCommand('before.build', $command) !== true){

                //Run the build chain
                $this->invokeCommand('build.route', $command);

                //Get the path
                $path = $command->url->path;

                //Encode each path segment
                array_walk($path, array($this, '_encodeSegment'));

                //Add the format to the uri
                if(isset($command->url->query['format']))
                {
                    $format = $command->url->query['format'];

                    if($format != 'html') {
                        $command->url->format = $format;
                    }

                    unset($command->url->query['format']);
                }

                //Build the route
                if($root = $this->getObject('request')->getBaseUrl()->getPath()) array_unshift($path, $root);
                $command->url->path = implode($path,'/');

                //Run after build
                $this->invokeCommand('after.build', $command);

//                //Remove the Itemid if still set and the page query matches the route query
//                if(isset($command->url->query['Itemid'])){
//                    if($page = $this->getObject('application.pages')->getPage($command->url->query['Itemid'])){
//                        $link = $page->getLink();
//                        if($link && array_intersect_key($link->query, $command->url->query) == $link->query){
//                            unset($command->url->query['Itemid']);
//                            if($page->home) $path = array();
//                        }
//                    }
//
//                    //Clear itemid if still set and an override exists
//                    if(isset($command->url->query['Itemid'])){
//                        if($itemid = $this->getObject('com://site/router.helper.pages')->findPageIdOverride($command->url->query)){
//                            unset($command->url->query['Itemid']);
//                        }
//                    }
//                }
//
//                //Add site prefix
//                $site = $this->getObject('application')->getSite();
//                if($site != 'default' && $site != $this->getObject('application')->getRequest()->toString(Library\HttpUrl::HOST)) {
//                    $path = array_merge(array($site), $path);
//                }
//
//                //Add the format to the uri
//                $format = isset($command->url->query['format']) ? $command->url->query['format'] : 'html';
//                if(!$this->_suffix && $format == 'html'){
//                    $format = '';
//                }
//                unset($command->url->query['format']);
//
//                $command->url->format = !empty($path) ? $format : '';

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


    /**
     * Encodes a segment of a path, replacing spaces with dashes and encoding
     * @param $segment
     */
    protected function _encodeSegment(&$segment)
    {
        $segment = strtolower(rawurlencode(str_replace(' ', '-', $segment)));
    }
}