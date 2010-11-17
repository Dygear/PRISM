<?php
/**
 * PHPInSimMod - CRON Module
 * @package PRISM
 * @subpackage CRON
*/

/**
* 
*/
class cron
{
	/**
	* 
	*/
	public $minute;
	/**
	* 
	*/
	public $hour;
	/**
	* 
	*/
	public $dayOfMonth;
	/**
	* 
	*/
	public $month;
	/**
	* 
	*/
	public $dayOfWeek;

	/**
	* 
	*/
	public function __construct($minute, $hour, $dayOfMonth, $month, $dayOfWeek)
	{
		foreach ($this as $property => $value)
			$this->$property = $$property;
	}

	/**
	* Retruns the number of seconds from the Unix Epoc (Jan 1st 1970) that the command will next take place.
	*/
	public function getNextTime()
	{
		return $epochTime;
	}

	/**
	* Retruns the number of seconds from the Unix Epoc (Jan 1st 1970) that the command took place previously.
	*/
	public function getPrevTime()
	{
		return $epochTime;
	}
}

?>