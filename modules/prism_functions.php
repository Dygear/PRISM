<?php

function console($line, $EOL = true)
{
	// Add log to file
	// Effected by PRISM_LOG_MODE && PRISM_LOG_FILE_MODE
	echo $line . (($EOL) ? PHP_EOL : '');
}

function get_dir_structure($path, $recursive = TRUE, $ext = NULL)
{
	$return = NULL;
	if (!is_dir($path))
	{
		trigger_error('$path is not a directory!', E_USER_WARNING);
		return FALSE;
	}
	if ($handle = opendir($path))
	{
		while (FALSE !== ($item = readdir($handle)))
		{
			if ($item != '.' && $item != '..')
			{
				if (is_dir($path . $item))
				{
					if ($recursive)
					{
						$return[$item] = get_dir_structure($path . $item . '/', $recursive, $ext);
					}
					else
					{
						$return[$item] = array();
					}
				}
				else
				{
					if ($ext != null && strrpos($item, $ext) !== FALSE)
					{
						$return[] = $item;
					}
				}
			}
		}
		closedir($handle);
	}
	return $return;
}

function findPHPLocation($windows = false)
{
	$phpLocation = '';
	
	if ($windows)
	{
		console('Trying to find the location of php.exe');

		// Search in current dir first.
		$exp = explode("\r\n", shell_exec('dir /s /b php.exe'));
		if (preg_match('/^.*\\\php\.exe$/', $exp[0]))
		{
			$phpLocation = $exp[0];
		}
		else
		{
			// Do a recursive search on this whole drive.
			chdir('/');
			$exp = explode("\r\n", shell_exec('dir /s /b php.exe'));
			if (preg_match('/^.*\\\php\.exe$/', $exp[0]))
				$phpLocation = $exp[0];
			chdir(ROOTPATH);
		}
	}
	else
	{
		$exp = explode(' ', shell_exec('whereis php'));
		$count = count($exp);
		if ($count == 1)				// Some *nix's output is only the path
			$phpLocation = $exp[0];
		else if ($count > 1)			// FreeBSD for example has more info on the line, like :
			$phpLocation = $exp[1];		// php: /user/local/bin/php /usr/local/man/man1/php.1.gz
	}
	
	return $phpLocation;
}

function validatePHPFile($file)
{
	// Validate script
	$fileContents = file_get_contents($file);
	if (!eval('return true;'.preg_replace(array('/^<\?(php)?/', '/\?>$/'), '', $fileContents)))
		return array(false, array('Errors parsing '.$file));
		
	// Validate any require_once or include_once files.
//	$matches = array();
//	preg_match_all('/(include_once|require_once)\s*\(["\']+(.*)["\']+\)/', $fileContents, $matches);
//
//	foreach ($matches[2] as $include)
//	{
//		console($include);
//		$result = validatePHPFile($include);
//		if ($result[0] == false)
//			return $result;
//	}

	return array(true, array());
}

function flagsToInteger($flagsString = '')
{
	# We don't have anything to parse.
	if ($flagsString == '')
		return FALSE;

	$flagsBitwise = 0;
	for ($chrPointer = 0, $strLen = strlen($flagsString); $chrPointer < $strLen; ++$chrPointer)
	{
		# Convert this charater to it's ASCII int value.
		$char = ord($flagsString{$chrPointer});

		# We only want a (ASCII = 97) through z (ASCII 122), nothing else.
		if ($char < 97 || $char > 122)
			continue;

		# Check we have already set that flag, if so skip it!
		if ($flagsBitwise & (1 << ($char - 97)))
			continue;

		# Add the value to our $flagBitwise intager.
		$flagsBitwise += (1 << ($char - 97));
	}
	return $flagsBitwise;
}

function flagsToString($flagsString = 0)
{
	$string = '';
	if ($flagsString == 0)
		return $string;
	
	return $string;
}

define('RAND_ASCII', 1);
define('RAND_ALPHA', 2);
define('RAND_NUMERIC', 4);
define('RAND_BINARY', 8);
function createRandomString($len, $type = RAND_ASCII)
{
	$out = '';
	for ($a=0; $a<$len; $a++)
	{
		if ($type & RAND_ALPHA)
		{
			$out .= rand(0,1) ? chr(rand(65, 90)) : chr(rand(97, 122));
		}
		else if ($type & RAND_NUMERIC)
		{
			$out .= chr(rand(48, 57));
		}
		else if ($type & RAND_ASCII)
		{
			$out .= chr(rand(32, 127));
		}
		else
		{
			$out .= chr(rand(0, 255));
		}
	}
	return $out;
}

function getIP(&$ip)
{
	if (verifyIP($ip))
		return $ip;
	else
	{
		$tmp_ip = @gethostbyname($ip);
		if (verifyIP($tmp_ip))
			return $tmp_ip;
	}
	
	return FALSE;
}

function verifyIP(&$ip)
{
	return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}

?>