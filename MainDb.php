<?php
/**
 * main/MainDb.php
 * @author Filipp Lepalaan <filipp@mekanisti.fi>
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
		      case 'pgsql':
		        self::$instance = new PDO(
    			    "{$c['db.driver']}:host={$c['db.host']};dbname={$c['db.name']}",
      				$c['db.username'], $c['db.password'], array(PDO::ATTR_PERSISTENT => true)
      			);
		        break;
		      case 'sqlite':
		        self::$instance = new PDO('sqlite:'.$c['db.path']);
		        break;
		      default:
		        exit('Unknown db driver: ' . $c['db.driver']);
		        break;
		    }
		
		  } catch (PDOException $e) {
			  exit(MainApp::error($e->getMessage()));
		  }
		
    }
    
    self::$instance->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);
    return self::$instance;
  
  }
	
  ////
  // deny cloning
  public function __clone()
  {
    trigger_error('Cloning disabled', E_USER_ERROR);
  }
  
  public function one($sql, $data = NULL)
  {
    $result = self::fetch($sql, $data);
    return current($result);
  }
  
  ////
  // execute an SQL query
  // @return mixed
  public static function query($sql, $data = NULL, $seq_id = NULL)
	{
	  $args = func_get_args();
	  $sql = array_shift($args);
    
	  if (!is_string($sql)) {
      return FALSE;
	  }
    
	  if (!is_array($data)) {
      $data = $args;
    }

	  // might just be a string
    if (!is_array($data)) {
      $data = array($data);
    }
	  
	  // might be just an empty array
	  if (empty($data)) {
      $data = array();
	  }
	  
    try {
      
      $pdo = self::getInstance();
      $stmt = $pdo->prepare($sql);
      
      if( !$stmt ) {
        list($ec, $dec, $emsg) = $pdo->errorInfo();
        $error = $emsg ."\n" . print_r(debug_backtrace(), TRUE);
        return MainApp::error($error);
      }
      
      $result = $stmt->execute( $data );
      
      if( !$result ) {
        list($ec, $dec, $emsg) = $pdo->errorInfo();
        $error = $emsg ."\n" . print_r(debug_backtrace(), TRUE);
        return MainApp::error($error);
      }
    
    }
    catch( PDOException $e ) {
      
      $error = $e->getMessage() . $sql;
      $error .= "\n" . print_r(debug_backtrace(), TRUE);
      return MainApp::error( $error );
    
    }
    
    // DELETE statements should report number of rows deleted
    if (preg_match('/^DELETE/i', $sql)) {
      return $stmt->rowCount();
    }
    
    // SELECT statements need the query results
    if (preg_match('/^SELECT/i', $sql)) {
      return $stmt;
    }
    
    // describe statements need the query results
    if (preg_match('/^DESCRIBE/i', $sql)) {
      return $stmt;
    }
    
    // pragma statements need the query results (SQLite)
    if (preg_match('/^PRAGMA/i', $sql)) {
      return $stmt;
    }
    
    if( empty( $data[':id'] )) {
      $data[':id'] = $pdo->lastInsertId( $seq_id );
    }
    
    $out = array();
    
    // Always strip ":" prefixes from input array keys
    foreach( $data as $k => $v ) {
      $key = ltrim( $k, ':' );
      $out[$key] = $v;
    }
    
    return $out;
  
  }
  
  ////
  // fetch something from DB
  public static function fetch($sql, $data = NULL)
  {
    $args = func_get_args();
    $sql = array_shift($args);
    
    if (is_array($data)) {
      $args = $data;
    }
    
    $stmt = self::query($sql, $args) or exit(MainApp::error('Error executing query: '.$sql));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    
  }
  
  ////
  // count something
  public static function total($table)
  {
    $sql = 'SELECT COUNT(*) AS the_count FROM %s';
    $res = self::fetch(sprintf($sql, $table));
    $res = current($res);
    return $res['the_count'];
  }
	
}

?>