<?php

/**
 * Find file in include paths
 * @param string $file
 * @return string|false
 */
function findFile($file) {
	if(file_exists($path = realpath($file))) return $path;
	else {
        $pathList = explode(PATH_SEPARATOR, get_include_path());
        foreach($pathList as $p) {
	        if(file_exists($path = preg_replace('%/$%','',$p)."/$file")) return $path;
		}
    }
	return false;
}

/**
 * Try to find file ClassName -> FileName
 * @param string $class
 * @return string
 */
function getClassFile($class) {
	$className = ltrim($class, '\\');
	$file      = '';
	$namespace = '';
	if ($lastNsPos = strripos($className, '\\')) {
		$namespace = substr($className, 0, $lastNsPos);
		$className = substr($className, $lastNsPos + 1);
		$file      = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
	}
	$file .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

	return $file;
}

/**
 * Get path of file for class
 * @param string $class
 * @return false|string
 */
function getClassPath($class) {
	return findFile(getClassFile($class));
}


/**
 * Test if it's safe to load a file
 *
 * @param string $file
 * @return bool
 */
function isSafeToLoadFile($file) {
	$parser = new Apigen\PhpParser(file_get_contents($file));
	$classes = array(); // List of used classes in this file

	while (($token = $parser->fetch()) !== FALSE) {
		// Find class ancestors
		if($parser->isCurrent(T_EXTENDS, T_IMPLEMENTS)) {
			do {
				$parser->fetchAll(T_WHITESPACE, T_COMMENT); // Skip whitespace
				$class = $parser->fetchAll(T_STRING, T_NS_SEPARATOR);
				if($class) $classes[] = $class;
			} while($parser->fetch(','));
		}
	}

	foreach($classes as $class) {
		if(class_exists($class, false) || interface_exists($class, false)) continue; // Already exists, maybe internal?
		
		if(!$file2 = getClassPath($class)) {
			echo "  File for class '$class' not found\n";
			return false;
		}

		if(!isSafeToLoadFile($file2)) {
			echo "  $file2 is not safe!\n";
			return false;
		}
	}

	return true;
}

/**
 * Simple auto-loader which uses getClassPath() to locate files and checks them using isSafeToLoadFile()
 *
 * @param string $class
 * @return bool
 */
function simpleAutoLoader($class) {
	static $cache;

	if(isset($cache[$class])) return $cache[$class]; // Already tried
	$cache[$class] = false;

	$file = getClassPath($class);
	if(!$file) {
		echo "File for class '$class' not found\n";
		dumpBackTrace();
		return false;
	}

	if(!isSafeToLoadFile($file)) {
		echo "File for class '$class' found, but is not safe!\n";
		dumpBackTrace();
		return false;
	}

	// Load the file
	include $file;

	if(!class_exists($class) && !interface_exists($class)) {
		echo "File '$file' found, but not class in it '$class'\n";
		dumpBackTrace();
		return false;
	}

	echo "Loaded $class from $file\n";
	return $cache[$class] = true; // Yeah, we made it
}

function dumpBackTrace() {
	file_put_contents('./last_dump', \NetteX\Diagnostics\Debugger::dumpX(debug_backtrace(), true));
}
