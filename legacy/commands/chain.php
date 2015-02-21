<?php
/**
 * User: Oli Griffiths
 * Date: 03/01/2013
 * Time: 21:48
 */

namespace Nooku\Component\Router;

use Nooku\Library;

class CommandChain extends Framework\CommandChain
{
	protected function _initialize(Framework\Config $config)
	{
		$config->append(array(
			'break_condition' => true
		));
		parent::_initialize($config);
	}


    /**
     * Returns a rule from the ruleset
     * @param $handle
     * @return bool|mixed
     */
    public function getRule($handle)
    {
        if($this->_object_list->offsetExists($handle)){
            return $this->_object_list->offsetGet($handle);
        }

        return false;
    }
}