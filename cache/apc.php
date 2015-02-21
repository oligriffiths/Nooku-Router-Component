<?php
/**
 * User: Oli Griffiths
 * Date: 03/01/2013
 * Time: 21:36
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

/**
 * Cache routing rule.
 * An APC cache that caches built and parsed rules
 */
class CacheApc extends RuleDefault
{
    /**
     * @var string The cache identifier string
     */
    protected $_cache_identifier;

    /**
     * Constructor.
     *
     * @param   object  An optional Library\ObjectConfig object with configuration options
     */
    public function __construct(Library\ObjectConfig $config)
    {
        parent::__construct($config);

        //Compile the cache identifier
        $this->_cache_identifier = $config->cache_namespace.$config->cache_identifier;
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
            'cache_namespace' => $this->getObject('application')->getConfig()->cache_namespace,
            'cache_identifier' => 'routes'
        ));

        parent::_initialize($config);
    }


    /**
     * Route build pre-processor. Stops execution if route is found in cache
     * @param Library\CommandInterface $command
     * @return bool
     */
    protected function _beforeBuild(Library\CommandInterface $command)
    {
        static $routes;

        if(extension_loaded('apc')){

            $query = $this->_buildCacheIdentifier($command->original->query);

            if(!isset($routes[$query])){
                if($route = apc_fetch($this->_cache_identifier.'-build-'.$query)){
                    $routes[$query] = $this->getObject('lib:http.url', array('url' => $route));
                }
            }

            if(isset($routes[$query])){
                $command->url = clone $routes[$query];
                return true;
            }
        }
    }


    /**
     * Route build post-processor, store the route to the cache
     * @param Library\CommandInterface $command
     * @return bool
     */
    protected function _afterBuild(Library\CommandInterface $command)
    {
        if(extension_loaded('apc')){

            $url = clone $command->original;
            $query = $this->_buildCacheIdentifier($url->query);

            return apc_store($this->_cache_identifier.'-build-'.$query, $command->url->toString(Library\HttpUrl::PATH + Library\HttpUrl::FORMAT + Library\HttpUrl::QUERY));
        }
    }


    protected function _buildCacheIdentifier()
    {
        //Ensure option and query come first, required for clean cache
        $q = array();
        if(isset($query['component'])){
            $q['component'] = $query['component'];
            unset($query['component']);
        }
        if(isset($query['view'])){
            $q['view'] = $query['view'];
            unset($query['component']);
        }

        ksort($query);

        return http_build_query(array_merge($q,$query));
    }


    /**
     * Route parse pre-processor, stops execution if round found in cache
     * @param Library\CommandInterface $command
     * @return bool
     */
    protected function _beforeParse(Library\CommandInterface $command)
	{
        if(extension_loaded('apc')){
            $route = $command->original->toString(Library\HttpUrl::PATH + Library\HttpUrl::FORMAT);

            if($query = apc_fetch($this->_cache_identifier.'-parse-'.$route)){
                if(!isset($query['format'])) $query['format'] = $command->url->format ? $command->url->format : 'html';
                $command->url->query = $query;
                $command->url->path = 'index';
                $command->url->format = 'php';
                return true;
            }
        }
	}


    /**
     * Route parse post-processor, stores route to cache
     * @param Library\CommandInterface $command
     * @return bool
     */
    protected function _afterParse(Library\CommandInterface $command)
    {
        if(extension_loaded('apc')){
            $url = clone $command->original;
            apc_store($this->_cache_identifier.'-parse-'.$url->toString(Library\HttpUrl::PATH + Library\HttpUrl::FORMAT), $command->url->query);
        }
    }


    /**
     * Cleans the APC cache
     * @param Library\CommandInterface $command - Accepts an optional type property (build|parse) to define what caches to clear
     */
    protected function _clearCache(Library\CommandInterface $command)
    {
        if(extension_loaded('apc')){
            $type = $command->type;

            if($items = apc_cache_info('user')){

                $query = $this->_cache_identifier.'-'.($type ? $type.'-'.http_build_query(array('component' => $command->component, 'view' => $command->view)) : '');
                $length = strlen($query);

                foreach($items['cache_list'] AS $item){
                    if(substr($item['info'], 0, $length) == $query){
                        apc_delete($item['info']);
                    }
                }
            }
        }
    }
}