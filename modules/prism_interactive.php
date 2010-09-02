<?php

class Interactive
{
	public function queryConnections(array &$vars)
	{
		echo '***Interactive startup***'.PHP_EOL;
		echo 'You now have the chance to manually enter the details of the host(s) you want to connect to.'.PHP_EOL;
		echo 'Afterwards your connection settings will be stored in ./config/connections.ini for future use.'.PHP_EOL;

		$c = 1;		
		while (true)
		{
			echo PHP_EOL;
			$tmp = array();
			
			// Ask if we want to add a direct host or a relay host
			$tmp['useRelay'] = (self::query('Do you want to connect to a host directly or through the relay?', array('direct', 'relay')) == 'relay') ? 1 : 0;
	
			if ($tmp['useRelay'])
			{
				// Relay host connection details
				$tmp['hostname']		= self::query('What is the name of the host (case-sensitive)?');
				$tmp['adminPass']		= self::query('Do you have an administrator password for the host?', array(), TRUE);
				$tmp['specPass']		= '';
				if (!$tmp['adminPass'])
					$tmp['specPass']	= self::query('Does the host require a spectator pass then?', array(), TRUE);
			}
			else
			{
				// Direct host connection details
				do {
					if (isset($tmp['ip']) && $tmp['ip'] != '')
						echo 'Invalid ip or hostname.'.PHP_EOL;
					$tmp['ip']			= self::query('What is the IP address or hostname of the host?');
				} while (!getIP($tmp['ip']));
	
				do {
					if (isset($tmp['port']))
						echo 'Invalid port number. Must be between 1 and 65535.'.PHP_EOL;
					$tmp['port']		= (int) self::query('What is the InSim port number of the host?');
				} while ($tmp['port'] < 1 || $tmp['port'] > 65535);
	
				$tmp['socketType']		= (self::query('Do you want to connect to the host via TCP or UDP?', array('tcp', 'udp')) == 'udp') ? 2 : 1;
				$tmp['password']		= self::query('What is the administrator password of the host?', array(), TRUE);
				$tmp['pps']				= 4;
				//$tmp['pps']			= self::query('How many position packets per second do you want to receive?');
				
				unset($tmp['useRelay']);
			}
			
			$vars['host #'.$c++] = $tmp;

			if (self::query(PHP_EOL.'Would you like to add another host?', array('yes', 'no')) == 'no')
				break;
		}
	}

	public function queryPlugins(array &$vars)
	{
		
	}
	
	/*	$question	- the string that will be presented to the user.
	 *	$options	- optional array of answers of which one must be matched.
	 *	$allowEmpty	- whether to allow an empty input or not.
	 */
	public function query($question, array $options = array(), $allowEmpty = false)
	{
		$input = '';
		$numOptions = count($options);
		
		while(true)
		{
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