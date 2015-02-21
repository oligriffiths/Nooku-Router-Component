<?php
/**
 * User: Oli Griffiths
 * Date: 03/01/2013
 * Time: 21:36
 */

namespace Nooku\Component\Router;

use Nooku\Library;

/**
 * Page routing rule
 * Builds/parses routes using the pages component
 */
class RulePage extends RuleDefault
{
    /**
     * Build route using the pages component. Attempts to locate the page id if no Itemid is defined.
     * If itemid is defined, the route is built using that pages' path.
     * @param Library\CommandInterface $context
     */
    protected function _buildRoute(Library\CommandInterface $context)
    {
        $url = clone $context->url;

        //If not set, default to active menu item
        if(!isset($url->query['Itemid'])){
            $page = $this->getObject('application.pages')->getActive();
            if($page) {
                $url->query['Itemid'] = $page->id;
            }
        }

        //Get page route
        if(isset($url->query['Itemid']))
        {
            if($page = $this->getObject('application.pages')->getPage($url->query['Itemid'])){

                $link = $page->getLink();
                if($link && isset($link->query['option']) && $link->query['option'] == $url->query['option']) {

                    $route = explode('/',$page->route);
                    $context->url->path = $page->home ? $context->url->path : array_merge($route, $context->url->path);
                    $context->url->query = array_diff_key($context->url->query, $link->query);

                    unset($context->url->query['Itemid']);
                }
            }
        }
    }


    /**
     * Parse a route using the page component.
     * Routes are matched in reverse to attempt to match highest sublevels first
     * @param Library\CommandInterface $context
     */
    protected function _parseRoute(Library\CommandInterface $context)
    {
        $url        = clone $context->url;
        $route      = $url->getPath();
        $pages      = $this->getObject('application.pages');
        $reverse    = array_reverse($pages->toArray());

        //Set the default
        $page = $pages->getHome();

        //Find the page
        if(!empty($route))
        {
            //Need to reverse the array (highest sublevels first)
            foreach($reverse as $tmp)
            {
                $tmp    = $pages->getPage($tmp['id']);
                $length = strlen($tmp->route);

                if($length > 0 && strpos($route.'/', $tmp->route.'/') === 0 && $tmp->type != 'pagelink')
                {
                    $page      = $tmp; //Set the page
                    $url->path = ltrim(substr($route, $length), '/');
                    break;
                }
            }
        }

        //Set the page information in the route
        if($page && $page->type != 'redirect')
        {
            if($link = $page->getLink()) $context->url->setQuery($link->query, true);
            $url->query['Itemid'] = $page->id;
        }

        $pages->setActive($page->id);
    }
}