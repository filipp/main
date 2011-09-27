<?php
/**
 * main/MainController.php
 * @package main
 * @author Filipp Lepalaan <f@0x00.co>
 * @copyright (c) 2009-2011 Filipp Lepalaan
 * @license
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details.
 */

class MainController
{
  public $view;                     // Where to store the data to be rendered
  public $pageTitle = '';           // Title of the rendered page
  
  protected $template = 'default';  // The base template for this controller (view)
  
  private $defaultAction = '';      // Method to run when none specified
  
  const OrderBy     = '';           // which column to order the results by
  const GroupBy			= '';						// which column to group the results by
  const HasMany     = '';         
  const TableName   = '';
  const ManyToMany  = '';
  const ForeignKey  = '';
  const TableSelect = '';         	// extra fields to select
  
  public $data;                   	// data returned from DB
  private $table;                		// corresponding Db table
  private $primary_key;           	// name of primary key column
  
  private $sql = '';                // the current SQL query
  
  private $sql_select = 'SELECT %s FROM %s %s %s';
  
  private $sql_insert = 'INSERT INTO %s (%s) VALUES (%s)';
  private $sql_update = 'UPDATE %s SET %s';
  private $sql_delete = 'DELETE FROM %s WHERE %s.%s = %s';
  
  ////
  // create controller object
	function __construct($where = NULL)
	{
	  // child classes typically have the same name as their tables
	  // but not always
	  $this->class = get_class($this);
    $this->table = static::TableName;
    
    // table name not defined, default to plural class name
    if (!$this->table) {
      $this->table = strtolower($this->class) . 's';
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
	  $driver = MainApp::conf('db.driver');
	  
	  switch ($driver) {
	    case 'pgsql':
	      $sql = "SELECT column_name 
	      FROM INFORMATION_SCHEMA.COLUMNS 
	      WHERE table_name = '{$this->table}'";
	      
	      $schema = MainDb::fetch($sql);
	      
	      foreach( $schema as $s ) {
	        $this->data[$s['column_name']] = NULL;
	      }
	      
	      break;
	      
	   case 'sqlite':
	    $sql = 'PRAGMA TABLE_INFO('.$this->table.')';
      $schema = MainDb::fetch($sql);
      foreach ($schema as $s) {
        $this->data[$s['name']] = '';
      }
	  break;
	
   case 'mysql':
     $schema = MainDb::fetch('DESCRIBE `'.$this->table.'`');
  	  foreach ($schema as $s) {
  	    $this->data[$s['Field']] = $s['Default'];
     }
     break;
	  }
	  
	  return $this;
	  
	}
	
	/**
	 * Return one thing, without the relatives
	 */
	public function one($where)
	{
	  if (is_numeric($where)) {
      $where = array('id' => $where);
	  }
	  
	  list($key, $value) = each($where);
	  
	  $this->sql = 'SELECT * FROM %s WHERE %s = ? LIMIT 1';
	  $this->sql = sprintf($this->sql, $this->table, $key);

	  return MainDb::one($this->sql, array($value));
	  
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
				
	      $tmp = (empty($q)) ? 'WHERE ' : 'AND';
	      $q .= sprintf(' %s %s %s ?', $tmp, $col, $op);
	      
	    }
	  } else {
			$q = "WHERE {$this->table}.id = ?";
			$values = $where;
	  }
		
		if ($where == NULL) {
      $q = 'WHERE ?';
      $values = 1;
		}
		
    $i_sort = static::OrderBy;
    $i_fk = static::ForeignKey;
    $i_mtm = static::ManyToMany;
    $i_select = static::TableSelect;
		
		if ($sort) {
			list($col, $dir) = explode(' ', $sort);
			$sort = "ORDER BY {$this->table}.{$col} $dir";
		}
		
		if (!$sort && $i_sort) {
			$sort = "ORDER BY {$i_sort}";
		}
		
		if ($i_select) {
			list($select_col, $args) = explode(",", $i_select);
			$select .= ", $args AS {$select_col}";
		}
		
		$this->sql_select = sprintf($this->sql_select, $select, $this->table, $q, $sort);
    
    if ($limit) {
      $this->sql_select .= " LIMIT $limit";
		}
    
		$result = MainDb::fetch($this->sql_select, $values);
    
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
		$id = $row['id']; // ID of this parent
		$fk = explode(',', static::HasMany);
		
		if (empty($fk[0])) {
      return array();
		}
		
