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
        $success = null;

		// Use the custom routing handler if it exists
		if (isset($command->url->query['component']))
		{
			//Get the router identifier
			$identifier = 'com:'.$command->url->query['component'].'.router';

			try{
				//Build the view route
				$router = $this->getObject($identifier, array('router' => $this));
                $segments = $router->build($command->result);

				if(is_array($segments) && count($segments)){

                    $command->result->path = array_merge($segments, $command->result->getPath(true));
                    $success = true;
				}
			}catch(\Exception $e){}
		}

        parent::_buildRoute($command);

        return $success;
	}


    /**
     * Parse route via the page rule first as there is no other way to determine 'component' at this point.
     * If option is defined, parse via the component router
     * @param Library\CommandInterface $command
     * @return bool
     */
    protected function _parseRoute(Library\CommandInterface $command)
	{
        parent::_parseRoute($command);

        $route = $command->result->path;

        if(isset($command->result->query['component']) && !empty($route))
        {
            try{
                //Get the router identifier
                $identifier = 'com:'.$command->result->query['component'].'.router';

                //Parse the view route
                $vars = $this->getObject($identifier)->parse($command->result);

                //Merge default and vars
                $command->result->query = array_merge($command->result->query, $vars);

                return true;

            }catch(\Exception $e){}
        }
	}
}