<?php
/**
 * PHPInSimMod - Interactive Module
 * @package PRISM
 * @subpackage Interactive
*/

class Interactive
{
	public static function queryHosts(array &$vars)
	{
		echo '***HOST CONNECTIONS SETUP***'.PHP_EOL;
		echo 'You now have the chance to manually enter the details of the host(s) you want to connect to.'.PHP_EOL;
		echo 'Afterwards your connection settings will be stored in ./config/hosts.ini for future use.'.PHP_EOL;

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
				$tmp['adminPass']		= self::query('Optional administrator password (or blank)', array(), TRUE);
				$tmp['specPass']		= '';
				
				if (!$tmp['adminPass'])
					$tmp['specPass']	= self::query('Optional spectator pass then? (or blank)', array(), TRUE);
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
				$tmp['pps']			= 4;
				//$tmp['pps']			= self::query('How many position packets per second do you want to receive?');
				
				unset($tmp['useRelay']);
			}

			$tmp['flags']			= 0;
			$tmp['flags']			+= (self::query('Are you connecting to dedicated or listen server?', array('dedi', 'listen')) == 'yes') ? 0 : ISF_LOCAL;
			$tmp['flags']			+= (self::query('Keep colours in MSO text?', array('yes', 'no')) == 'yes') ? ISF_MSO_COLS : 0;
			$tmp['flags']			+= (self::query('Receive Node Lap Player (Less Detailed then MCI) packets?', array('yes', 'no')) == 'yes') ? ISF_NLP : 0;
			$tmp['flags']			+= (self::query('Receive Muli Car Info (Most detailed real time packet) packets?', array('yes', 'no')) == 'yes') ? ISF_MCI : 0;
			$tmp['flags']			+= (self::query('Receive Contact packets?', array('yes', 'no')) == 'yes') ? ISF_CON : 0;
			$tmp['flags']			+= (self::query('Receive Object Hit packets?', array('yes', 'no')) == 'yes') ? ISF_OBH : 0;
			$tmp['flags']			+= (self::query('Receive Hot Lap Verification packets?', array('yes', 'no')) == 'yes') ? ISF_HLV : 0;
			$tmp['flags']			+= (self::query('Receive Auto X packet when loading and unloading track layouts?', array('yes', 'no')) == 'yes') ? ISF_AXM_LOAD : 0;
			$tmp['flags']			+= (self::query('Receive Auto X packet when editing track layouts?', array('yes', 'no')) == 'yes') ? ISF_AXM_EDIT : 0;

			// Ask for the alias (hostID) for this connection
			while (true)
			{
				$hostID = self::query('What would you like this connection to be known as?', array(), TRUE);
				if (isset($vars[$hostID]))
					echo 'There already is a connection by that name. Please enter another one.'.PHP_EOL;
				else if (!preg_match('/^[a-zA-Z0-9]+$/', $hostID))
					echo 'Please only use characters a-z, A-Z and 0-9'.PHP_EOL;
				else
					break;
			}
			
			$c++;
			if ($hostID == '')
				$vars["host #{$c}"] = $tmp;
			else
				$vars[$hostID] = $tmp;
			unset($hostID);

