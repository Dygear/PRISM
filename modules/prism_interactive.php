<?php

class Interactive
{
	public function queryConnections(&$vars)
	{
		echo '***Interactive startup***'.PHP_EOL;
		echo 'You now have the chance to manually enter the details of the host(s) you want to connect to.'.PHP_EOL;
		echo 'Afterwards your connection settings will be stored in ./config/connections.ini for future use.'.PHP_EOL;
		echo ''.PHP_EOL;
		
		// Ask if we want to add a direct host or a relay host
		$input = self::query('Do you want to connect to a host directly or through the relay?', array('direct', 'relay'));
		$vars['useRelay'] = ($input == 'relay') ? 1 : 0;

		if ($vars['useRelay'])
		{
			// Relay host connection details
			$vars['hostname']		= self::query('What is the name of the host (case-sensitive)?');
			$vars['adminPass']		= self::query('Do you have an administrator password for the host?', array(), TRUE);
			if (!$vars['adminPass'])
				$vars['specPass']	= self::query('Does the host require a spectator pass then?', array(), TRUE);
		}
		else
		{
			// Direct host connection details
			$vars['ip']				= self::query('What is the IP address or hostname of the host?');
			$vars['port']			= self::query('What is the InSim port number of the host?');
			$vars['socketType']		= self::query('Do you want to connect to the host via TCP or UDP?', array('tcp', 'udp'));
			$vars['password']		= self::query('What is the administrator password of the host?');
			$vars['pps']			= 4;
			//$vars['pps']			= self::query('How many position packets per second do you want to receive?');
		}
	}

	public function queryPlugins(&$vars)
	{
		
	}
	
	/*	$question	- the string that will be presented to the user.
	 *	$options	- optional array of answers of which one must be matched.
	 *	$allowEmpty	- whether to allow and empty input or not.
	 */
	public function query($question, $options = array(), $allowEmpty = FALSE)
	{
		$input = '';
		$numOptions = count($options);
		
		while(true) {
			echo $question.' [';
			foreach ($options as $index => $option)
			{
				if ($index > 0)
					echo '/';
				echo $option;
			}
			echo '] : ';
			$input = trim(fread(STDIN, 1024));
			
			if ($input == '')
			{
				if ($allowEmpty)
					break;
			}
			else if ($numOptions > 0)
			{
				if (in_array($input, $options))
					break;
			}
			else
			{
				break;
			}
		}
		return $input;
	}
}

?>