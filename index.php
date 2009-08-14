<?php
/**
 * xCSS index file
 *
 * @author		Anton Pawlik
 * @see			http://xcss.antpaw.de/docs/
 * @copyright	(c) 2009 Anton Pawlik
 * @license		http://xcss.antpaw.de/about/
 */

define('XCSSCLASS', 'xCSS_0.1.3.5.php');
file_exists(XCSSCLASS) ? include XCSSCLASS : die('alert("Can\'t find the xCSS class file: \''.XCSSCLASS.'\'"!);');

define('XCSSCONFIG', 'config.php');
file_exists(XCSSCONFIG) ? include XCSSCONFIG : die('alert("Can\'t find the xCSS config file: \''.XCSSCONFIG.'\'"!);');

$xCSS = new xCSS($config);

$xCSS->compile();