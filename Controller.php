<?php

/**
 * main/Controller.php
 * "VC" version of Beof
 * @TODO: transfer boeuf.php here
 */

class Controller
{
  public $view;                   // Where to store the data to be rendered
  public $pageTitle = "";         // Title of the rendered page
  public $defaultAction = "";     // Method to run when none specified
  
  const OrderBy = "";
  const HasMany = "";
  const TableName = "";
  const ManyToMany = "";
  const ForeignKey = "";
  const TableSelect = "";
  
	function __construct($id = null)
	{
	  // Child classes should always have the same name as their tables
	  $this->class = get_class($this);
    $this->table = eval("return {$this->class}::TableName;");
    
    $this->view = new MainView();
    
    // Table name not defined, default to class name
    if (!$this->table) {
      $this->table = strtolower($this->class);
    }
    
		$this->result = null;
		
		if ($id) {
		  return $this->get($id);
		}
		
		return $this;
		
	}
	
	/**
	 * Get One Thing
	 */
	public function get($where)
	{
	  if (!is_array($where)) {
	    $where = array('id' => $where);
	  }
	  
    $this->find($where);
    return current($this->data);
	
	}
	
	public function db()
	{
	  return Db::getInstance();
	}
	
	/**
	 * The New Find
	 */
	public function find($where = null, $sort = false, $limit = false)
	{
		$select = "*"; $q = "";
    
		// Allow custom queries
		if (is_array($where))
		{
	    foreach ($where as $k => $v)
			{
				$values[] = $v;
				$args = explode(" ", $k);
				$col = array_shift($args);
				$op = implode(" ", $args);

				// No column name given, default to "id"
	 			if (empty($col)) {
					$col = "id";
				}
				
				// No operator given, default to "="
				if (empty($op)) {
					$op = "=";
				}
				
	      $tmp = (empty($q)) ? ' WHERE ' : ' AND ';
	      $q .= $tmp . $col . ' ' . $op . ' ?';
	      
	    }
	  } else {
			$q = "WHERE `{$this->table}`.`id` = ?";
			$values = array($where);
	  }
		
		if ($where == null) {
      $q = "WHERE ?";
      $values = array(1);
		}
		
//		$schema = App::conf('tables');
//		$this->schema = $schema[$this->table];
		
		// Ugly hack until PHP 5.3
    $i_sort = eval("return {$this->class}::OrderBy;");
    $i_fk = eval("return {$this->class}::ForeignKey;");
    $i_mtm = eval("return {$this->class}::ManyToMany;");
    $i_select = eval("return {$this->class}::TableSelect;");
    
//		$orderBy = ($sort) ? $sort : 
		
		if ($sort) {
			list($col, $dir) = explode(' ', $sort);
			$sort = "ORDER BY `{$this->table}`.`$col` $dir";
		}
		
		if (!$sort && $i_sort) {
			$sort = "ORDER BY `{$this->table}`.{$i_sort}";
		}
		
		if ($i_select) {
			list($select_col, $args) = explode(",", $i_select);
			$select .= ", $args AS `{$select_col}`";
		}
		
		$sql = "SELECT $select FROM `{$this->table}` $q $sort";
    
    if ($limit) {
      $sql .= " LIMIT $limit";
		}
    
		$result = Db::fetch($sql, $values);
		
		for ($i=0; $i < count($result); $i++)
		{
      $row = $result[$i];
			$this->data[$i] = $row;
			$this->find_parent($row, $i);
//			$this->find_children($row, $i);
		}
				
		return $this;
		
	}
	
	/**
	 * Find all child rows for this row
	 * @return void
	 */
	private function find_children($row, $i)
	{
		$id = $row['id']; // ID of the parent
		$fk = explode(",", eval("return $this->class::HasMany;"));
		
		if (empty($fk[0])) {
      return false;
		}
		
		foreach ($fk as $child)
		{
			$sql = "SELECT * FROM `$child` WHERE `{$this->table}_id` = ?";
			
			$ref_schema = App::conf('tables');
			$ref_schema = $ref_schema[$child];
			
			if (@in_array($this->table, $ref_schema['belongsToMany'])) // m/n
			{
				$sql = "SELECT `{$child}`.*, `{$child}_{$this->table}`.*,
					`{$child}_{$this->table}`.id AS {$child}_{$this->table}_id,
					`{$child}`.*
					FROM `{$child}_{$this->table}`, `{$this->table}`, `$child`
					WHERE `{$child}_{$this->table}`.`{$this->table}_id` = `$this->table`.id AND
        	`{$child}_{$this->table}`.`{$child}_id` = `$child`.id AND
        	`{$this->table}`.id = ?
					ORDER BY `{$child}`.{$ref_schema['orderBy']}";
			} else if (@in_array ($table, $ref_schema['belongsTo'])) { // 1/m
					$sql = "SELECT * FROM `$ref` WHERE `$ref`.`{$table}_id` = ?";
			}
      
      $stmt = DB::query($sql, array($id));
			$this->data[$i][$child] = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		}
	}
	
