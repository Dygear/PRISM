<?php
/**
 * PHPInSimMod - Database Module
 * @package PRISM
 * @subpackage Database
*/
class DatabaseHandler
{
	# Hold an instance of the class
	private $instance;
	# A private constructor; prevents direct creation of object
	public function __construct() {}
	# The singleton method
	public function initialise($dsn, $startUpStr, $user = NULL, $pass = NULL, $port = NULL)
	{
		if (!isset($this->instance))
			$this->instance = new PDO($dsn, $startUpStr, $user, $pass, $port);
		return $this;
	}
	# Prevent users to clone the instance
	public function __clone()
	{
		trigger_error('Clone is not allowed.', E_USER_WARNING);
	}
}
?>
