<?php defined('XCSSCONFIG') OR die('No direct access allowed.');
/**
 * xCSS config
 */

$config['path_to_css_dir'] = '../../../';		//	default: '../'

$config['xCSS_files'] = array
(
	'960.xcss'						=> '960.css',
	'stresstest_across_files.xcss'	=> 'stresstest_across_files.css',
	'quickcheck.xcss'				=> 'quickcheck.css',
);

$config['use_master_file'] = true;				//	default: 'true'
$config['compress_output_to_master'] = true;	//	default: 'false'
$config['master_filename'] = 'master.css';		//	default: 'master.css'

$config['reset_files'] = array
(
//	'static/reset.css',
);

$config['hook_files'] = array
(
//	'static/hooks.css: screen',
);

$config['construct_name'] = 'self';				//	default: 'self'

$config['compress_output'] = false;				//	default: 'false'

$config['debugmode'] = true;					//	default: 'false'

$config['disable_xCSS'] = false;				//	default: 'false'