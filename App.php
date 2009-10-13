<?php

 /**
 * main/App.php
 * @author Filipp Lepalaan <filipp@mekanisti.fi>
 * @copyright 2009 Filipp Lepalaan
 */
 
class App
{
	/**
	 * Fire up the application
	 */
	static public function init()
	{	
		@list($controller, $param, $action) = App::url();
		
		if (empty($param)) {
			$action = "index";
		}
    
//		$conf['basedir'] = dirname(dirname(__FILE__) . "/system");
		
		if (!$controller) {
			$controller = "user";
		}
		
		// Dispatch correct controller
		$c = new $controller;
		
		// Assume no method name was given, try $param, then default to defaultAction
		// controller/param/action
		if (method_exists($c, $action)) {
			return $c->$action($c);
		}
		
		// controller/action
		if (method_exists($c, $param)) {
			return $c->$param($c);
		}
		
		// controller/param
		if (method_exists($c, $c->defaultAction)) {
			$action = $c->defaultAction;
			return $c->$action($c);
		}
		
		exit(App::error("{$controller}_{$action}: no such method"));
		
	}
	
	static function param()
	{
		$url = App::url();
		return $url[1];
	}
	
	// Requests should always be in the form: controller/action/parameters.type
	// Strip type info since it's not needed at this point
	static function url($index = null)
	{
	  $req = ltrim($_SERVER['REQUEST_URI'], "/");
		$array = explode("/", preg_replace('/\.\w+$/', '', $req));
		return (is_numeric($index)) ? $array[$index] : $array;
	}
	
	static function conf($key = null)
	{
	  $cpath = realpath("../system/config.ini");
    $config = parse_ini_file($cpath, true);
    $config = $config['development'];
		return ($key) ? $config[$key] : $config;
	}
	
	static function type()
	{
		$last = array_pop(explode("/", @$_GET['url']));
		$type = ltrim(strrchr($last, "."), ".");
		
		$contentTypes = array('html', 'rss', 'xml', 'tpl', 'pdf', 'jpg');

		if (in_array($type, $contentTypes)) {
			return $type;
		}
		
		return "html";
		
	}
	
	static function ok($msg)
	{
    $ok = array('result' => 'ok', 'msg' => $msg);
    $json = json_encode($ok);
		header("Content-Type: application/json");
		header("Content-Length: " . strlen($json));
		print $json;
	}
	
	static function error($msg)
	{
		$err = array('result' => 'error', 'msg' => $msg);
		$json = json_encode($err);
		header("Content-Type: application/json");
		header("Content-Length: " . strlen($json));
		print $json;
	}
	
	/**
	 * Do a proper HTTP redirect
	 * @param string [$where] URL to redirect to
	 * @return void
	 */
	static function redirect($url)
	{
		header("HTTP/1.1 303 See Other");
		header("Location: $url");
	}
	
	static function locale()
	{
		// Set language to whatever the browser is set to
		list($loc, $lang) = explode("-", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
		return sprintf("%s_%s", $loc, strtoupper($lang));
	}
	
	static function log($msg)
	{
	  if (is_array($msg)) {
      $msg = print_r($msg, true);
	  }
	  syslog(LOG_ERR, $msg);
	}
  
  public function delete($table, $where)
  {
    if (empty($where)) {
      exit(App::error("Delete without parameters"));
    }
    
    list($key, $value) = each($where);
    $sql = "DELETE FROM `$table` WHERE $key = :{$key}";

    self::log($sql);
    self::query($sql, $where);
    
  }
  
  /**
   * Insert something in the database
   */
  static function insert($table, $data)
  {
    if (empty($data)) {
      exit(self::error("Empty insert"));
    }
    
    $cols = array();
    $vals = array();
    
    foreach ($data as $k => $v) {
      $cols[] = "`{$k}`";
      $vals[] = ":{$k}";
    }
    
    $cols = implode(",", $cols);
    $vals = implode(",", $vals);
    $sql = "INSERT INTO `$table` ($cols) VALUES ($vals)";
    
    self::log($sql);
    self::query($sql, $data);
    
  }
  
  // Move this back to Controller once PHP 5.3 is out (get_called_class())
	static function select($table, $where = 1, $what = "*", $order_by = "")
	{
	  $out = array();
	  
	  $query = "?";
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
		
		if (count($out) == 1 && $what != "*") {
      return $out[0][$what];
		}
		
		return $out;
	
	}
	
	public function js($string)
	{
    return '<script type="text/javascript" charset="utf-8">
      ' . $string . '
    </script>';
	}
  
}

	function __autoload($class_name)
	{
	  $class_name = ucfirst($class_name);
    include_once "{$class_name}.php";
    if (!class_exists($class_name)) {
			exit(App::error("{$class_name}: no such class"));
		}
  }
  
?>