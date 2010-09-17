<?php

/**
 * protected IniLoader methods (to be extended by other classes, like the section handlers)
 * ->loadIniFile(array &$target, $iniFile, $parseSections = TRUE)
 * ->createIniFile($iniFile, $desc, array $options, $extraInfo = '')
 * ->rewriteLine($iniFile, $section, $key, $value)
 * ->appendSection($iniFile, $section, array &$values)
 * ->removeSection($iniFile, $section)
*/
abstract class IniLoader
{
	protected function loadIniFile(array &$target, $iniFile, $parseSections = TRUE)
	{
		$iniVARs = FALSE;
		
		// Should parse the $PrismDir/config/***.ini file, and load them into the passed $target array.
		$iniPath = ROOTPATH . '/configs/'.$iniFile;
		$localIniPath = ROOTPATH . '/configs/local_'.$iniFile;
		
		if (file_exists($localIniPath))
		{
			if (($iniVARs = parse_ini_file($localIniPath, $parseSections)) === FALSE)
			{
				console('Could not parse ini file "local_'.$iniFile.'". Using global.');
			}
			else
			{
				console('Using local ini file "local_'.$iniFile.'"');
			}
		}
		if ($iniVARs === FALSE)
		{
			if (!file_exists($iniPath))
			{
				console('Could not find ini file "'.$iniFile.'"');
				return FALSE;
			}
			if (($iniVARs = parse_ini_file($iniPath, $parseSections)) === FALSE)
			{
				console('Could not parse ini file "'.$iniFile.'"');
				return FALSE;
			}
		}

		// Merge iniVARs into target (array_merge didn't seem to work - maybe because target is passed by reference?)
		foreach ($iniVARs as $k => $v)
			$target[$k] = $v;
		
		# At this point we're always successful
		return TRUE;
	}
	

	protected function createIniFile($iniFile, $desc, array $options, $extraInfo = '')
	{
		// Check if config folder exists
		if (!file_exists(ROOTPATH . '/configs/') && 
			!@mkdir(ROOTPATH . '/configs/'))
		{
			return FALSE;
		}
		
		// Check if file doesn't already exist
		if (file_exists(ROOTPATH . '/configs/'.$iniFile))
			return FALSE;
		
		// Generate file contents
		$text = '; '.$desc.' (automatically genereated)'.PHP_EOL;
		$text .= '; File location: ./PHPInSimMod/configs/'.$iniFile.PHP_EOL;
		$text .= $extraInfo;
		
		$main = '';
		foreach ($options as $section => $data)
		{
			if (is_array($data))
			{
				$main .= PHP_EOL.'['.$section.']'.PHP_EOL;
				foreach ($data as $key => $value)
				{
					$main .= $key.' = '.((is_numeric($value)) ? $value : '"'.$value.'"').PHP_EOL;
				}
			}
		}

		if ($main == '')
			return FALSE;
		
		$text .= $main.PHP_EOL;
		
		// Write contents
		if (!file_put_contents(ROOTPATH.'/configs/'.$iniFile, $text))
			return FALSE;
		
		return TRUE;
	}
	
	protected function rewriteLine($iniFile, $section, $key, $value)
	{
		// Check if file exists
		if (!file_exists(ROOTPATH.'/configs/'.$iniFile))
			return false;
		
		$newValue = (is_numeric($value)) ? $value : '"'.$value.'"';

		// Read the contents of the file into an array of lines
		$lines = file(ROOTPATH.'/configs/'.$iniFile, FILE_IGNORE_NEW_LINES);
		
		// Loop through the lines to detect Section and then Key
		$foundSection = false;
		foreach ($lines as $num => &$line)
		{
			$matches = array();
			if (preg_match('/^\s*\[(.+)\]\s*$/', $line, $matches))
			{
				if ($matches[1] == $section)
					$foundSection = true;
				else
				{
					// Check if we were in the correct section, but didn't find the line we were looking for
					if ($foundSection)
					{
						// Create a new line and insert it.
						$insert = $key.' = '.$newValue.PHP_EOL.PHP_EOL;
						array_splice($lines, $num, 0, array($insert));
						file_put_contents(ROOTPATH.'/configs/'.$iniFile, implode(PHP_EOL, $lines));
						break;
					}
					$foundSection = false;
				}
				continue;
			}
			
			if ($foundSection && preg_match('/^'.$key.'\s*=\s*.*$/', $line))
			{
				// Rewrite the line and store the updated file
				$line = preg_replace('/^'.$key.'\s*=\s*"?.+"?(\s*;.*)?$/U', $key.' = '.$newValue.'\\1', $line);
				file_put_contents(ROOTPATH.'/configs/'.$iniFile, implode(PHP_EOL, $lines));
				break;
			}
		}
		
		return true;
	}
	
	protected function appendSection($iniFile, $section, array &$values)
	{
		// Check if file exists
		if (!file_exists(ROOTPATH.'/configs/'.$iniFile))
		{
			// Just create a new file for the new section then...
			$desc = '; Configuration File (automatically genereated)'.PHP_EOL;
			$desc .= '; File location: ./PHPInSimMod/configs/'.$iniFile.PHP_EOL;
			$this->createIniFile($iniFile, $desc, array($section => $values));
			return true;
		}
		
		// Loop through the new values
		$append = PHP_EOL.'['.$section.']'.PHP_EOL;
		foreach ($values as $key => $value)
		{
			$append .= $key.' = '.((is_numeric($value)) ? $value : '"'.$value.'"').PHP_EOL;
		}
		
		file_put_contents(ROOTPATH.'/configs/'.$iniFile, $append, FILE_APPEND);
		
		return true;
	}
	
	protected function removeSection($iniFile, $section)
	{
		// Check if file exists
		if (!file_exists(ROOTPATH.'/configs/'.$iniFile))
			return false;
		
		// Read the contents of the file into an array of lines
		$lines = file(ROOTPATH.'/configs/'.$iniFile, FILE_IGNORE_NEW_LINES);
		
		// Loop through the lines to detect Section and then Key
		$newLines = array();
		$foundSection = false;
		foreach ($lines as $num => &$line)
		{
			$matches = array();
			if (preg_match('/^\s*\[(.+)\]\s*$/', $line, $matches))
			{
				if ($matches[1] == $section)
					$foundSection = true;
				else
					$foundSection = false;
			}
			
			if ($foundSection == true)
				continue;
			
			$newLines[] = $line;
		}
		
		file_put_contents(ROOTPATH.'/configs/'.$iniFile, implode(PHP_EOL, $newLines));
		
		return true;
	}
}

?>