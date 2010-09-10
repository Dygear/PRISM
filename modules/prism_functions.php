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

function createRandomString($len)
{
	$out = '';
	for ($a=0; $a<$len; $a++)
		$out .= chr(rand(32, 127));
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