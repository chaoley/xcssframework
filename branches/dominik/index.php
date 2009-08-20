<?php
/**
 * xCSS index file
 *
 * @author     Anton Pawlik
 * @author     Dominik Bonsch <dominik.bonsch@webfrap.de>
 * @see        http://xcss.antpaw.org/docs/
 * @copyright  (c) 2009 Anton Pawlik
 * @license    http://xcss.antpaw.org/about/
 */

include 'Xcss.php';
include 'config.php';

$xCSS = new Xcss($config);
$xCSS->compile();