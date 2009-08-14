<?php defined('XCSSCONFIG') OR die('No direct access allowed.');
/**
 * xCSS config
 */

$config['path_to_css_dir'] = '../';			//	default: '../'

$config['xCSS_files'] = array
(
	'modules.xcss'	=> 'generated/modules.css',
	'main.xcss'	=> 'generated/main.css',
	'test.xcss'	=> 'generated/test.css',
	'_rules_test.xcss'		=> 'generated/_rules_test.css',
	'_main.xcss'			=> 'generated/_main.css',
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

$config['debugmode'] = true;				//	default: 'false'