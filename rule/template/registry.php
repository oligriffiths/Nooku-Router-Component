<?php
/**
 * User: Oli Griffiths
 * Date: 14/10/2012
 * Time: 18:13
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

class RuleTemplateRegistry extends Library\Object
{
	/**
	 * Holds the added route templates
	 * @note Routes loaded from $this->loadRoutes() are cached in APC, so must be cleared if the files are changed
	 * @var array
	 */
	protected static $_templates = array();

	/**
	 * if set to true, routes for components missing a router or routes.json/php files will be auto created
	 * @see $this->loadRoutes()
	 * @var bool
	 */
	protected $_auto_create_routes;

	/**
	 * Cache identifier used by APC
	 * APC is used to cache 3 things:
	 *      Routes loaded from loadRoutes()
	 *      Parsed routes
	 *      Built routes
	 *
	 * If any routes are changed without clearing the cache, APC must be cleaned
	 * @var string
	 */
	protected $_cache_identifier;


	/**
	 * Object constructor
	 * @param Library\ObjectConfig $config
	 */
	public function __construct(Library\ObjectConfig $config = null)
	{
		parent::__construct($config);

        //Compile the cache identifier
        $this->_cache_identifier = $config->cache_namespace.$config->cache_identifier;

		$this->_auto_create_routes = $config->auto_create;

		if($config->auto_load) $this->loadRoutes();
	}


	/**
	 * Initializes the config
	 * @param Library\ObjectConfig $config
	 */
	protected function _initialize(Library\ObjectConfig $config)
	{
		$config->append(array(
			'auto_create' => true,
			'auto_load' => true,
            'cache_namespace' => $this->getObject('application')->getConfig()->cache_namespace,
            'cache_identifier' => 'route-templates'
		));
		parent::_initialize($config);
	}


	/**
	 * Main router connect method for adding routes
	 * @param $rule - the rule itself, see RouterRoute::$_template
	 * @param array $params
	 * @param array $config
	 * @see RouterRoute::$_template
	 * @see RouterRoute::$_params
	 * @see http://lithify.me/docs/lithium/net/http/Route
	 * @return mixed
	 */
	public function connect($rule, $params = array(), $config = array())
	{
		$params = $params instanceof Library\ObjectConfig ? $params->toArray() : $params;
		$config = $config instanceof Library\ObjectConfig ? $config->toArray() : $config;

		//Add starting slash
		$rule = '/'.ltrim($rule,'/');

		//Set the config
		$config['template'] = $rule;
		$config['params'] = $params;

		//Get route instance
        $class = $this->getObject('manager')->getClass('com:router.rule.template.route');
		self::$_templates[$rule] = new $class(new Library\ObjectConfig($config));

		return self::$_templates[$rule];
	}


	/**
	 * Removes a connected route if it exists
	 * @param $rule
	 */
	public function disconnect($rule)
	{
		unset(self::$_templates[$rule]);
	}


	/**
	 * Returns the defined routes
	 * @return array
	 */
	public function routes()
	{
		return self::$_templates;
	}


	/**
	 * Parses a url and returns a matching internal route or false on no match
	 * @param $url
	 * @return bool|Library\HttpUrl
	 */
	public function parse(Library\HttpUrl $url)
	{
		$url        = clone $url; //Ensure original url is not modified
		$path       = $url->toString(Library\HttpUrl::PATH);

		$routes = array();

		//Check for an exact match first
		if(isset(self::$_templates[$path])){

			$routes[] = self::$_templates[$path];
		}else{
			//If an exact match failed, attempt to match as many routes based on the route path as possible
			//For example /products/{:id} would be matched against /products/5.html, (/products = /products)
			//This reduces the number of routes that have to be parsed below, = more efficient

			//Attempt to match the base route
			$parts = explode('/',ltrim($path,'/'));

			//Compile a list of paths for simple matching
			$path = '';
			foreach($parts AS $part)
			{
				$path .= '/'.$part;
				foreach(array_keys(array_reverse(self::$_templates, true)) AS $route){
					if(preg_match('#^'.preg_quote($path,'#').'#', $route)){
						$routes[$route] = self::$_templates[$route];
					}
				}
			}
		}

		//If we have no matched routes, get all routes
		if(empty($routes)){
			$routes = self::$_templates;
		}

		//Loop routes and attempt to match
		$vars = array();
		$count = count($routes)-1;
		$i = 0;
		foreach($routes AS $route)
		{
			$tmp_url = clone $url;
			if($route_vars = $route->parse($tmp_url)){
				$vars = $vars + $route_vars;
				if(!$route->canContinue() || $count == $i) break;
			}
			$i++;
		}

		//If we have vars, return the new url
		if(count($vars)){
			$url->query = $vars;
			$url->path = array();
			return $url;
		}

		return false;
	}


	/**
	 * Attempts to match a Library\HttpUrl against the defined routes a connected `Route` object.
	 * For example, given the following route:
	 *
	 * {{{
	 * Router::connect('/login', array('component' => 'com_users', 'view' => 'login'));
	 * }}}
	 *
	 * This will match:
	 * {{{
	 * $url = Router::match(array('component' => 'com_users', 'view' => 'login'));
	 * // returns /login
	 * }}}
	 *
	 * For URLs templates with no insert parameters (i.e. elements like `{:id}` that are replaced
	 * with a value), all parameters must match exactly as they appear in the route parameters.
	 *
	 * Alternatively to using a full array, you can specify routes using a more compact syntax. The
	 * above example can be written as:
	 *
	 * {{{ $url = Router::match('Users::login'); // still returns /login }}}
	 *
	 * You can combine this with more complicated routes; for example:
	 * {{{
	 * Router::connect('/posts/{:id:\d+}', array('controller' => 'posts', 'action' => 'view'));
	 * }}}
	 *
	 * This will match:
	 * {{{
	 * $url = Router::match(array('controller' => 'posts', 'action' => 'view', 'id' => '1138'));
	 * // returns /posts/1138
	 * }}}
	 *
	 * Again, you can specify the same URL with a more compact syntax, as in the following:
	 * {{{
	 * $url = Router::match(array('Posts::view', 'id' => '1138'));
	 * // again, returns /posts/1138
	 * }}}
	 *
	 * You can use either syntax anywhere a URL is accepted, i.e.
	 * `lithium\action\Controller::redirect()`, or `lithium\template\helper\Html::link()`.
	 *
	 * @param string|array $url Options to match to a URL. Optionally, this can be a string
	 *              containing a manually generated URL.
	 * @param object $context An instance of `lithium\action\Request`. This supplies the context for
	 *               any persistent parameters, as well as the base URL for the application.
	 * @param array $options Options for the generation of the matched URL. Currently accepted
	 *              values are:
	 *              - `'absolute'` _boolean_: Indicates whether or not the returned URL should be an
	 *                absolute path (i.e. including scheme and host name).
	 *              - `'host'` _string_: If `'absolute'` is `true`, sets the host name to be used,
	 *                or overrides the one provided in `$context`.
	 *              - `'scheme'` _string_: If `'absolute'` is `true`, sets the URL scheme to be
	 *                used, or overrides the one provided in `$context`.
	 * @return string Returns a generated URL, based on the URL template of the matched route, and
	 *         prefixed with the base URL of the application.
	 */
	public function match(Library\HttpUrl $url, $context = null, array $options = array())
	{
		$query = $url->query;
		ksort($query);
		$url->query = $query;

		$match_query_count = count($url->query);
		$match = null;
		$query = array();
		foreach (static::$_templates as $route) {
			$tmp_url = clone $url;
			if (!$route->match($tmp_url, $context)) {
				continue;
			}

			/**
			 * Validate how much of the querystring the route matched, and if it matched more than a previous route
			 * This is done because we're matching only querystring variables, and routes may be defined in any order
			 * We need to find the most specific route, eg:
			 *      /shop <= option=com_shop
			 *      /shop/products =< option=com_shop&view=products
			 *
			 * /shop will be matched first for the url index.php?option=com_shop&view=products, but we have another route
			 * that is more specific, the 2nd route. Thus we need to see how much of each route removes from the querstring
			 * in an attempt to find the best match route
			 **/
			if(count(array_intersect_key($tmp_url->query, $url->query)) < $match_query_count){
				$match_query_count = count($tmp_url->query);
				$match = $tmp_url;

				//If the route is a fallthrough route, extract its querystring parameters
				if($route->canContinue()){
					$query = empty($query) ? array_diff_key($url->query, $match->query) : array_diff_key($match->query, $query);
				}else{
					$query = array_diff_key($match->query, $url->query);
				}

			}
		}

		//If we found a match, set the url to the match
		if($match){
			$url->path = is_array($match->path) ? array_filter($match->path) : explode('/',ltrim($match->path,'/'));
			$url->query = $match->query + $query;
			return true;
		}else{
			return false;
		}
	}


    /**
     * Cleans the APC cache
     * @param string $type - cache type (parse|build/match|routes)
     */
    protected function _clearCache($type = '')
    {
        if(extension_loaded('apc')){

            if($items = apc_cache_info('user')){

                if($type == 'build') $type = 'match';
                $query = $this->_cache_identifier.'-'.$type;
                $length = strlen($query);

                foreach($items['cache_list'] AS $item){
                    if(substr($item['info'], 0, $length) == $query){
                        apc_delete($item['info']);
                    }
                }
            }
        }
    }


	/**
	 * Route loader searches the components directory in all com_ folders looking for a
	 * routes.php or routes.json to include or parse and add routes
	 */
	protected function loadRoutes()
	{
		static $loaded;

        if($loaded) return;

		if(extension_loaded('apc')){
			if($routes = apc_fetch($this->_cache_identifier)){
				self::$_templates = $routes;
				$loaded = true;
				return;
			}
		}

        $bootstrapper   = $this->getObject('object.bootstrapper');
        $components     = $bootstrapper->getComponents();

        //Find all components and look for routes
        foreach($components AS $component){
            $identifier = $this->getIdentifier($component);
            $path       = $bootstrapper->getComponentPath($identifier->package, $identifier->domain);

            $this->loadComponentRoutes($identifier, $path);
        }

        if(extension_loaded('apc')){
            apc_store($this->_cache_identifier, self::$_templates);
        }
	}


    /**
     * Loads the routes for a specific component
     *
     * @param Library\ObjectIdentifier $identifier
     * @param $path
     * @throws
     */
    protected function loadComponentRoutes(Library\ObjectIdentifier $identifier, $path)
    {
        $component = $identifier->package;

        //Skip core components
        if(in_array($component, array('application','router'))) return;

        //If a router already exists, skip
        if(file_exists($path.'/router.php')) return;

        //Ensure the component is dispatchable
        try{
            $dispatcher_identifier = $identifier->toArray();
            $dispatcher_identifier['path'] = array('dispatcher','permission');
            $dispatcher_identifier['name'] = 'http';

            //DispatcherPermissionAbstract does not implement ObjectInterface, so we instantiate manually
            $class = $this->getObject('manager')->getClass($dispatcher_identifier);
            if(!$class || $class == 'Nooku\Library\DispatcherPermissionDefault' || !class_exists($class)) return;

            //Instantiate dispatcher
            $dispatcher_http = new $class(new Library\ObjectConfig);
            if(!$dispatcher_http->canDispatch()) return;
        }catch(\Exception $e){
            return;
        }

        //Check for routes php or json
        if($files = glob($path.'/routes.{php,json}',GLOB_BRACE)){

            foreach($files AS $file)
            {
                if(preg_match('#\.php$#', $file)){

                    //Include the router
                    include_once $file;

                }else{

                    //Read the file and process the params
                    $content = file_get_contents($file);
                    $json = json_decode($content, true);
                    if($json){
                        foreach($json AS $route => $options)
                        {
                            //Setup options
                            if(!is_array($options)){
                                parse_str($options, $params);
                            }else if(isset($options['params'])){
                                $params = $options['params'];
                                unset($options['params']);
                            }else{
                                $params = $options;
                            }

                            $params['component'] = $component;

                            //Connect the route
                            self::connect($route, $params, $options);
                        }
                    }else{
                        throw \UnexpectedValueException('The file '.$file.' is not valid JSON. Ensure the file is formatted correctly (ensure you escape regex patterns)');
                    }
                }
            }
        }else if($this->_auto_create_routes){

            /**
             * Register some default routes.
             * 1 of 2+ routes are registered:
             *      component/views <- plural views
             *      component/view/id <- singular views
             *
             * Model states are used to generate the component/view/X routes
             **/
            $views = glob($path.'/view/*', GLOB_ONLYDIR);
            if(empty($views)) return;

            //Connect the base component route
            $this->connect($component);

            //Connect views
            foreach($views AS &$view_path){
                $view       = basename($view_path);
                $singular   = Library\StringInflector::isSingular($view);
                $states     = $singular ? $this->getComponentViewStates($identifier, $view) : array();

                //If view is the name of component, just add /component
                if($view == $component){
                    $this->connect($component, array('component' => $component, 'view' => $view));
                }else{
                    //Singular views have identifiers
                    if($singular){
                        foreach($states AS $name => $regex){
                            $this->connect($component.'/'.$view.'/{:'.$name.':'.$regex.'}', array('component' => $component, 'view' => $view, $name => null));
                        }
                    }else{
                        $this->connect($component.'/'.$view, array('component' => $component, 'view' => $view));
                    }
                }

                $layouts = glob($view_path.'/templates/*.php');
                foreach($layouts AS $layout){
                    $layout = explode('.',substr(basename($layout), 0, -4));

                    //Layouts must have a format
                    if(count($layout) < 2) continue;

                    array_pop($layout);
                    $layout = implode('.', $layout);

                    //Ignore partials and default
                    if($layout != 'default' && !preg_match('#^_#',$layout) && !preg_match('#^default_#',$layout) && !preg_match('#^form_#',$layout)){

                        if($singular){
                            foreach($states AS $name => $regex){
                                $this->connect($component.'/'.$view.'/{:'.$name.':'.$regex.'}/'.$layout, array('component' => $component, 'view' => $view, $name => null, 'layout' => $layout));
                            }
                            $this->connect($component.'/'.$view.'/'.$layout, array('component' => $component, 'view' => $view, 'layout' => $layout));
                        }else{
                            $this->connect($component.'/'.$view.'/'.$layout, array('component' => $component, 'view' => $view, 'layout' => $layout));
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns the states for a particular view as an array of state name => regex
     *
     * @param Library\ObjectIdentifier $identifier
     * @param $view
     * @return array
     */
    protected function getComponentViewStates(Library\ObjectIdentifier $identifier, $view)
    {
        $states = array();

        $model = $identifier->toArray();
        $model['path'] = array('model');
        $model['name'] = Library\StringInflector::pluralize($view);

        try{
            $model = $this->getObject($model);
        }catch(\Exception $e){
            return array();
        }

        $state = $model->getState();
        foreach($state AS $value){

            if(!$value->unique) continue;

            switch($value->filter){
                case 'word':
                    $regex = '[A-Za-z_]+';
                    break;

                case 'int':
                    $regex = '\d+';
                    break;

                default:
                    $regex = '[A-Za-z0-9.\-_]+';
                    break;
            }

            //Can't have multiple of the same state type for a route
            if(!in_array($regex, $states)) $states[$value->name] = $regex;
        }

        return $states;
    }
}