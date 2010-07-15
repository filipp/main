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
  
  ////
  // open persistent connection to database
  public static function getInstance()
  {
    $c = MainApp::conf();
		
		if (!self::$instance)
		{
		  try {
		    switch ($c['db.driver']) {
		      case 'mysql':
		        self::$instance = new PDO(
    			    "{$c['db.driver']}:host={$c['db.host']};dbname={$c['db.name']}",
      				$c['db.username'], $c['db.password'], array(PDO::ATTR_PERSISTENT => true)
      			);
      			// always use UTF-8?
    			  self::$instance->query('SET NAMES utf8');
		        break;
		        
		      case 'sqlite':
		        self::$instance = new PDO('sqlite:'.$c['db.path']);
		        break;
		    }
		
		  } catch (PDOException $e) {
			  exit(MainApp::error($e->getMessage()));
		  }
		
    }
  
    return self::$instance;
  
  }
	
  ////
  // deny cloning
  public function __clone()
  {
    trigger_error('Cloning disabled', E_USER_ERROR);
  }
  
  ////
  // execute an SQL query
  // @return mixed
  public static function query($sql, $data = null)
	{
	  if (!$data) {
      $data = array();
	  }
    
	  // might be just a string
	  if (!is_array($data)) {
      $data = array($data);
	  }
	  
    try {
      
      $pdo = self::getInstance();
      $stmt = $pdo->prepare($sql);
      
      if (!$stmt) {
        list($ec, $dec, $emsg) = $pdo->errorInfo();
        $error = $emsg ."\n" . print_r(debug_backtrace(), true);
        return MainApp::error($error);
      }
      
      $result = $stmt->execute($data);
      
      if (!$result) {
        list($ec, $dec, $emsg) = $pdo->errorInfo();
        $error = $emsg ."\n" . print_r(debug_backtrace(), true);
        return MainApp::error($error);
      }
    
    } catch (PDOException $e) {
        $error = $e->getMessage() . $sql;
        $error .= "\n" . print_r(debug_backtrace(), true);
        return MainApp::error($error);
    }
    
    // select statements need the query results
    if (preg_match('/^SELECT/i', $sql)) {
      return $stmt;
    }
    
    // describe statements need the query results
    if (preg_match('/^DESCRIBE/i', $sql)) {
      return $stmt;
    }
    
    // describe statements need the query results
    if (preg_match('/^PRAGMA/i', $sql)) {
      return $stmt;
    }
    
    if (empty($data[':id'])) {
      $data[':id'] = $pdo->lastInsertId();
    }
    
    $out = array();
    
    // Always strip ":" prefixes from input array keys
    foreach ($data as $k => $v) {
      $key = ltrim($k, ':');
      $out[$key] = $v;
    }
    
    return $out;
  
  }
  
  ////
  // fetch something from DB
  public static function fetch($sql, $data = null)
  {
    $stmt = self::query($sql, $data)
      or exit(MainApp::error('Error executing query '.$sql));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
	
}

?>