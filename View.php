<?php
/**
 * main/MainView.php
 * @created 31.10.2009
 * @author Filipp Lepalaan <filipp@mac.com>
 */
class MainView
{
  /**
   * Create HTML <select> options from array
   * @param array array
   * @param mixed select option with this value
   * @return string
   */
  function select($array, $current = null)
  {
  	$out = '';
	
  	foreach ($array as $k => $v) {
  		$sel = ($k == $current) ? ' selected="selected" ' : '';
  		$out .= "<option value=\"{$k}\"{$sel}>{$v}</option>\n\t";
  	}
	
  	return $out;
	
  }
  
  function tag($name, $args = '', $content = '', $selected = '')
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
	
  	if (is_array ($args))
  	{
  		while (list ($k, $v) = each ($args)) {
  			if (!empty ($k)) $str_args .= ' ' . $k . '="' . $v . '"';
  		}
  	}
	
  	if (is_array ($content))
  	{
  		foreach ($content as $k => $v)
  		{
  			//
  		}
  	} else {
  		//
  	}
	
  	if (empty ($content)) return $out . $str_args . ' />';
		
  	return "{$out}{$str_args}>{$content}</{$name}>\n";
	
  }
  
}

?>