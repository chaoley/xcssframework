<?php
/**
 * xCSS string-compiler file
 *
 */

$xcss_string = '.selector { color: red; }';

define('XCSSCLASS', '../xcss-class.php');
include XCSSCLASS;
define('XCSSCONFIG', '../config.php');
include XCSSCONFIG;

$xCSS = new xCSS($config);

echo $xCSS->compile($xcss_string);