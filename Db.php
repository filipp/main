<?php

/**
 * main/Db.php
 * @author Filipp Lepalaan <filipp@mekanisti.fi>
 * http://www.php.net/manual/en/language.oop5.patterns.php
 */

class Db
{ 
  private static $instance = NULL;
  
  private function __construct()
  {
    
  }
  
	/**
	 * Open persistent connection to database
	 */
  public static function getInstance()
  {
    $c = App::conf();
		
		if (!self::$instance)
		{
		  try {
			  self::$instance = new PDO(
			    "{$c['db.driver']}:host={$c['db.host']};dbname={$c['db.name']}",
  				$c['db.username'], $c['db.password'], array(PDO::ATTR_PERSISTENT => true)
  			);
  			
			  self::$instance->query("SET NAMES utf8");
		
		  } catch (PDOException $e) {
			  exit(App::error($e->getMessage()));
		  }
		
    }
  
    return self::$instance;
  
  }
	
	/**
	 * Deny cloning
	 */
  public function __clone()
  {
    trigger_error("Hello, ich name was Singleton. Cloning is not allowed", E_USER_ERROR);
  }
  
  /**
   * Execute an SQL query
   * @return mixed
   */
  public function query($sql, $data = null)
	{
	  if (!$data) {
      $data = array();
	  }
	  
	  // Might be just a string
	  if (!is_array($data)) {
      $data = array($data);
	  }
	  
    try {
    
      $stmt = self::getInstance()->prepare($sql);
      $result = $stmt->execute($data);
      
      if (!$result) {
        $e = $stmt->errorInfo();
        exit(App::error($e[2]));
      }
    
    } catch (PDOException $e) {
        $error = $e->getMessage() . $sql;
        App::log($error);
        exit(App::error($error));
    }
    
    // Select statements need the query results
    if (preg_match('/^select/i', $sql)) {
      return $stmt;
    }
    
    if (empty($data['id'])) {
      $data['id'] = self::getInstance()->lastInsertId();
    }
    
    return $data;
  
  }
  
  public function fetch($sql, $data = null)
  {
    $stmt = DB::query($sql, $data);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
	
}

?>