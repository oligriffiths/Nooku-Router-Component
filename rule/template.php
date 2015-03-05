<?php
/**
 * User: Oli Griffiths
 * Date: 03/01/2013
 * Time: 21:36
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

/**
 * Template routing rule
 * Build/parses routes based on static routes that are registered with the registry object.
 * These routes are templated/defined routes.
 */
class RuleTemplate extends RuleDefault
{
    /**
     * The registry object that stores all the connected routes
     * @var RouterRegistry
     */
    protected $_registry;


    /**
     * Constructor.
     *
     * @param   object  An optional Library\ObjectConfig object with configuration options
     */
    public function __construct(Library\ObjectConfig $config)
	{
		parent::__construct($config);
		$this->_registry = $config->registry;
	}


    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param   object  An optional Library\ObjectConfig object with configuration options
     * @return void
     */
    protected function _initialize(Library\ObjectConfig $config)
	{
		$config->append(array(
			'registry_options' => array(
                'auto_load' => true,
                'auto_create' => true,
                'cache_identifier' => 'nooku-route-cache-template'
            )
		))->append(array(
			'registry' => $this->getObject('com:router.rule.template.registry', $config->registry_options->toArray())
		));
		parent::_initialize($config);
	}


    /**
     * Builds a route using the registry object. If a route is found, execution is stopped.
     * @param Library\CommandInterface $context
     * @return bool
     */
    protected function _buildRoute(Library\CommandInterface $context)
	{
        $url = clone $context->url;
		if($this->_registry->match($url)){
            $context->result->path = array_merge($url->path, $context->url->path);
            $context->result->query = $url->query;
            return true;
        }
	}


    /**
     * Parses a route using the registry object. If a route is found, execution is stopped
     * @param Library\CommandInterface $context
     * @return bool
     */
    protected function _parseRoute(Library\CommandInterface $context)
	{
		//Check if a static route is defined for this url
        $url = clone $context->url;
        $url->path = '/'.$url->getPath();

		if($parsed_url = $this->_registry->parse($url))
		{
			if(isset($parsed_url->query['component'])){
				$context->result->query = array_merge($context->url->query, $parsed_url->query);
                $context->result->path  = $parsed_url->path;
				return true;
			}
		}
	}


    /**
     * Cleans the APC cache.
     * @param Library\CommandInterface $context - Optional 'type' parameter (parse|build|routes) can be set to define the cache type to clear
     */
    protected function _clearCache(Library\CommandInterface $context)
    {
        $this->_registry->clearCache($context->type);
    }
}