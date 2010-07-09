<?php
////
// main/MainApp.php
// @author Filipp Lepalaan <filipp@mekanisti.fi>
// @copyright (c) 2009 Filipp Lepalaan
class MainApp
{
  ////
  // fire up the application
	static public function init()
	{
	  $url = self::url();
	  
		@list($controller, $param, $action) = $url;
    
    // no controller given, read default one
		if (!$controller) {
		  $controller = self::conf('defaults.controller');
		}
		
		// no action given, read default one
		if (strlen($param) < 1) {
			$action = self::conf('defaults.action');
		}
		
		// fire up the output buffer
		ob_start();
		
		// dispatch correct controller
		$c = new $controller;
		
		// assume no method name was given, try $param, then default to defaultAction
		// controller/param/action
		if (method_exists($c, $action)) {
			return $c->$action($_POST);
		}
		
		// controller/action
		if (method_exists($c, $param)) {
			return $c->$param($_POST);
		}
		
		// controller/param
		if (method_exists($c, $c->defaultAction)) {
			$action = $c->defaultAction;
			return $c->$action($_POST);
		}
		
    self::error("{$controller}/{$action}: no such method");
    
    // release the output buffer
    ob_end_flush();
		
	}
	
  ////
  // requests should always be in the form: controller/action/parameters.type
  // Strip type info since it's not needed at this point
	static function url($index = null)
	{
	  $url = parse_url($_SERVER['REQUEST_URI']);
    
	  if ($index == 'query') {
      return $url['query'];
	  }
	  
	  $req = ltrim($url['path'], '/');
		$array = explode('/', preg_replace('/\.\w+$/', '', $req));
		return (is_numeric($index)) ? $array[$index] : $array;
	
	}
	
  ////
  // return parameter part of URL
	static function param()
	{
		$url = self::url();
		// no parameter given
		if (count($url) < 3) {
      return NULL;
		}
		return $url[1];
	}
	
  ////
  // get configuration data from ini file
	static function conf($key = NULL)
	{
	  $cpath = realpath('../system/config.ini');
    
	  if (!file_exists($cpath)) {
      trigger_error('Failed to open config file', E_USER_ERROR);
      return false;
	  }
	  
    $config = parse_ini_file($cpath, true);
    $config = $config['development'];
    
    if ($key && ! $config[$key]) {
      return self::error('No such config key: '.$key);
    }
    
		return ($key) ? $config[$key] : $config;
	
	}
	
	////
	// determine template type of request
	static function type()
	{
	  $tokens = explode('/', $_SERVER['REQUEST_URI']);
		$last = array_pop($tokens);
		$type = ltrim(strrchr($last, '.'), '.');
    
		$contentTypes = array('html', 'rss', 'xml', 'tpl', 'pdf', 'jpg');
    
		if (in_array($type, $contentTypes)) {
			return $type;
		}
		
		return 'html';
		
	}
	
	static function ok($msg)
	{
    $ok = array('ok' => $msg);
    self::json($ok);
	}
	
	static function error($msg)
	{
	  $err = array('error' => $msg);
	  trigger_error($msg, E_USER_ERROR);
	  // And log it locally
    self::log($msg);
	}

  static function json($msg)
	{
	  $json = json_encode($msg);
		header('Content-Type: application/json');
		header('Content-Length: ' . mb_strlen($json));
		print $json;
	}
	
  ////
  // log an error to our own logging system
  static function log($msg)
	{
	  $file = self::conf('app.error_log');
	  
	  if (!file_exists($file)) {
      exit('Log file does not exist');
	  }
	  
	  $fh = fopen($file, 'a+');
	  
    foreach (func_get_args() as $arg)
    {
      if (is_array($arg) || is_object($arg)) {
        $arg = print_r($arg, true);
  	  }
  	  fwrite($fh, date('r') . "\t" . trim($arg) . "\n");
    }
    fclose($fh);
	}
	
  ////
  // do a proper HTTP redirect
  // @param string [$where] URL to redirect to
  // @return void
	static function redirect($url = null)
	{
	  if (!$url) {
	    // Is it smart to redirect back to the page which redirected here?
	    $url = $_SERVER['HTTP_REFERER'];
	  }
		header('HTTP/1.1 303 See Other');
		header('Location: ' . $url);
	}
	
  ////
  // determine locale from USER_AGENT
	static function locale()
	{
	  if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
      return NULL;
	  }
		// Set language to whatever the browser is set to
		list($loc, $lang) = explode('-', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
		return sprintf('%s_%s', $loc, strtoupper($lang));
	}
  
  public function delete($table, $where)
  {
    if (empty($where)) {
      exit(self::error('Delete without parameters'));
    }
    
    list($key, $value) = each($where);
    $sql = "DELETE FROM `$table` WHERE $key = :{$key}";

    self::log($sql);
    self::query($sql, $where);
    
  }
  
  ////
  // insert something in the database
  static function insert($table, $data)
  {
    if (empty($data)) {
      exit(self::error('Empty insert'));
    }
    
    $cols = array();
    $vals = array();
    
    foreach ($data as $k => $v) {
      $cols[] = "`{$k}`";
      $vals[] = ":{$k}";
    }
    
    $cols = implode(',', $cols);
    $vals = implode(',', $vals);
    $sql = "INSERT INTO `$table` ($cols) VALUES ($vals)";
    
    self::log($sql);
    self::query($sql, $data);
    
  }
  
  // Move this back to Controller once PHP 5.3 is out (get_called_class())
	static function select($table, $where = 1, $what = '*', $order_by = '')
	{
	  $out = array();
	  
	  $query = '?';
	  $values = array(1);
	  
	  if (is_array($where)) {
	    $values = array();
      foreach ($where as $k => $v) {
        $keys[] = "`$k` = :{$k}";
        $values[":{$k}"] = $v;
      }
      $query = implode(" AND ", $keys);
	  }
	  
	  if (!empty($order_by)) {
	    list($ob_col, $ob_dir) = explode(" ", $order_by);
      $order_by = "ORDER BY `$ob_col` $ob_dir";
	  }
	  
		$sql = "SELECT $what FROM `$table` WHERE $query $order_by";
		
		self::log($sql);
		self::log($values);
		
		$stmt = self::db()->prepare($sql);
		$stmt->execute($values);
		
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		  $out[] = $row;
		}
		
		if (count($out) == 1 && $what != '*') {
      return $out[0][$what];
		}
		
		return $out;
	
	}

  ////
  // prompt for HTTP authentication
  // @param string [$callback] Function that makes the actual authentication
  // @param string [$realm] Realm name
  // @return mixed false if cancelled or output of $function
	static function auth($callback, $realm = 'Default')
	{
  	if (!isset($_SERVER['PHP_AUTH_USER']))
  	{
  		header(sprintf('WWW-Authenticate: Basic realm="%s"', $realm));
  		header('HTTP/1.0 401 Unauthorized');
  		return false;
  	} else {
  		return call_user_func($callback, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
  	}
	}
	
  ////
  // output a JavaScript fragment
	public function js($string)
	{
	  header('Content-Type: text/javascript');
    print '<script type="text/javascript" charset="utf-8">
      ' . $string . '
    </script>';
	}
  
}
  
  ////
  // for autoloading the app's classes
	function __autoload($class_name)
	{
	  $class_name = ucfirst($class_name);
    include_once "{$class_name}.php";
    if (!class_exists($class_name)) {
			exit(MainApp::error("{$class_name}: no such class"));
		}
  }
  
?>