			if (self::query(PHP_EOL.'Would you like to add another host?', array('yes', 'no')) == 'no')
				break;
		}
		echo PHP_EOL;
	}

	public static function queryPlugins(array &$vars, array &$hostvars)
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
				
		echo '***PLUGINS SETUP***'.PHP_EOL;
		echo 'You now have the chance to manually select which plugins to load.'.PHP_EOL;
		echo 'Afterwards your plugin settings will be stored in ./config/plugins.ini for future use.'.PHP_EOL;
		
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
			foreach ($hostvars as $index => $values)
			{
				$hostIDCache[$c] = $values['id'];
				printf('%-2d | %s (', $c, $values['id']);
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
					$ids = self::query(PHP_EOL.'Enter the ID number of the host you want to tie to this plugin. Or type * for all hosts.', array(), TRUE);
				}
				else
				{
					echo PHP_EOL.'Enter the ID numbers of the hosts you want to tie to this plugin. Or type * for all hosts.'.PHP_EOL;
					$ids = self::query('Separate each ID number by a space', array(), TRUE);
				}
				
				// Validate user input
				if ($ids == '*')
				{
					$hostIDs .= '"*"';
					break;
				}
				else
				{
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
			}
			
			// Store this plugin's settings in target var
			$vars[$plugin] = array('useHosts' => substr($hostIDs, 1, -1));
		}

		echo PHP_EOL;
	}
	
	public static function queryHttp(array &$vars)
	{
		echo '***HTTP SETUP***'.PHP_EOL;
		echo 'You now have the chance to manually enter the details of the http server.'.PHP_EOL;
		echo 'Afterwards your http settings will be stored in ./config/http.ini for future use.'.PHP_EOL;

		// Ask if we want to use a http socket at all
		if (self::query(PHP_EOL.'Would you like to setup the web server?', array('yes', 'no')) == 'no')
		{
			$vars['ip'] = '';
			$vars['port'] = '0';
			return;
		}

		// Ask which IP address to bind the listen socket to
		while (true)
		{
			$vars['ip']		= self::query('On which IP address should we listen? (blank means all)', array(), true);
			if ($vars['ip'] == '')
				$vars['ip'] = '0.0.0.0';
			
			if (!verifyIP($vars['ip']))
				echo 'Invalid IPv4 address entered. Please try again.'.PHP_EOL;
			else
				break;
		}
		
		// Ask which Port to listen on
		while (true)
		{
			$vars['port']		= (int) self::query('On which Port should we listen? (blank means Port 80)', array(), true);
			if ($vars['port'] == '')
				$vars['port'] = '80';

			if ($vars['port'] < 1 || $vars['port'] > 65535)
				echo 'Invalid Port number entered. Please try again.'.PHP_EOL;
			else
				break;
		}

		// Ask if we want to turn on httpAuth
		if (self::query('Do you want to restrict access to the admin website with a http login query?', array('yes', 'no')) == 'yes')
		{
			$vars['httpAuthPath'] = '/';
		}
		echo PHP_EOL;
	}
	
	public static function queryTelnet(array &$vars)
	{
		echo '***TELNET SETUP***'.PHP_EOL;
		echo 'You now have the chance to manually enter the details of the telnet server.'.PHP_EOL;
		echo 'Afterwards your telnet settings will be stored in ./config/telnet.ini for future use.'.PHP_EOL;

		// Ask if we want to use a telnet socket at all
		if (self::query(PHP_EOL.'Would you like to setup the telnet server?', array('yes', 'no')) == 'no')
		{
			$vars['ip'] = '';
			$vars['port'] = '0';
			return;
		}
		
		// Ask which IP address to bind the listen socket to
		while (true)
		{
			$vars['ip']		= self::query('On which IP address should we listen? (blank means all)', array(), true);
			if ($vars['ip'] == '')
				$vars['ip'] = '0.0.0.0';
			
			if (!verifyIP($vars['ip']))
				echo 'Invalid IPv4 address entered. Please try again.'.PHP_EOL;
			else
				break;
		}
		
		// Ask which Port to listen on
		while (true)
		{
			$vars['port']	= (int) self::query('On which Port should we listen? (blank means Port 23', array(), true);
			if ($vars['port'] == '')
				$vars['port'] = '23';
			
			if ($vars['port'] < 1 || $vars['port'] > 65535)
				echo 'Invalid Port number entered. Please try again.'.PHP_EOL;
			else
				break;
		}
		echo PHP_EOL;
	}
	
	public static function queryAdmins(array &$vars)
	{
		global $PRISM;
		
		$hostVars = $PRISM->hosts->getHostsInfo();
		
		echo '***ADMIN SETUP***'.PHP_EOL;
		echo 'You now have the chance to create PRISM admin accounts.'.PHP_EOL;
		echo 'Afterwards your admins settings will be stored in ./config/admins.ini for future use.'.PHP_EOL;

		do
		{
			echo PHP_EOL;
			$tmp = array();
			
			$tmp['username']			= self::query('Give the (LFS) username for the account');
			do
			{
				$tmp['password']		= self::query('Give a password for the account');
				$tmp['passwordVeri']	= self::query('Repeat the same password to verify');
				if ($tmp['password'] != $tmp['passwordVeri'])
					echo 'Passwords did not match. Please try again.'.PHP_EOL;
				else if (strlen($tmp['password']) < 4)
					echo 'The password is too short. Please enter a longer one.'.PHP_EOL;
				else if (strlen($tmp['password']) >= 40)
					echo 'The password is too long. Please enter a shorter one.'.PHP_EOL;
				else
					break;
			} while(true);
			
			// Print a list of available hosts			
			$c = 1;
			$hostIDCache = array();
			echo 'ID | Host details'.PHP_EOL;
			echo '---+----------------'.PHP_EOL;
			foreach ($hostVars as $index => $values)
			{
				$hostIDCache[$c] = $values['id'];
				printf('%-2d | %s (', $c, $values['id']);
				if (isset($values['useRelay']) && $values['useRelay'] == 1)
					echo '"'.$values['hostname'].'" via relay';
				else
					echo '"'.$values['ip'].':'.$values['port'].'"';
				echo ')'.PHP_EOL;
				$c++;
			}

			// Select which hosts to tie to this new admin
			while (true)
			{
				$hostIDs = '';
				if ($c == 2)
				{
					$ids = self::query(PHP_EOL.'Enter the ID number of the host you want to tie to this admin. Or type * for all hosts.', array(), TRUE);
				}
				else
				{
					echo PHP_EOL.'Enter the ID numbers of the hosts you want to tie to this admin. Or type * for all hosts.'.PHP_EOL;
					$ids = self::query('Separate each ID number by a space', array(), TRUE);
				}
				
				// Validate user input
				if ($ids == '*')
				{
					$hostIDs .= '*';
					break;
				}
				else
				{
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
							$hostIDs .= $hostIDCache[$id];
							$IDCache[] = $id;
						}
					}
					if ($invalidIDs != '')
						echo 'You typed one or more invalid host ID ('.trim($invalidIDs).'). Please try again.'.PHP_EOL;
					else
						break;
				}
			}
			
			$tmp['connection']			= $hostIDs;
			$tmp['accessFlags']			= 'abcdefghijklmnopqrstuvwxyz';
			
			$vars[$tmp['username']] 	= array(
				'password'		=> sha1($tmp['password'].$PRISM->config->cvars['secToken']),
				'connection'	=> $tmp['connection'],
				'accessFlags'	=> $tmp['accessFlags'],
				'realmDigest'	=> md5($tmp['username'].':'.HTTP_AUTH_REALM.':'.$tmp['password']),
			);
			
			if (self::query(PHP_EOL.'Add another admin account?', array('yes', 'no')) == 'no')
				break;
		} while(true);
	}

	/*	$question	- the string that will be presented to the user.
	 *	$options	- optional array of answers of which one must be matched.
	 *	$allowEmpty	- whether to allow an empty input or not.
	 */
	public static function query($question, array $options = array(), $allowEmpty = false)
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