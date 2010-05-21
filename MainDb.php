<?php
////
// main/MainDb.php
// @author Filipp Lepalaan <filipp@mekanisti.fi>
// http://www.php.net/manual/en/language.oop5.patterns.php
class MainDb
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
    trigger_error("Cloning not work is", E_USER_ERROR);
  }
  
  /**
   * Execute an SQL query
   * @return mixed
   */
  public static function query($sql, $data = null)
	{
	  if (!$data) {
      $data = array();
	  }
	  
	  // Might be just a string
	  if (!is_array($data)) {
      $data = array($data);
	  }
	  
    try {
      
      $pdo = self::getInstance();
      $stmt = $pdo->prepare($sql);
      $result = $stmt->execute($data);
      
      if (!$result) {
        list($ec, $dec, $emsg) = $pdo->errorInfo();
        $error = $emsg ."\n" . print_r(debug_backtrace(), true);
        return App::error($error);
      }
    
    } catch (PDOException $e) {
        $error = $e->getMessage() . $sql;
        $error .= "\n" . print_r(debug_backtrace(), true);
        return App::error($error);
    }
    
    // Select statements need the query results
    if (preg_match('/^SELECT/i', $sql)) {
      return $stmt;
    }
    
    if (empty($data['id'])) {
      $data['id'] = $pdo->lastInsertId();
    }
    
    $out = array();
    
    // Always strip ":" prefixes from input array keys
    foreach ($data as $k => $v) {
      $key = ltrim($k, ':');
      $out[$key] = $v;
    }
    
    return $out;
  
  }
  
  /**
   *
   */
  public static function fetch($sql, $data = null)
  {
    $stmt = self::query($sql, $data)
      or exit(App::error("Error executing query $sql"));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
	
}

?>