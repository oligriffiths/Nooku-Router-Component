<?php
/**
 * User: Oli Griffiths
 * Date: 14/10/2012
 * Time: 11:50
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;
use Nooku\Component\Application;

/**
 * Nooku enhanced router. Allows for routing based on rules rather than pre-defined methods.
 * @see router/rules for the available rules
 */
class Router extends \ApplicationRouter implements Framework\ServiceInstantiatable
{
    /**
     * The rule command chain
     * 
     * @var RouterCommandChain
     */
    protected $_chain;

    /**
     * Add format suffix to built routes
     * 
     * @var bool
     */
    protected $_suffix;

    /**
     * Enables rewriting. Requires htaccess to work. When false, requests are routed via /index.php/
     * 
     * @var bool
     */
    protected $_rewrite;


	/**
	 * Force creation of a singleton
	 *
	 * @param 	object 	An optional Framework\Config object with configuration options
	 * @param 	object	A Framework\ServiceInterface object
	 * @return KDispatcherDefault
	 */
    public static function getInstance(Framework\Config $config, Framework\ServiceManagerInterface $manager)
	{
		// Check if an instance with this identifier already exists or not
		if (!$manager->has($config->service_identifier))
		{
			//Create the singleton
			$classname = $config->service_identifier->classname;
			$instance  = new $classname($config);
			$manager->set($config->service_identifier, $instance);
		}

		return $manager->get($config->service_identifier);
	}


	/**
	 * Object constructor
	 * @param Framework\Config $config
	 */
	public function __construct(Framework\Config $config = null)
	{
		parent::__construct($config);

        $this->_suffix = $config->suffix;
        $this->_rewrite = $config->rewrite;

		//Attach rules
        $rules = $config->rules->toArray();

        //If apc is not loaded, remove cache rule
        if(!extension_loaded('apc')) unset($rules['cache']);

        //Backend routes do not need component/pages or database rules
        if($this->getService('application')->getIdentifier()->namespace == 'admin'){
            unset($rules['component']);
            unset($rules['database']);
        }

        //Set the mixer
        $config->mixer = $this;

        //Mixin the command chain to hold the rules
        $this->mixin(new Framework\MixinCommand($config));

        //Add the rules to the command chain
		foreach($rules AS $rule => $options)
		{
            //Check for disabled rule
            if($options === false) continue;

            //Create relative identifier
			if(strpos($rule,'.') === false){
                $identifier = clone $this->getIdentifier();
                $identifier->path = array('rule');
                $identifier->name = $rule;
                $rule = $identifier;
			}

			$this->getCommandChain()->enqueue($this->getService($rule, $options));
		}
	}


	/**
	 * Initializes the config
	 * @param Framework\Config $config
	 */
	protected function _initialize(Framework\Config $config)
	{
		$config->append(array(
            'command_chain' => 'com://site/router.command.chain',
            'event_dispatcher'  => 'lib://nooku/event.dispatcher.default',
            'dispatch_events' => false, //@TODO: Enable this once Framework\CommandEvent is fixed, see https://groups.google.com/d/msg/nooku-framework/OsX6si_I1gY/eIGKwCBxS0oJ
            'suffix' => $this->getService('application')->getCfg('sef_suffix'),
            'rewrite' => $this->getService('application')->getCfg('sef_rewrite'),
			'rules' => array(
				'cache' => array('priority' => Framework\Command::PRIORITY_HIGHEST),
				'database' => array('priority' => Framework\Command::PRIORITY_HIGH),
				'registry' => array('priority' => Framework\Command::PRIORITY_NORMAL),
				'component' => array('priority' => Framework\Command::PRIORITY_LOW),
			)
		));

		parent::_initialize($config);
	}