		foreach ($fk as $child)
		{
			$group_by = '';
		  $order_by = '';
  		
  		// try to determine table name of child
  		$table = ($child::TableName) ? $child::TableName : $child;
  		
  		if ($ob = $child::OrderBy) {
  		  $order_by = sprintf('ORDER BY %s.%s', $table, $ob);
  		}
  		
  		if ($gb = $child::GroupBy) {
  		  $group_by = sprintf('GROUP BY %s.%s', $table, $gb);
  		}
			
		  // determine nature of relationship
			$one_to_many = explode(',', $child::ForeignKey);
      $many_to_many = explode(',', $child::ManyToMany);
      
      $sql = 'SELECT *';
      
			// allow custom selections
			if ($child::TableSelect) {
				$sql .= ', ' . $child::TableSelect;
			}
			
			$pk = sprintf('%s_id', strtolower(get_class($this)));
      $sql .= " FROM $table WHERE {$pk} = ? $group_by $order_by";
      
			if (in_array($this->table, $many_to_many)) // m/n
			{
				$sql = "SELECT {$table}.*, {$table}_{$this->table}.*,
				{$table}_{$this->table}.id AS {$table}_{$this->table}_id,
				{$table}.*
				FROM {$table}_{$this->table}, {$this->table}, {$table}
				WHERE {$table}_{$this->table}.{$pk} = {$this->table}.id AND
      	{$table}_{$this->table}.{$pk} = {$table}.id AND
      	{$this->table}.id = ?";
			}
			else if (@in_array($table, $ref_schema['belongsTo'])) { // 1/m
			  $sql = "SELECT * FROM `$ref` WHERE `$ref`.`{$table}_id` = ?";
			}
			
      $stmt = MainDb::query($sql, array($id));
      
      if (!$stmt) {
        return MainApp::error('Error executing query ' . $sql);
      }
      
			$this->data[$i][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		}
	}
	
  ////
  // find all parent rows for this row
	private function find_parent($row, $i)
	{
		$select = '*';
		$fk = explode(',', static::ForeignKey);
    
    // No parents defined
    if (empty($fk)) {
      return false;
    }
		
		foreach ($fk as $parent)
		{
			$fkey = 'id';
			$lkey = "{$parent}_id";
			
			// try to determine parent table's table name
			$parent = ($parent::TableName) ? $parent::TableName : $parent;
      
			@$parent_id = $row[$lkey];
      @$ref_schema = $fk[''];
			
			if ($ref_schema['select'])
			{
				foreach ($ref_schema['select'] as $a => $b)
				{
					$select .= ", $b AS {$a}";
				}
			}
			
			$sql = "SELECT $select FROM {$parent} WHERE {$fkey} = ?";
			
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
			$insert .= "{$k}, ";
			$values[":{$k}"] = $v;
		}
		
		$insert = rtrim($insert, ', ');
		$val = implode(', ', array_keys($values));
		
		$this->sql_insert = sprintf($this->sql_insert, $this->table, $insert, $val);
		$seq_id = sprintf( '%s_id_seq', strtolower(get_called_class()) );
    
		return MainDb::query( $this->sql_insert, $values, $seq_id );
		
	}
	
  ////
  // delete This Thing
  // @return whatever MainDb returns
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
		$this->sql = "DELETE FROM {$this->table} WHERE {$key} = :{$key} $limit";
    
		return MainDb::query($this->sql, $data);
		
	}
	
  ////
  // update this Thing
  // We keep this in the Controller since it might know
  // more about the topmost class
  // return the updated Thing
	protected function update($data, $where = NULL)
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
      $query .= "$k = :$k, ";
      $values[':'.$k] = $v;
    }
    
    $query = rtrim($query, ', ');
    $this->sql = sprintf('UPDATE %s SET %s WHERE %s = :%s', $this->table, $query, $col, $col);
    
    return MainDb::query($this->sql, $values);
    
  }
	
	////
  // render a view
	public function render($view = NULL, $data = NULL)
	{
		// default to the same view as the method
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
		@list($c, $p, $m) = MainApp::url();
    
    if (empty($c)) {
      $c = strtolower($this->class);
    }
    
		$template = '../system/views/'.$this->template.'.'.$type;
		$file = "../system/views/{$c}/{$view}.{$type}";
		
		if (!is_file($file)) {
			return MainApp::error("{$c}/{$view}.{$type}: no such view");
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
  // Insert or update
  public function upsert($data, $where = NULL)
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
