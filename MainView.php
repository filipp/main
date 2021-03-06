<?php
/**
 * main/MainView.php
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

class MainView
{
  ////
  // include something within the project tree
  function snippet($path)
  {
    $base = dirname(__FILE__).'/../..';
    $base = realpath($base);
    include $base.'/'.$path;
  }
  
  ////
  // render a table
  function table($data, $cols = null)
  {
    
  }
  
  ////
  // create HTML <select> options from array
  // @param array array
  // @param mixed select option with this value
  // @return string
  function select($array, $current = null)
  {
  	$out = '';
  	
  	foreach ($array as $k => $v) {
  		$sel = ($k == $current) ? ' selected="selected"' : '';
  		$out .= "<option value=\"{$k}\"{$sel}>{$v}</option>\n\t";
  	}
	
  	return $out;
	
  }
  
  function checkbox($name, $value, $checked = FALSE, $params = NULL)
  {
    $checked = ($checked) ? ' checked="checked"' : '';
    $html = '<input type="checkbox" name="'.$name.'" value="'.$value.'"'.$checked.'/>';
    return $html;
  }
  
  // $this->mainView->form('/some/save')->
  function action($action)
  {
    $port = ($_SERVER['SERVER_PORT'] > 80) ? ':'.$_SERVER['SERVER_PORT'] : '';
    $base = str_replace('index.php', '', $_SERVER['PHP_SELF']);
    return 'action="'.$base.$action.$port.'"';
  }
  
  function form($action)
  {
    $port = ($_SERVER['SERVER_PORT'] > 80) ? ':'.$_SERVER['SERVER_PORT'] : '';
    $out = '<form action="'.$action.$port.'" accept-charset="utf-8"';
  }
  
  function clean($string)
  {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
  }
  
  function input($params)
  {
    return $this->tag('input', $params);
  }
  
  /**
   * Create an HTML tag
   */
  function tag($name, $args = '', $content = FALSE, $selected = '')
  {
  	$str_args = '';
  	$out = '<' . $name;
	
  	// special treatment for certain tags
  	switch ($name)
  	{
  		case 'form':
  			break;
  		case 'img':
  			if (empty ($args['alt'])) $args['alt'] = 'Image';
  			break;
  	}
	
  	if (is_array($args))
  	{
  		while (list($k, $v) = each($args)) {
  			if (!empty($k)) $str_args .= ' ' . $k . '="' . $v . '"';
  		}
  	}
	
  	if (is_array($content))
  	{
  		foreach($content as $k => $v)
  		{
  			//
  		}
  	} else {
  		//
  	}
	  
  	if ($content === FALSE) {
  	  return $out . $str_args . ' />';
  	}
		
  	return "{$out}{$str_args}>{$content}</{$name}>\n";
	
  }
  
}

?>