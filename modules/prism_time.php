<?php
/**
 * PHPInSimMod - Time Module
 * @package PRISM
 * @subpackage Time
 * @copyright the AMX Mod X Development Team.
*/

class Time
{
	// Time unit types.
	const UNIT_SECONDS		= 0;
	const UNIT_MINUTES		= 1;
	const UNIT_HOURS		= 2;
	const UNIT_DAYS			= 3;
	const UNIT_WEEKS		= 4;

	// The number of seconds that are in each time unit.
	const SECONDS_IN_MINUTE = 60;
	const SECONDS_IN_HOUR   = 3600;
	const SECONDS_IN_DAY    = 86400;
	const SECONDS_IN_WEEK   = 604800;

	/**
	 * @desc: By Brad for AMX Mod X.
	 * @param: Unit - The number of time units you want translated into verbose text.
	 * @param: Type - The type of unit (i.e. seconds, minutes, hours, days, weeks) that you are passing in.
	*/
	static function getLength($unit, $type = Time::UNIT_SECONDS)
	{
		if ($unit > 0)
		{
			# Determine the number of each time unit there are.
			$weeks = 0; $days = 0; $hours = 0; $minutes = 0; $seconds = 0;

			switch ($type)
			{
				case Time::UNIT_SECONDS: $seconds = $unit;
				case Time::UNIT_MINUTES: $seconds = $unit * Time::SECONDS_IN_MINUTE;
				case Time::UNIT_HOURS:   $seconds = $unit * Time::SECONDS_IN_HOUR;
				case Time::UNIT_DAYS:    $seconds = $unit * Time::SECONDS_IN_DAY;
				case Time::UNIT_WEEKS:   $seconds = $unit * Time::SECONDS_IN_WEEK;
			}

			$weeks = $seconds / Time::SECONDS_IN_WEEK;
			$seconds -= ($weeks * Time::SECONDS_IN_WEEK);

			$days = $seconds / Time::SECONDS_IN_DAY;
			$seconds -= ($days * Time::SECONDS_IN_DAY);

			$hours = $seconds / Time::SECONDS_IN_HOUR;
			$seconds -= ($hours * Time::SECONDS_IN_HOUR);

			$minutes = $seconds / Time::SECONDS_IN_MINUTE;
			$seconds -= ($minutes * Time::SECONDS_IN_MINUTE);

			// Translate the unit counts into verbose text
			$timeElement = array();

			if ($weeks > 0)
				$timeElement[] = sprintf("%i %s", $weeks, ($weeks == 1) ? "week" : "weeks");
			if ($days > 0)
				$timeElement[] = sprintf("%i %s", $days, ($days == 1) ? "day" : "days");
			if ($hours > 0)
				$timeElement[] = sprintf("%i %s", $hours, ($hours == 1) ? "hour" : "hours");
			if ($minutes > 0)
				$timeElement[] = sprintf("%i %s", $minutes, ($minutes == 1) ? "minute" : "minutes");
			if ($seconds > 0)
				$timeElement[] = sprintf("%i %s", $seconds, ($seconds == 1) ? "second" : "seconds");

			switch(count($timeElement))
			{
				case 1: return sprintf("%s", $timeElement[0]);
				case 2: return sprintf("%s & %s", $timeElement[0], $timeElement[1]);
				case 3: return sprintf("%s, %s & %s", $timeElement[0], $timeElement[1], $timeElement[2]);
				case 4: return sprintf("%s, %s, %s & %s", $timeElement[0], $timeElement[1], $timeElement[2], $timeElement[3]);
				case 5: return sprintf("%s, %s, %s, %s & %s", $timeElement[0], $timeElement[1], $timeElement[2], $timeElement[3], $timeElement[4]);
			}
		}
	}
}

?>