<?php
/**
 * User: Oli Griffiths <oli@expandtheroom.com>
 * Date: 2/11/13
 * Time: 10:23 AM
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

class HelperPages extends Library\Object implements Library\ObjectInstantiable
{
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
        }

        return $manager->getObject($config->object_identifier);
    }


    /**
     * Finds the itemid for a component & view pair
     * First looking in the DB table
     * @param $component
     * @param $view
     * @return mixed
     */
    public function findPageId($query)
    {
        static $ids = array();

        ksort($query);
        $querystring = http_build_query($query);
        if(!isset($ids[$querystring])){

            $page = $this->findPage($query);
            $ids[$querystring] = $page ? $page->id : false;
        }

        return $ids[$querystring];
    }


    /**
     * Finds the page for a component & view pair
     * @param $component
     * @param $view
     * @return mixed
     */
    public function findPage($query)
    {
        static $ids = array();

        ksort($query);
        $querystring = http_build_query($query);

        if(!isset($ids[$querystring])){

            $pages = $this->getObject('application.pages');

            $view       = isset($query['view']) ? $query['view'] : null;
            $itemid     = isset($query['Itemid']) ? $query['Itemid'] : null;
            $match      = null;
            $view_plural= Library\StringInflector::pluralize($view);

            if(!$match = $pages->find($itemid)){

                //Need to reverse the array (highest sublevels first)
                $reverse = array_reverse($pages->toArray());
                $param_count = 0;

                foreach($reverse AS $page)
                {
                    $page   = $pages->getPage($page['id']);
                    $link   = $page->getLink();
                    if($link){
                        $link = clone $link;
                        unset($link->query['Itemid']);

                        if(count($link->query) && array_intersect_key($query, $link->query) == $link->query && count($link->query) > $param_count){
                            $match = $page;
                            $param_count = count($link->query);
                        }
                    }
                }

                $param_count = 0;

                //Try plural views
                if(!$match && $view_plural != $view){
                    foreach($reverse AS $page)
                    {
                        $page   = $pages->getPage($page['id']);

                        $q = $query;
                        $q['view'] = $view_plural;
                        $link = $page->getLink();

                        if($link){
                            $link = clone $link;
                            unset($link->query['Itemid']);

                            if(count($link->query) && array_intersect_key($q, $link->query) == $link->query && count($link->query) > $param_count){
                                $match = $page;
                                $param_count = count($link->query);
                            }
                        }
                    }
                }
            }

            $ids[$querystring] = $match;
        }

        return $ids[$querystring];
    }
}