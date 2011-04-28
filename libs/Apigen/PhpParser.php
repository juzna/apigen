<?php
namespace Apigen;

/**
 * Simple tokenizer for PHP.
 */
class PhpParser extends \NetteX\Utils\Tokenizer
{

	function __construct($code)
	{
		$this->ignored = array(T_COMMENT, T_DOC_COMMENT, T_WHITESPACE);
		foreach (token_get_all($code) as $token) {
			$this->tokens[] = is_array($token) ? self::createToken($token[1], $token[0], $token[2]) : $token;
		}
	}



	function replace($s, $start = NULL)
	{
		for ($i = ($start === NULL ? $this->position : $start) - 1; $i < $this->position - 1; $i++) {
			$this->tokens[$i] = '';
		}
		$this->tokens[$this->position - 1] = $s;
	}
}
