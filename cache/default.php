<?php
/**
 * User: Oli Griffiths
 * Date: 03/01/2013
 * Time: 21:36
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

/**
 * Memory caching.
 */
class CacheDefault extends Library\CommandHandlerAbstract
{
    /**
     * Returns the handle for the rule (the rule name)
     * @return string
     */
    public function getHandle()
    {
        return $this->getIdentifier()->name;
    }

    /**
     * Converts an array into a querystring sorted by key
     *
     * @param array $query
     * @return string
     */
    protected function _buildQuerystringIdentifier(array $query)
    {
        //Ensure component and query come first, required for clean cache pattern matching
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
}