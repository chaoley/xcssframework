<?php
/**
 * xCSS index file
 *
 * @author     Anton Pawlik
 * @see        http://xcss.antpaw.org/docs/
 * @copyright  (c) 2009 Anton Pawlik
 * @license    http://xcss.antpaw.org/about/
 */
$time_start = microtime_float();

define('XCSSCLASS', 'xcss-class.php');
file_exists(XCSSCLASS) ? include XCSSCLASS : die('alert("Can\'t find the xCSS class file: \''.XCSSCLASS.'\'"!);');

define('XCSSCONFIG', '_config.php');
file_exists(XCSSCONFIG) ? include XCSSCONFIG : die('alert("Can\'t find the xCSS config file: \''.XCSSCONFIG.'\'"!);');

$xCSS = new xCSS($config);

$xCSS->compile();






usleep(30000);
$time = microtime_float() - $time_start;
echo round($time, 6)." seconds\n";

function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}