    /***
     * Parses a route according to the attached parse rules
     * @param Framework\HttpUrl $url
     * @return bool|void
     */
    public function parse(Framework\HttpUrl $url)
    {
        $context = $this->getCommandContext();
        $context->url = clone $url;
        $context->result = $url;

        //Run before chain
        if($this->getCommandChain()->run('before.parse', $context) !== true){

            // Get the path
            $path = $context->url->getPath();

            //Remove base path
            $path = substr_replace($path, '', 0, strlen($this->getService('request')->getBaseUrl()->getPath()));

            //Remove the filename
            $path = preg_replace('#^index\.php#', '', $path);

            //Find & remove the site
            $site = $this->getService('application')->getSite();
            if($site != 'default'){
                $context->url->query['site']  = $site;
                $path = str_replace($context->url->query['site'], '', $path);
            }

            //Set the route
            $context->url->path = trim($path , '/');

            //Ensure index.php gets converted to html if no format is set
            if($context->url->format == 'php' && !isset($context->url->query['format'])){
                $context->url->format = 'html';
            }

            // Set the format as a query var
            if($context->url->format && !isset($context->url->query['format'])) {
                $context->url->query['format'] = $context->url->format;
            }

            //Run the parse chain
            $this->getCommandChain()->run('parse', $context);

            //Add page id if not set
            if(!isset($context->result->query['Itemid'])){
                if($page_id = $this->getService('com://site/router.helper.pages')->findPageId($context->result->query)){
                    $context->result->query['Itemid'] = $page_id;
                }
            }

            //Run after parse
            $this->getCommandChain()->run('after.parse', $context);
        }

        //Set the url
        $url->path = $context->result->path;
        $url->query = $context->result->query;
        $url->format = $context->result->format;

        //Set the active page
        $pages = $this->getService('application.pages');
        if($pages->getIdentifier()->namespace == 'site'){
            if(!isset($url->query['Itemid'])) {
                $url->query['Itemid'] = $pages->getHome()->id;
            }
            $pages->setActive($url->query['Itemid']);
        }

        return $url;
    }


    /**
     * Builds a route according to the attached build rules
     * @param Framework\HttpUrl $url
     * @return bool|Framework\HttpUrl
     */
    public function build(Framework\HttpUrl $url)
	{
        static $building;

		if(isset($url->query['option']) && !$building){

            $building = true;
			$context = $this->getCommandContext();
			$context->url = clone $url;
			$context->result = $url;
            $context->result->path = array();

            //Run the before build chain
			if($this->getCommandChain()->run('before.build', $context) !== true){

                //Run the build chain
                $this->getCommandChain()->run('build', $context);

                $path = $context->result->path;

                //Encode each path segment
                array_walk($path, array($this, '_encodeSegment'));

                //Remove the Itemid if still set and the page query matches the route query
                if(isset($context->result->query['Itemid'])){
                    if($page = $this->getService('application.pages')->getPage($context->result->query['Itemid'])){
                        $link = $page->getLink();
                        if($link && array_intersect_key($link->query, $context->url->query) == $link->query){
                            unset($context->result->query['Itemid']);
                            if($page->home) $path = array();
                        }
                    }

                    //Clear itemid if still set and an override exists
                    if(isset($context->result->query['Itemid'])){
                        if($itemid = $this->getService('com://site/router.helper.pages')->findPageIdOverride($context->url->query)){
                            unset($context->result->query['Itemid']);
                        }
                    }
                }

                //Add site prefix
                $site = $this->getService('application')->getSite();
                if($site != 'default' && $site != $this->getService('application')->getRequest()->toString(Framework\HttpUrl::HOST)) {
                    $path = array_merge(array($site), $path);
                }

                //If sef rewrite is disabled, route via index.php
                if(!$this->_rewrite && (count($path) || count($context->result->query))) {
                    $path = array_merge(array('index.php'), $path);
                }

                //Add the format to the uri
                $format = isset($context->result->query['format']) ? $context->result->query['format'] : 'html';
                if(!$this->_suffix && $format == 'html'){
                    $format = '';
                }
                unset($context->result->query['format']);

                $context->result->format = !empty($path) ? $format : '';

                //Run after build
                $this->getCommandChain()->run('after.build', $context);

                //Add root prefix
                $context->result->path = $this->getService('request')->getBaseUrl()->getPath().'/'.implode('/',$path);
            }

            //Set the url
            $url->path = $context->result->path;
            $url->query = $context->result->query;
            $url->format = $context->result->format;

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
        $context = $this->getCommandContext();
        $context->append($options);
        $this->getCommandChain()->run('clear.cache', $context);
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