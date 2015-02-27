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
class CacheMemory extends CacheDefault
{
    /**
     * Holds the previously built routes
     *
     * @var array
     */
    protected $_build_routes = array();

    /**
     * Holds the previously parsed routes
     *
     * @var array
     */
    protected $_parse_routes = array();

    /**
     * Fetches a route being built
     *
     * @param Library\CommandInterface $command
     * @return bool
     */
    protected function _fetchBuild(Library\CommandInterface $command)
    {
        $identifier = $this->_buildQuerystringIdentifier($command->url->query);

        if(!isset($this->_build_routes[$identifier])) return;

        $command->result = $this->_build_routes[$identifier];

        return true;
    }

    /**
     * Fetches a route being parsed
     *
     * @param Library\CommandInterface $command
     * @return bool
     */
    protected function _fetchParse(Library\CommandInterface $command)
    {
        $identifier = $command->url->toString(Library\HttpUrl::PATH);

        if(!isset($this->_parse_routes[$identifier])) return;

        $command->result = $this->_parse_routes[$identifier];

        return true;
    }

    /**
     * Stores a reoute being built
     *
     * @param Library\CommandInterface $command
     */
    protected function _storeBuild(Library\CommandInterface $command)
    {
        $identifier = $this->_buildQuerystringIdentifier($command->url->query);

        $this->_build_routes[$identifier] = clone $command->result;
    }

    /**
     * Stores a route being parsed
     *
     * @param Library\CommandInterface $command
     */
    protected function _storeParse(Library\CommandInterface $command)
    {
        $identifier = $command->url->toString(Library\HttpUrl::PATH);

        $this->_parse_routes[$identifier] = clone $command->result;
    }

    /**
     * Clears the cache
     *
     * @param Library\CommandInterface $command
     */
    protected function _clearCache(Library\CommandInterface $command)
    {
        $this->_parse_routes = array();
        $this->_build_routes = array();
    }
}