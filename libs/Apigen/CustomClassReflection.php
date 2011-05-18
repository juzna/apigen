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
 * Smarter class/interface reflection.
 * @author     David Grudl
 */
class CustomClassReflection extends NetteX\Reflection\ClassType
{
	public static $allowZendNamespaces = false;

	private $package = NULL;
	
	
	public function getTypeHintingClasses()
	{
		$methods = $this->getMethods();
		
		// can not use $this->getConstructor() because of strange MethodReflection::import() error
		array_push($methods, new \ReflectionMethod($this, '__construct'));
		
		$classes = array();
		foreach ($methods as $method) {
			foreach($method->getParameters() as $param) {
				try {
					if ($class = $param->getClass()) {
						$classes[] = $class->getName();
					}
				} catch (\ReflectionException $e) {
					echo $e->getMessage() . "\n";
				}
			}
		}
		
		return array_unique($classes);
	}


	/**
	 * Returns namespace or package name.
	 * @return string
	 */
	public function getNamespaceName()
	{
		if ($this->package === NULL) {
			if ($this->inNamespace() || $this->isInternal()) {
				$this->package = parent::getNamespaceName();

			} elseif ($this->hasAnnotation('package')) {
				$this->package = $this->getAnnotation('package'); // found in class-level DocBlock

			} elseif (preg_match('#\*\s+@package\s+(\S+)#', file_get_contents($this->getFileName()), $matches)) {
				$this->package = $matches[1]; // found in page-level DocBlock

			} elseif (self::$allowZendNamespaces && preg_match('#([a-z0-9_]+)_([a-z0-9]+)#Ai', $this->getName(), $matches)) {
				return strtr($matches[1], '_', '\\'); // Zend style namespace
			} 
		}
		return $this->package;
	}



	/**
	 * Returns interfaces declared by inspected class.
	 * @return array of CustomClassReflection
	 */
	public function getOwnInterfaces()
	{
		$parent = $this->getParentClass();
		return array_filter($this->getInterfaces(), function($interface) use ($parent) {
			return !$parent || !$parent->implementsInterface($interface->getName());
		});
	}



	/**
	 * Returns visible methods declared by inspected class.
	 * @return array of NetteX\Reflection\MethodReflection
	 */
	public function getOwnMethods()
	{
		$me = $this->getName();
		return array_filter($this->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED), function($method) use ($me) {
			return $method->declaringClass->name === $me && !$method->hasAnnotation('internal');
		});
	}



	/**
	 * Returns visible properties declared by inspected class.
	 * @return array of NetteX\Reflection\PropertyReflection
	 */
	public function getOwnProperties()
	{
		$me = $this->getName();
		return array_filter($this->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED), function($property) use ($me) {
			return $property->declaringClass->name === $me && !$property->hasAnnotation('internal');
		});
	}



	/**
	 * Returns constants declared by inspected class.
	 * @return array of string
	 */
	public function getOwnConstants()
	{
		return array_diff_assoc($this->getConstants(), $this->getParentClass() ? $this->getParentClass()->getConstants() : array());
	}


	/**
	 * Is inspected class an exception?
	 * @return bool
	 */
	public function isException()
	{
		return $this->isSubclassOf('Exception') || $this->getName() === 'Exception';
	}

	public function getDefaultPropertyValue($name)
	{
		$x = $this->getDefaultProperties();
		return $x[$name]; //isset($x[$name]) ? $x[$name] : null;
	}

}
