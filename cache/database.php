<?php
/**
 * User: Oli Griffiths
 * Date: 03/01/2013
 * Time: 21:36
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

/**
 * Database routing rule
 * Looks for routes in a database table and stores routes to the database if a cache flag is set in the context
 */
class RuleDatabase extends RuleDefault
{
    /**
     * Builds a route from the database #__router_routes table. If match found, match is used and execution stops
     * @param Library\CommandContext $context
     * @return bool
     */
    protected function _routerBuild(Library\CommandContext $context)
	{
		$url = $context->url;
		$query = $url->query;

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
		unset($query['format']);
		unset($query['Itemid']);

		ksort($query);
		$querystring = http_build_query($q).'&'.http_build_query($query);

		if(!isset($routes[$querystring])){
			$routes[$querystring] = false;
            $state = $q;
            $state['query'] = http_build_query($query);

            try{
                if($route = $this->getObject('com://site/router.model.routes')->set($state)->getRow()){
                    if(!$route->isNew()){
                        $tmp = $this->getObject('lib://nooku/http.url', array('url' => ltrim($route->route,'/').'?'.$route->query));
                        if($route->page_id) $tmp->query['Itemid'] = $route->page_id;
                        $routes[$querystring] = $tmp;
                    }
                }
            }catch(\Exception $e){

            }
		}

		if($route = $routes[$querystring]){

			//Remove the querystring parts from the route
			$context->result->path = $route->path;
			$context->result->query = array_diff_key($url->query, $route->query);

			return true;
		}
	}


    /**
     * Build route post-processor, stores a route to the database if the context is set to be cached
     * @param Library\CommandContext $context
     */
    protected function _routerAfterBuild(Library\CommandContext $context)
    {
        if($context->cache){
            $url = clone $context->url;
            $result = clone $context->result;
            $query = array_diff_key($url->query, $result->query);

            //Ensure option and query come first, required for clean cache
            $q = array();
            $page_id = null;
            if(isset($query['component'])){
                $q['component'] = $query['component'];
                unset($query['component']);
            }
            if(isset($query['view'])){
                $q['view'] = $query['view'];
                unset($query['view']);
            }
            if(isset($query['Itemid'])){
                $page_id = $query['Itemid'];
            }
            unset($query['Itemid']);
            unset($query['format']);

            ksort($query);

            try{
                $state = $q;
                $state['query'] = http_build_query($query);

                $item = $this->getObject('com://site/router.model.routes')->set($state)->getRow();
                if($item && $item->isNew()){
                    $data = $state;
                    $data['component'] = $data['component'];
                    $data['route'] = $result->getPath();
                    $data['query'] = $state['query'];

                    if($page_id) $data['page_id'] = $page_id;

                    $this->getObject('com://site/router.controller.route')->add($data);
                }
            }catch(\Exception $e){

            }
        }
    }


    /**
     * Parse a route and from the database #__router_routes table, if match found, use and stop execution
     * @param Library\CommandContext $context
     * @return bool
     */
    protected function _routerParse(Library\CommandContext $context)
	{
		$url = $context->url;
		try{
			$model = $this->getObject('com://site/router.model.routes');

			$path = $url->getPath();
			$item = $model->set('route',$path)->getRow();

			//If item was not found, check aliases,
            //@TODO: this should really be an HTTP redirect, need to find out the best way to do that
			if($item->isNew()){
				$alias = $this->getObject('com://site/router.model.aliases')->set('source',$path)->getRow();
				if($alias && !$alias->isNew()){
					$item = $model->set('route',$alias->target)->getRow();
				}
			}

			//Parse & set matching route
			if(!$item->isNew()){
				parse_str($item->query, $query);
                $query['component'] = $item->component;
                $query['view'] = $item->view;
				$context->result->query = array_merge($query, $url->query);
				if($item->page_id && !isset($context->result->query['Itemid'])) $context->result->query['Itemid'] = $item->page_id;

				return true;
			}
		}catch(\Exception $e){

		}
	}
}