<?php
/**
 * xCSS_compiler class
 *
 * @author     Anton Pawlik
 * @copyright  (c) 2009 Anton Pawlik
 * @see        http://xcss.antpaw.org/
 */

error_reporting(E_ALL);
define('XCSSCLASS', '../css/xCSS/xcss-class.php');
include XCSSCLASS;
define('XCSSCONFIG', '../css/xCSS/config.php');
include XCSSCONFIG;

class xCSS_compiler extends xCSS
{
	public function create_file($content, $filename)
	{
		$time = $this->microtime_float() - $this->debug['xcss_time_start'];
		return '<h3>Parsed <em>xCSS</em> in: '.round($time, 6).' seconds</h3>'."\n<pre>\n".str_replace(array("\'", '\"'), array('\'', '"'), $content)."</pre>\n";
	}
	
	public function exception_handler($exception, $message = NULL, $file = NULL, $line = NULL)
	{
		if(strpos($message, 'create_function') !== FALSE)
		{
			$exception = 'xcss_math_error';
		}
		switch ($exception)
		{
			case 'xcss_empty':
				echo '<h3 class="error">xCSS Parse error: '.$message.'</h3>'."\n";
				exit(1);
			break;
			case 'xcss_math_error':
				echo '<h3 class="error">xCSS Parse error: unable to solve the math operation</h3>'."\n";
				exit(1);
			break;
			case 'xcss_file_does_not_exist':
			case 'xcss_disabled':
			case 'css_file_unwritable':
			case 'css_dir_unwritable':
				echo '<h3 class="error">xCSS Parse error: this one should not happen indside onlinecompiler. hmmm :(</h3>'."\n";
				exit(1);
			break;
			
			default:
				echo '<h3 class="error">CSS Parse error: check the xCSS syntax</h3>'."\n";
				exit(1);
			break;
		}
		return TRUE;
	}
	
	public function __destruct() {}
}

$xCSS_compiler = new xCSS_compiler($config);

echo $xCSS_compiler->compile($_POST['xcss']);