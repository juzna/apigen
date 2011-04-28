<?php

/**
 * API Generator.
 *
 * Copyright (c) 2010 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the "Nette license", and/or
 * GPL license. For more information please see http://nette.org
 */

namespace Apigen;

use NetteX;



/**
 * Scans and reflects classes/interfaces structure.
 * @author     David Grudl
 */
class Model extends NetteX\Object
{
	/** @var bool Use robot loader for autoloading classes */
	public $useRobotLoader = true;

	/** @var array Classes to be skipped */
	public $skipClasses = array();

	/** @var string Path to file to log last used class (useful when loading crashes) */
	public $logLastUsedClass = null;

	/** @var string */
	private $dir;

	/** @var array or CustomClassReflection */
	private $classes;



	/**
	 * Scans and parses PHP files.
	 * @param  string  directory
	 * @return void
	 */
	public function parse($dir)
	{
		$robot = new NetteX\Loaders\RobotLoader;
		$robot->setCacheStorage(new NetteX\Caching\Storages\MemoryStorage);
		$robot->addDirectory($dir);
		if ($this->useRobotLoader) $robot->register();
		else $robot->rebuild(); // Just load php files, but not register loader

		// load add classes
		$this->dir = realpath($dir);
		$this->classes = array();
		foreach ($robot->getIndexedClasses() as $name => $foo) {
			if (!$class = $this->getClassReflection($name)) continue;
			if (!$class->hasAnnotation('internal') && !$class->hasAnnotation('deprecated')) {
				$this->classes[$name] = $class;
			}
		}

		if($this->useRobotLoader) $robot->unregister();
		if($this->logLastUsedClass) @unlink($this->logLastUsedClass);
	}



	/**
	 * Expands list of classes by internal classes and interfaces.
	 * @return void
	 */
	public function expand()
	{
		$declared = array_flip(array_merge(get_declared_classes(), get_declared_interfaces()));

		foreach ($this->classes as $name => $class) {
			foreach (array_merge(class_parents($name),$class->getInterfaceNames(), $class->getTypeHintingClasses()) as $parent) {
				if (!isset($this->classes[$parent])) {
					$this->classes[$parent] = $this->getClassReflection($parent);
				}
			}

			foreach ($class->getOwnMethods() as $method) {
				foreach (array('param', 'return', 'throws') as $annotation) {
					if (!isset($method->annotations[$annotation])) {
						continue;
					}

					foreach ($method->annotations[$annotation] as $doc) {
						$types = preg_replace('#\s.*#', '', $doc);
						foreach (explode('|', $types) as $name) {
							$name = ltrim($name, '\\');
							if (!isset($this->classes[$name]) && isset($declared[$name])) {
								$this->classes[$name] = $this->getClassReflection($name);
							}
						}
					}
				}
			}
		}
		if($this->logLastUsedClass) @unlink($this->logLastUsedClass);
	}

	/**
	 * Tidy list of classes (i.e. remote those item which weren't found, i.e. are false)
	 */
	public function tidy()
	{
		$this->classes = array_filter($this->classes);
	}



	/** @return array or CustomClassReflection */
	public function getClasses()
	{
		return $this->classes;
	}



	/** @return string */
	public function getDirectory()
	{
		return $this->dir;
	}



	/**
	 * Tries to resolve type as class or interface name.
	 * @param  string
	 * @param  string
	 * @return string
	 */
	public function resolveType($type, $namespace = NULL)
	{
		if (substr($type, 0, 1) === '\\') {
			$namespace = '';
			$type = substr($type, 1);
		}
		return isset($this->classes["$namespace\\$type"]) ? "$namespace\\$type" : (isset($this->classes[$type]) ? $type : NULL);
	}



	/**
	 * Returns list of direct subclasses.
	 * @param  ReflectionClass
	 * @return array or CustomClassReflection
	 */
	public function getDirectSubClasses($parent)
	{
		$parent = $parent->getName();
		$res = array();
		foreach ($this->classes as $class) {
			if ($class->getParentClass() && $class->getParentClass()->getName() === $parent) {
				$res[$class->getName()] = $class;
			}
		}
		return $res;
	}



	/**
	 * Returns list of direct subclasses.
	 * @param  ReflectionClass
	 * @return array or CustomClassReflection
	 */
	public function getDirectImplementers($interface)
	{
		if (!$interface->isInterface()) return array();
		$interface = $interface->getName();
		$res = array();
		foreach ($this->classes as $class) {
			if (array_key_exists($interface, class_implements($class->getName()))) {
				if (!$class->getParentClass() ||
					!array_key_exists($interface, class_implements($class->getParentClass()->getName()))) {
					$res[$class->getName()] = $class;
				}
			}
		}
		return $res;
	}



	/**
	 * Helpers for DocBlock extracting.
	 * @param  string
	 * @return string
	 */
	public static function extractDocBlock($doc)
	{
		$doc = trim($doc, '/*');
		$doc = preg_replace('#^\s*\**\s*(@var \S+\s*|@.*)#ms', '', $doc); // remove annotations
		$doc = preg_replace('#^\s*\** ?#m', '', $doc); // remove stars
		return NetteX\Utils\Strings::normalize(trim($doc));
	}

	/**
	 * Try to find reflection for a class
	 * @param string $name Class name
	 * @return \Apigen\CustomClassReflection|false
	 */
	protected function getClassReflection($name)
	{
		if($this->logLastUsedClass) file_put_contents($this->logLastUsedClass, $name);
		
		if(in_array(strtolower($name), $this->skipClasses) || in_array($name, $this->skipClasses)) return false;
		if(!class_exists($name)) return false;

		try {
			return new CustomClassReflection($name);
		}
		catch(\ReflectionException $e) {
			echo $e->getMessage() . "\n";
			return false;
		}
	}
}
