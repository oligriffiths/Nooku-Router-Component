<?php
/**
 * User: Oli Griffiths
 * Date: 14/10/2012
 * Time: 12:41
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

class RouterDefault extends Library\DispatcherRouter
{
	protected $_root;

	protected $_default_layout;

	protected $_router;

	/**
	 * @param Library\ObjectConfig $config
	 */
	public function __construct(Library\ObjectConfig $config = null)
	{
		parent::__construct($config);

		$this->_root = $config->root;
	}


	/**
	 * @param Library\ObjectConfig $config
	 */
	protected function _initialize(Library\ObjectConfig $config)
	{
		$config->append(array(
			'root' => false,
			'cache' => false,
			'default_layout' => 'default'
		));

		parent::_initialize($config);
	}


	/**
	 * Main route builder method
	 * @param $query
	 * @return array
	 */
	public function buildRoute(&$query, Library\HttpUrl $url)
	{
		$segments = array();

		//Set the root if set
		if($this->_root){
			$segments[] = $this->_root;
		}

		$view   = isset($query['view']) ? $query['view'] : null;

		//Set view
		if($view)
		{
			$route = $this->buildViewRoute($view, $query, $url);
			if(is_array($route)){
				$segments = array_merge($segments, $route);
			}else{
				$segments[] = $view;
				unset($query['view']);
			}
		}

		//Set id
		if(isset($query['id'])){
			$segments[] = $query['id'];
			unset($query['id']);
		}

		//Set the layout
		if(isset($query['layout'])){
			if($query['layout'] != $this->getConfig()->default_layout) $route[] = $query['layout'];
			unset($query['layout']);
		}

		return $segments;
	}


    /**
     * Calls a build view method for the specified view
     * @param $view
     * @param $query
     * @param Library\HttpUrl $url
     * @return array|null
     */
    protected function buildViewRoute($view, &$query, Library\HttpUrl $url)
	{
		$method = '_buildView'.ucfirst(strtolower($view));
		$return = method_exists($this, $method) ? $this->$method($query, $url) : null;
		if(is_array($return)){
			unset($query['view']);
		}

		return $return;
	}


    /**
     * Returns if the route is cacheable to the database
     * @return mixed
     */
    public function canCache()
	{
		return $this->getConfig()->cache;
	}
}