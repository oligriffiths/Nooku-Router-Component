<?php
/**
 * Created By: Oli Griffiths
 * Date: 22/11/2012
 * Time: 17:31
 */

namespace Oligriffiths\Component\Router;

use Nooku\Library;

class ModelPages extends Library\ModelDatabase
{
    protected function _buildQueryWhere(Library\DatabaseQuerySelect $query)
	{
		$view = null;
		if($this->view){
			$view = $this->view;
			$this->view = null;
		}

		parent::_buildQueryWhere($query);

		if($view){
			$query->where('(UPPER(tbl.view) = :view OR ISNULL(tbl.view))')->bind(array('view' => strtoupper($view)));
		}
	}
}