	/**
	 * Find all rows for this row
	 */
	private function find_parent($row, $i)
	{
		$select = "*";
		$fk = explode(",", eval("return {$this->class}::ForeignKey;"));
    
    // No parents defined
    if (empty($fk[0])) {
      return false;
    }
		
		foreach ($fk as $parent)
		{
			$fkey = "id";
			$lkey = "{$parent}_id";
	/*
			if ($this->schema['foreignKey'][$parent])
			{
				list($lkey, $fkey) = explode("|", $this->schema['foreignKey'][$parent]);
			}
	*/
			$parent_id = $row[$lkey];
			
//			$ref_schema = App::conf('tables');
//			$ref_schema = $ref_schema[$parent];
      $ref_schema = $fk[''];
			
			if ($ref_schema['select'])
			{
				foreach ($ref_schema['select'] as $a => $b)
				{
					$select .= ", $b AS `{$a}`";
				}
			}
			
			$sql = "SELECT $select FROM `{$parent}` WHERE `{$fkey}` = ?";
			
      $stmt = DB::query($sql, array($parent_id));
			$this->data[$i][$parent] = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		}
		
	}
	
	private function find_parents()
	{
		
	}
	
	/**
	 * Insert this thing in the DB and return inserted
	 * thing
	 */
	public function insert($data)
	{
		if (empty($data)) {
      return App::error("Nothing to insert");
		}
		
		$insert = "";
		$values = array();
		
		foreach($data as $k => $v) 
		{
			$insert .= "`{$k}`, ";
			$values[":{$k}"] = $v;
		}
		
		$insert = rtrim($insert, ", ");
		$val = implode(", ", array_keys($values));
		$sql = "INSERT INTO `{$this->table}` ({$insert}) VALUES ({$val})";
    
		return DB::query($sql, $values);
		
	}
	
	/**
	 * Delete this thing
	 */
	protected function delete($where, $limit = "")
	{
		if (empty($where)) {
      return App::error("Delete without arguments");
		}
		
		list($key, $value) = each($where);
		
		if ($limit) {
      $limit = " LIMIT $limit";
		}
		
    $data = array(":{$key}" => $value);
		$sql = "DELETE FROM `{$this->table}` WHERE `{$key}` = :{$key} $limit";
    
		return Db::query($sql, $data);
		
	}
	
  /**
   * Update this thing
   * We keep this in the Controller since it might know
   * more about the topmost class
   */ 
	protected function update($data, $where = null)
  {
    if (!is_array($data)) {
      return App::error("Update with empty parameters");
    }
    
    if (empty($where)) {
      $where = array('id' => 'id');
    }
    
    $query = ""; $values = array();
    $data = array_merge($data, $where);
    
    foreach ($data as $k => $v) {
      $query .= "`$k` = :$k, ";
      $values[":{$k}"] = $v;
    }
    
    $query = rtrim($query, ", ");
    list($col, $val) = each($where);
    
    $sql = "UPDATE `{$this->table}` SET $query WHERE `$col` = :$col";
    
    return Db::query($sql, $values);
    
  }
	
	/**
	 * Render a view
	 */
	public function render($data = null, $view = null)
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
		
		$type = App::type();
		$template = "../system/views/default.{$type}";
		$file = "../system/views/{$this->table}/{$view}.{$type}";
		
		if (!is_file($file)) {
			return App::error("{$this->table}_{$view}_{$type}: no such view");
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
	  
	  $title = ($this->pageTitle) ? $this->pageTitle : App::conf("defaults.title");
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
	
	  $sql = rtrim($sql, ",");
	  $sql = "SELECT * FROM `{$this->table}` WHERE MATCH($sql) AGAINST('{$match}')";
    
	  return App::db()->query($sql);
	
  }
  
  /**
   * Insert or update
   */
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
