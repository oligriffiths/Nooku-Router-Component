<?php

namespace Oligriffiths\Component\Router;

use Nooku\Library;

/**
 * Routes Model
 *
 * @package Oligriffiths\Component\Router
 */
class ModelRoutes extends Library\ModelDatabase
{
    public function __construct(Library\ObjectConfig $config)
    {
        parent::__construct($config);

        $this->getState()
            ->insert('component', 'string')
            ->insert('view', 'string')
            ->insert('id', 'int')
            ->insert('query', 'string')
            ->insert('route', 'string');

    }

    protected function _buildQueryWhere(Library\DatabaseQuerySelect $query)
    {
        parent::_buildQueryWhere($query);

        $state = $this->getState();

        if ($state->component) {
            $query->where('tbl.component = :component')->bind(array('component' => $state->component));
        }

        if ($state->view) {
            $query->where('tbl.view = :view')->bind(array('view' => $state->view));
        }

        if ($state->id) {
            $query->where('tbl.id = :id')->bind(array('id' => $state->id));
        }

        if ($state->query) {
            $query->where('tbl.query = :query')->bind(array('query' => $state->query));
        }

        if ($state->route) {
            $query->where('tbl.route = :route')->bind(array('route' => $state->route));
        }
    }
}