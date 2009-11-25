<?php
/**
 * xCSS advanced implementation file
 *
 */

define('XCSSCONFIG', '../_config.php');
define('XCSSCLASS', '../xcss-class.php');
include XCSSCONFIG;

$config['path_to_css_dir'] = '../'.$config['path_to_css_dir'];

$update_file = 'last-xcss-update.txt';

$xcss_mod_time = array();
foreach($config['xCSS_files'] as $xcss_file => $css_file)
{
	if(strpos($xcss_file, '*') !== FALSE)
	{
		$xcss_dir = glob($config['path_to_css_dir'].$xcss_file);
		foreach($xcss_dir as $glob_xcss_file)
		{
			$xcss_mod_time[$glob_xcss_file] = filemtime($glob_xcss_file);
		}
	}
	else
	{
		$xcss_mod_time[$xcss_file] = filemtime($config['path_to_css_dir'].$xcss_file);
	}
}

$xcss_time = serialize($xcss_mod_time);

if( ! file_exists($update_file) || file_get_contents($update_file) !== $xcss_time)
{
	include XCSSCLASS;
	
	$xCSS = new xCSS($config);
	
	echo '<script type="text/javascript">'."\n";
	$xCSS->compile();
	$xCSS->create_file($xcss_time, $update_file, './');
	unset($xCSS);
	echo '</script>'."\n";
}