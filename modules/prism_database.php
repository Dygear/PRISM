<?php
/**
 * PHPInSimMod - Database Module
 * @package PRISM
 * @subpackage Database
*/
class database
{
	# Hold an instance of the class
	private static $instance;
	# A private constructor; prevents direct creation of object
	private function __construct() {}
	# The singleton method
	public static function initialise($dsn, $startUpStr, $user = NULL, $pass = NULL, $port = NULL)
	{
		if (!isset(self::$instance))
			self::$instance = new PDO($dsn, $startUpStr, $user, $pass, $port);
		return self::$instance;
	}
	# Prevent users to clone the instance
	public function __clone()
	{
		trigger_error('Clone is not allowed.', E_USER_ERROR);
	}
	# Startup functions
	public static function startup($dsn, $startUpStr, $user = NULL, $pass = NULL, $port = NULL)
	{
		self::$instance = new PDO($dsn, $startUpStr, $user, $pass, $port);
	}
}
?>