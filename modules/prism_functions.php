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

// check if path1 is part of path2 (ie. if path1 is a base path of path2)
function isDirInDir($path1, $path2)
{
	$p1 = explode('/', $path1);
	$p2 = explode('/', $path2);
	
	foreach ($p1 as $index => $part)
	{
		if ($part === '')
			continue;
		if (!isset($p2[$index]) || $part != $p2[$index])
			return false;
	}
	
	return true;
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

function flagsToString($flagsBitwise = 0)
{
	$flagsString = '';
	if ($flagsBitwise == 0)
		return $flagsString;

	# This makes sure we only handle the flags we know by unsetting any unknown bits.
	$flagsBitwise = $flagsBitwise & ADMIN_ALL;

	# Converts bits to the char forms.
	for ($i = 0; $i < 26; ++$i)
		$flagsString .= ($flagsBitwise & (1 << $i)) ? chr($i + 97) : NULL;

	return $flagsString;
}

define('RAND_ASCII', 1);
define('RAND_ALPHA', 2);
define('RAND_NUMERIC', 4);
define('RAND_HEX', 8);
define('RAND_BINARY', 16);
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
		else if ($type & RAND_HEX)
		{
			$out .= sprintf('%02x', rand(0, 255));
		}
		else if ($type & RAND_BINARY)
		{
			$out .= chr(rand(0, 255));
		}
		else
		{
			$out .= chr(rand(32, 127));
		}
	}
	return $out;
}

function ucwordsByChar($string, $delimiter)
{
	$out = '';
	foreach (explode($delimiter, $string) as $k => $v)
	{
		if ($k > 0)
			$out .= $delimiter;
		$out .= ucfirst($v);
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

function timeToString($int, $fraction=1000)
{
	$seconds = floor($int / $fraction);
	$fractions = $int - floor($seconds * $fraction);
	$seconds -= ($hours = floor($seconds / 3600)) * 3600;
	$seconds -= ($minutes = floor($seconds / 60)) * 60;
	
	if ($hours > 0)
	{
		return sprintf('%d:%02d:%02d.%0'.(strlen($fraction) - 1).'d', $hours, $minutes, $seconds, $fractions);
	}
	else
	{
		return sprintf('%d:%02d.%0'.(strlen($fraction) - 1).'d', $minutes, $seconds, $fractions);
	}
}

function timeToStr($time, $fraction=1000)
{
	return preg_replace('/^(0+:)+/', '', timeToString($time, $fraction));
}

function sortByKey($key)
{
	return function ($left, $right) use ($key)
	{
		if ($left[$key] == $right[$key])
			return 0;
		else
			return ($left[$key] < $right[$key]) ? -1 : 1;
	};
}

function sortByProperty($property)
{
	return function ($left, $right) use ($property)
	{
		if ($left->$property == $right->$property)
			return 0;
		else
			return ($left->$property < $right->$property) ? -1 : 1;
	};
}

class Msg2Lfs
{
    public $PLID = 0;
    public $UCID = 0;
    public $Text = '';
    public $Sound = SND_SILENT;
    
    public function __construct($text = '')
    {
        $this->Text = $text;
        return $this;
    }
    
    public function &__call($name, array $arguments)
    {
    	if (property_exists(get_class($this), $name))
    		$this->$name = array_shift($arguments);
    	return $this;
    }
    
    public function send($hostId = NULL)
    {
        if ($this->Text == '') { return; }
        
    	global $PRISM;
    
        // Decide what IS packet to use to send this message
        if (($PRISM->hosts->getStateById($hostId)->State & ISS_MULTI) === 0)
        {
            // Single player
            IS_MSL()->Msg($this->Text)->Sound($this->Sound)->send();
        }
        else
        {
            // Multi player
            if ($this->PLID > 0)
                IS_MTC()->PLID($this->PLID)->Text($this->Text)->Sound($this->Sound)->send();
            else if ($this->UCID > 0)
                IS_MTC()->UCID($this->UCID)->Text($this->Text)->Sound($this->Sound)->send();
            else
                IS_MSX()->Msg($this->Text)->send();
        }
    
    	return $this;
    }
}; function Msg2Lfs() { return new Msg2Lfs; }
?>