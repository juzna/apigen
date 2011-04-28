<?php

/**
 * API Generator.
 *
 * Copyright (c) 2010 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the "Nette license", and/or
 * GPL license. For more information please see http://nette.org
 */

use NetteX\Diagnostics\Debugger;


require __DIR__ . '/libs/NetteX/nettex.min.php';
require __DIR__ . '/libs/fshl/fshl.php';
require __DIR__ . '/libs/TexyX/texyx.min.php';
require __DIR__ . '/libs/Apigen/CustomClassReflection.php';
require __DIR__ . '/libs/Apigen/Model.php';
require __DIR__ . '/libs/Apigen/Generator.php';

echo '
APIGen version 0.1
------------------
';

if(!isset($options) || !is_array($options)) $options = array(); // when include-d from another php file
$options += getopt('s:d:c:t:l:', array('norobot', 'skip:', 'logclass:'));

if (!isset($options['s'], $options['d'])) { ?>
Usage:
	php apigen.php [options]

Options:
	-s <path>  Name of a source directory to parse. Required.
	-d <path>  Folder where to save the generated documentation. Required.
	-c <path>  Output config file.
	-l <path>  Directory with libraries
	-t ...     Title of generated documentation.
	--norobot          Do not use RobotLoader for autoloading (i.e. use own autoloader)
	--skip=<path>      File with list of files to skip
	--logclass=<path>  File to log last used class (useful when crashing)

<?php
	die();
}



date_default_timezone_set('Europe/Prague');
Debugger::enable();
Debugger::timer();

if(isset($options['l'])) {
  $robot = new NetteX\Loaders\RobotLoader;
  $robot->setCacheStorage(new NetteX\Caching\MemoryStorage);
  $robot->addDirectory($options['l']);
  $robot->register();
}

echo "Scanning folder $options[s]\n";
$model = new Apigen\Model;
if(isset($options['norobot'])) $model->useRobotLoader = false;
if(isset($options['logclass'])) {
	$logClassFile = $model->logLastUsedClass = $options['logclass'];

	// Add to skipped classes
	if(isset($options['skip']) && file_exists($logClassFile) && ($lastClass = trim(file_get_contents($logClassFile)))) {
		file_put_contents($options['skip'], $lastClass . "\n", FILE_APPEND);
	}
}
if(isset($options['skip'])) $model->skipClasses = array_filter(array_map(function($item) { return strtolower(trim($item)); }, file($options['skip'])));
$model->parse($options['s']);
$count = count($model->getClasses());

$model->expand();
$countD = count($model->getClasses()) - $count;

echo "Found $count classes and $countD system classes\n";



$configPath = isset($options['c']) ? $options['c'] : __DIR__ . '/config.neon';
$config = str_replace('%dir%', dirname($configPath), file_get_contents($configPath));
$config = NetteX\Utils\Neon::decode($config);
if (isset($options['t'])) {
	$config['variables']['title'] = $options['t'];
}


echo "Generating documentation to folder $options[d]\n";
@mkdir($options['d']);
foreach (NetteX\Utils\Finder::find('*')->from($options['d'])->childFirst() as $item) {
	if ($item->isDir()) {
		rmdir($item);
	} elseif ($item->isFile()) {
		unlink($item);
	}
}
$generator = new Apigen\Generator($model);
$generator->generate($options['d'], $config);



echo 'Done. Total time: ' . (int) Debugger::timer() . " seconds\n";
