<?php defined('XCSSCONFIG') OR die('No direct access allowed.');
/**
 * xCSS config
 */

$config['path_to_css_dir'] = '../';			//	default: '../'

$config['xCSS_files'] = array
(
//	'source/modules.xcss'		=> 'generated/modules.css',
//	'source/main.xcss'			=> 'generated/main.css',
);

$config['master_file'] = true;				//	default: 'true'
$config['master_filename'] = 'master.css';	//	default: 'master.css'

$config['reset_files'] = array
(
//	'static/reset.css',
);

$config['hook_files'] = array
(
//	'static/hooks.css: screen',
);

$config['construct_name'] = 'self';			//	default: 'self'

$config['compress'] = false;				//	default: 'false'

$config['debugmode'] = false;				//	default: 'false'