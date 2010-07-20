<?php
////
// main/MainController.php
// @TODO: transfer boeuf.php here
class MainController
{
  public $view;                   // Where to store the data to be rendered
  public $pageTitle = '';         // Title of the rendered page
  
  public $defaultAction = '';     // Method to run when none specified
  
  const OrderBy     = '';
  const HasMany     = '';
  const TableName   = '';
  const ManyToMany  = '';
  const ForeignKey  = '';
  const TableSelect = '';         // extra fields to select
  
  public $data;
  private $table;
  
  ////
  // create controller object
	function __construct($where = null)
	{
	  // child classes typically have the same name as their tables
	  // but not always
	  $this->class = get_class($this);
    $this->table = static::TableName;
    
    // table name not defined, default to class name
    if (!$this->table) {
      $this->table = strtolower($this->class);
    }
    
    $this->mainView = new MainView();
    
		if ($where) {
		  return $this->get($where);
		}
		
		return $this;
		
	}
	
	////
  // Get One Thing
	public function get($where)
	{
	  if (!is_array($where)) {
	    $where = array('id' => $where);
	  }
	  
    $this->find($where);
    
    if (!is_array($this->data)) {
      return false; // found nothing
    }
    
    if (count($this->data) == 1) {
      return current($this->data);
    }
    
	  return $this->data;
	  
	}
	
	////
	// Initialize the fields of a class
	// with the table's default values
	public function init()
	{
	  // populate indices
	  if ($_SESSION['config']['db.driver'] == 'mysql') {
	    $schema = MainDb::fetch('DESCRIBE `'.$this->table.'`');
	    foreach ($schema as $s) {
        $this->data[$s['Field']] = $s['Default'];
      }
	  }
	  
	  if ($_SESSION['config']['db.driver'] == 'sqlite') {
	    $sql = 'PRAGMA TABLE_INFO('.$this->table.')';
      $schema = MainDb::fetch($sql);
      foreach ($schema as $s) {
        $this->data[$s['name']] = '';
      }
    }
	}
	
  ////
  // the New Find
	public function find($where = null, $sort = false, $limit = false)
	{
	  $q = '';
		$select = '*';
    $this->data = array();
    
		// allow custom queries
		if (is_array($where))
		{
	    foreach ($where as $k => $v)
			{
				$values[] = $v;
				$args = explode(' ', $k);
				$col = array_shift($args);
				$op = implode(' ', $args);

				// No column name given, default to "id"
	 			if (empty($col)) {
					$col = 'id';
				}
				
				// No operator given, default to "="
				if (empty($op)) {
					$op = '=';
				}
				
	      $tmp = (empty($q)) ? ' WHERE ' : ' AND ';
	      $q .= $tmp . "`{$col}`" . ' ' . $op . ' ?';
	      
	    }
	  } else {
			$q = "WHERE `{$this->table}`.`id` = ?";
			$values = $where;
	  }
		
		if ($where == NULL) {
      $q = 'WHERE ?';
      $values = 1;
		}
		
//		$schema = App::conf('tables');
//		$this->schema = $schema[$this->table];
		
		// Ugly hack until PHP 5.3
    $i_sort = static::OrderBy;
    $i_fk = static::ForeignKey;
    $i_mtm = static::ManyToMany;
    $i_select = static::TableSelect;
		
		if ($sort) {
			list($col, $dir) = explode(' ', $sort);
			$sort = "ORDER BY `{$this->table}`.`$col` $dir";
		}
		
		if (!$sort && $i_sort) {
			$sort = "ORDER BY {$i_sort}";
		}
		
		if ($i_select) {
			list($select_col, $args) = explode(",", $i_select);
			$select .= ", $args AS `{$select_col}`";
		}
		
		$sql = "SELECT $select FROM `{$this->table}` $q $sort";
    
    if ($limit) {
      $sql .= " LIMIT $limit";
		}
    
		$result = MainDb::fetch($sql, $values);
    
		if (empty($result)) {
		  return $this->data = array();
		}
		
		for ($i=0; $i < count($result); $i++)
		{
      $row = $result[$i];
			$this->data[$i] = $row;
			if (static::ForeignKey) {
			  $this->find_parent($row, $i);
			}
			if (static::HasMany) {
			  $this->find_children($row, $i);
			}
		}
				
		return $this->data;
		
	}
	
  ////
  // find all child rows for this row
  // @return void
	private function find_children($row, $i)
	{
		$id = $row['id']; // ID of the parent
		$fk = explode(',', static::HasMany);
		
		if (empty($fk[0])) {
      return array();
		}
		
		foreach ($fk as $child)
		{
		  $order_by = '';
		  
  		if ($child::OrderBy) {
  		  $order_by = sprintf('ORDER BY `%s`.%s', $child, $child::OrderBy);
  		}
  		
		  // determine nature of relationship
			$one_to_many = explode(',', eval("return $child::ForeignKey;"));
      $many_to_many = explode(',', eval("return $child::ManyToMany;"));
      $sql = "SELECT * FROM `$child` WHERE `{$this->table}_id` = ? $order_by";
      
			if (in_array($this->table, $many_to_many)) // m/n
			{
				$sql = "SELECT `{$child}`.*, `{$child}_{$this->table}`.*,
					`{$child}_{$this->table}`.id AS {$child}_{$this->table}_id,
					`{$child}`.*
					FROM `{$child}_{$this->table}`, `{$this->table}`, `$child`
					WHERE `{$child}_{$this->table}`.`{$this->table}_id` = `$this->table`.id AND
        	`{$child}_{$this->table}`.`{$child}_id` = `$child`.id AND
        	`{$this->table}`.id = ?";
			} else if (@in_array($table, $ref_schema['belongsTo'])) { // 1/m
					$sql = "SELECT * FROM `$ref` WHERE `$ref`.`{$table}_id` = ?";
			}
      
      $stmt = MainDb::query($sql, array($id));
      if (!$stmt) {
        return MainApp::error('Error executing query ' . $sql);
      }
      
			$this->data[$i][$child] = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		}
	}
	
