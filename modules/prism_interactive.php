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

			// Ask for the alias (hostID) for this connection
			while (true)
			{
				$alias = self::query('What would you like this connection to be known as?', array(), TRUE);
				if (!isset($vars[$alias]))
					break;
				
				echo 'There already is a connection by that name. Please enter another one.'.PHP_EOL;
			}
			
			$c++;
			if ($alias == '')
				$vars["host #{$c}"] = $tmp;
			else
				$vars[$alias] = $tmp;
			unset($alias);

			if (self::query(PHP_EOL.'Would you like to add another host?', array('yes', 'no')) == 'no')
				break;
		}
		echo PHP_EOL;
	}

	public function queryPlugins(array &$vars, array &$hostvars)
	{
		// Check if plugins dir exists
		if (!file_exists(ROOTPATH.'/plugins/'))
		{
			echo 'No plugins folder seems to exist. Cannot load any plugins at this time'.PHP_EOL;
			return;
		}
		
		// read plugins dir
		$plugins = array();
		foreach (new DirectoryIterator(ROOTPATH.'/plugins') as $fileInfo) {
		    if ($fileInfo->getType() != 'file' || !preg_match('/^.*\.php$/', $fileInfo->getFilename()))
		    	continue;
		    $plugins[] = preg_replace('/^(.*)\.php$/', '$1', $fileInfo->getFilename());
		}

		if (count($plugins) == 0)
		{
			echo 'Your plugins folder does not appear to contain any plugins. PRISM will not be doing much now...'.PHP_EOL;
			return;
		}
				
		echo '***Interactive startup***'.PHP_EOL;
		echo 'You now have the chance to manually select which plugins to load.'.PHP_EOL;
		echo 'Afterwards your plugin settings will be stored in ./config/plugins.ini for future use.'.PHP_EOL;
		
		$hosts = array();
		
		// Loop through the plugins now, so we can tie hosts to each plugin
		foreach ($plugins as $plugin)
		{
			echo PHP_EOL;

			// Ask if user wants this plugin
			if (self::query('Do you want to use the plugin "'.$plugin.'"?', array('yes', 'no')) == 'no')
				continue;

			// Print a list of available hosts			
			$c = 1;
			$hostIDCache = array();
			echo 'ID | Host details'.PHP_EOL;
			echo '---+----------------'.PHP_EOL;
			foreach ($hostvars as $hostID => $values)
			{
				$hostIDCache[$c] = $hostID;
				printf('%-2d | %s (', $c, $hostID);
				if (isset($values['useRelay']) && $values['useRelay'] == 1)
					echo '"'.$values['hostname'].'" via relay';
				else
					echo '"'.$values['ip'].':'.$values['port'].'"';
				echo ')'.PHP_EOL;
				$c++;
			}

			// Select which hosts to tie to it
			while (true)
			{
				$hostIDs = '';
				if ($c == 2)
				{
					$ids = self::query(PHP_EOL.'Enter the ID number of the host you want to tie to this plugin.', array(), TRUE);
				}
				else
				{
					echo PHP_EOL.'Enter the ID numbers of the hosts you want to tie to this plugin.'.PHP_EOL;
					$ids = self::query('Separate each ID number by a space', array(), TRUE);
				}
				
				// Validate user input
				$exp = explode(' ', $ids);
				$invalidIDs = '';
				$IDCache = array();
				foreach ($exp as $e)
				{
					if ($e == '')
						continue;
					
					$id = (int) $e;
					if ($id < 1 || $id >= $c)
					{
						$invalidIDs .= $e.' ';
					}
					else if (!in_array($id, $IDCache))
					{
						if ($hostIDs != '')
							$hostIDs .= ',';
						$hostIDs .= '"'.$hostIDCache[$id].'"';
						$IDCache[] = $id;
					}
				}
				if ($invalidIDs != '')
					echo 'You typed one or more invalid host ID ('.trim($invalidIDs).'). Please try again.'.PHP_EOL;
				else
					break;
			}
			
			// Store this plugin's settings in target var
			$vars[$plugin] = array('useHosts' => substr($hostIDs, 1, -1));
		}

		echo PHP_EOL;
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
			echo $question;
			if (count($options))
			{
				echo ' [';
				foreach ($options as $index => $option)
				{
					if ($index > 0)
						echo '/';
					echo $option;
				}
				echo ']';
			}
			echo ' : ';
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