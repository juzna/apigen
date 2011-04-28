<?php
// Load nette and our functions
require_once __DIR__ . '/libs/NetteX/nettex.min.php';
require_once __DIR__ . '/libs/Apigen/PhpParser.php';
require_once __DIR__ . '/func.php';


// Set-up paths for EDR
{
	define('SYSTEM_LOCATION', '/www/sys/application/');
	define('LIB_LOCATION', '/www/lib/application/');

	// initialise file include locations
	set_include_path(
		get_include_path()
		. PATH_SEPARATOR
		. '/www/lib/' // Zend Framework is here
		. PATH_SEPARATOR
		. '/www/lib/application/models/' // Raa libraries are here
		. PATH_SEPARATOR
		. '../application/models/' // application-supplied custom models
		. PATH_SEPARATOR
		. SYSTEM_LOCATION . '/models/' // eDR system-supplied models
	);
}
// print_r(explode(PATH_SEPARATOR, get_include_path()));

// Initialize simple auto-loader
spl_autoload_register('simpleAutoLoader');


// EDR specific options
$options = array(
	'norobot'   => true,
	'logclass'  => __DIR__ . '/lastClass.tmp',
	'skip'		=> __DIR__ . '/skip_classes',
);


// Run apigen
include __DIR__ . '/apigen.php';

echo "FINISHED!\n";