  ////
  // find all parent rows for this row
	private function find_parent($row, $i)
	{
		$select = '*';
		$fk = explode(',', eval("return {$this->class}::ForeignKey;"));
    
    // No parents defined
    if (empty($fk[0])) {
      return false;
    }
		
		foreach ($fk as $parent)
		{
			$fkey = 'id';
			$lkey = "{$parent}_id";
	/*
			if ($this->schema['foreignKey'][$parent])
			{
				list($lkey, $fkey) = explode("|", $this->schema['foreignKey'][$parent]);
			}
	*/
			@$parent_id = $row[$lkey];
			
//			$ref_schema = App::conf('tables');
//			$ref_schema = $ref_schema[$parent];
      @$ref_schema = $fk[''];
			
			if ($ref_schema['select'])
			{
				foreach ($ref_schema['select'] as $a => $b)
				{
					$select .= ", $b AS `{$a}`";
				}
			}
			
			$sql = "SELECT $select FROM `{$parent}` WHERE `{$fkey}` = ?";
			
      $stmt = MainDb::query($sql, array($parent_id));
			$this->data[$i][$parent] = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		}
		
	}
	
	////
  // insert this thing in the DB and return inserted Thing
	public function insert($data)
	{
		if (empty($data)) {
      return MainApp::error('Cannot insert emptiness');
		}
		
		$insert = '';
		$values = array();
		
		if (@empty($data['id'])) {
		  unset($data['id']);
		}
		
		foreach($data as $k => $v) {
			$insert .= "`{$k}`, ";
			$values[":{$k}"] = $v;
		}
		
		$insert = rtrim($insert, ', ');
		$val = implode(', ', array_keys($values));
		$sql = "INSERT INTO `{$this->table}` ({$insert}) VALUES ({$val})";
		
		return MainDb::query($sql, $values);
		
	}
	
  ////
  // delete This Thing
	public function delete($where, $limit = '')
	{
		if (empty($where)) {
      return MainApp::error('Cannot delete without arguments');
		}
		
		if (!is_array($where)) {
      $where = array('id' => $where);
		}
		
		list($key, $value) = each($where);
		
		if ($limit) {
      $limit = " LIMIT $limit";
		}
		
    $data = array(":{$key}" => $value);
		$sql = "DELETE FROM `{$this->table}` WHERE `{$key}` = :{$key} $limit";
    
		return MainDb::query($sql, $data);
		
	}
	
  ////
  // update this Thing
  // We keep this in the Controller since it might know
  // more about the topmost class 
	protected function update($data, $where = null)
  {
    if (!is_array($data)) {
      return MainApp::error('Cannot update without parameters');
    }
    
    if (empty($where)) {
      $where = array('id' => 'id');
    }
    
    $query = '';
    $values = array();
    list($col, $val) = each($where);
    
    if (!isset($data[$col])) {
      $data = array_merge($data, $where);
    }
    
    foreach ($data as $k => $v) {
      $query .= "`$k` = :$k, ";
      $values[":{$k}"] = $v;
    }
    
    $query = rtrim($query, ', ');
    $sql = "UPDATE `{$this->table}` SET $query WHERE `$col` = :$col";
    
    return MainDb::query($sql, $values);
    
  }
	
	////
  // render a view
	public function render($view = null, $data = null)
	{
		// Default to the same view as the method
		if (!$view) {
			$bt = debug_backtrace();
			$view = $bt[1]['function'];
		}
		
		if (!$view) {
		  $view = $this->defaultAction;
		}
		
		if (!$data) {
			$data = $this->view;
		}
		
		$type = MainApp::type();
    
    $controller = strtolower($this->class);
		$template = "../system/views/default.{$type}";
		$file = "../system/views/{$controller}/{$view}.{$type}";
		
		if (!is_file($file)) {
			return MainApp::error("{$controller}/{$view}.{$type}: no such view");
		}
		
		if ($data) {
      foreach ($data as $k => $v) {
		    $$k = $v;
		  }
		}
		
		// Capture view
	  ob_start();
		include $file;
	  $view_contents = ob_get_contents();
	  ob_end_clean();
		
		// Capture template
		ob_start();
		include $template;
	  $tpl_contents = ob_get_contents();
	  ob_end_clean();
	  
	  $title = ($this->pageTitle) ? $this->pageTitle : MainApp::conf('defaults.title');
	  $tpl_contents = preg_replace(
	    '/<title>.*?<\/title>/', "<title>{$title}</title>", $tpl_contents
	  );
	  
		echo str_replace('%%page_content%%', $view_contents, $tpl_contents);
		
	}

  public function match($cols, $match)
  {
	  foreach ($cols as $col) {
		  $sql .= "`{$col}`,";
	  }
	
	  $sql = rtrim($sql, ',');
	  $sql = "SELECT * FROM `{$this->table}` WHERE MATCH($sql) AGAINST('{$match}')";
    
	  return MainDb::fetch($sql);
	
  }
  
  ////
  // insert or update
  public function upsert($data, $where = null)
  {
    if(!$this->get($where)) {
      $out = $this->insert($data);
    } else {
      $out = $this->update($data, $where);
    }
    
    return $out;
    
  }
	
}

?>
