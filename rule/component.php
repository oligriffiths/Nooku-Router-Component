<?php
/**
 * User: Oli Griffiths
 * Date: 03/01/2013
 * Time: 21:36
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

/**
 * Component routing rule.
 * Builds/parses routes using the components router
 */
class RuleComponent extends RulePage
{
    /**
     * Build route using a component router if found.
     * Once built, fall back to building via the page rule
     * @param Library\CommandInterface $command
     */
    protected function _buildRoute(Library\CommandInterface $command)
	{
		$url = clone $command->url;
        $success = null;

		// Use the custom routing handler if it exists
		if (isset($url->query['option']))
		{
			//Get the router identifier
			$identifier = 'com:'.substr($url->query['option'], 4).'.router';

			try{
				//Build the view route
				$router = $this->getObject($identifier, array('router' => $this));

				if(false !== $segments = $router->build($url, $command->original)){

                    if(is_array($segments) && count($segments)){
                        $command->url->path = array_merge($segments, $command->url->path);
                    }

                    if(method_exists($router, 'canCache')){
                        $command->cache = $router->canCache($command);
                    }

                    $success = true;
				}
			}catch(\Exception $e){}
		}

        parent::_buildRoute($command);

        return $success;
	}


    /**
     * Parse route via the page rule first as there is no other way to determine 'option' at this point.
     * If option is defined, parse via the component router
     * @param Library\CommandInterface $command
     * @return bool
     */
    protected function _parseRoute(Library\CommandInterface $command)
	{
        parent::_parseRoute($command);

        $route = $command->url->path;

        if(isset($command->url->query['option']) && !empty($route))
        {
            try{
                //Get the router identifier
                $identifier = 'com:'.substr($command->url->query['option'], 4).'.router';

                //Parse the view route
                $vars = $this->getObject($identifier)->parse($command->url);

                //Merge default and vars
                $command->url->query = array_merge($command->url->query, $vars);

                return true;

            }catch(\Exception $e){}
        }
	}
}