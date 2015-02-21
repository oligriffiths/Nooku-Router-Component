<?php
/**
 * User: Oli Griffiths
 * Date: 03/01/2013
 * Time: 21:34
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

/**
 * Default routing rule
 * Ensures the rule is a singleton
 */
class RuleDefault extends Library\CommandHandlerAbstract
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
     * Returns the handle for the rule (the rule name)
     * @return string
     */
    public function getHandle()
	{
		return $this->getIdentifier()->name;
	}
}