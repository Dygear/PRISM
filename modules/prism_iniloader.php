<?php
/**
 * PHPInSimMod - INILoader Module
 * @package PRISM
 * @subpackage INILonader
*/

/**
 * protected IniLoader methods (to be extended by other classes, like the section handlers)
 * ->loadIniFile(array &$target, $parseSections = TRUE)
 * ->createIniFile($desc, array $options, $extraInfo = '')
 * ->rewriteLine($section, $key, $value)
 * ->appendSection($section, array &$values)
 * ->removeSection($section)
*/
abstract class IniLoader
{
	protected $iniFile = '';
	
	protected function loadIniFile(array &$target, $parseSections = TRUE)
	{
		$iniVARs = FALSE;
		
		// Should parse the $PrismDir/config/***.ini file, and load them into the passed $target array.
		$iniPath = ROOTPATH . '/configs/'.$this->iniFile;
		
		if (!file_exists($iniPath))
		{
			console('Could not find ini file "'.$this->iniFile.'"');
			return FALSE;
		}
		if (($iniVARs = parse_ini_file($iniPath, $parseSections)) === FALSE)
		{
			console('Could not parse ini file "'.$this->iniFile.'"');
			return FALSE;
		}

		// Merge iniVARs into target (array_merge didn't seem to work - maybe because target is passed by reference?)
		foreach ($iniVARs as $k => $v)
			$target[$k] = $v;
		
		# At this point we're always successful
		return TRUE;
	}
	

	protected function createIniFile($desc, array $options, $extraInfo = '')
	{
		// Check if config folder exists
		if (!file_exists(ROOTPATH . '/configs/') && 
			!@mkdir(ROOTPATH . '/configs/'))
		{
			return FALSE;
		}
		
		// Check if file doesn't already exist
		if (file_exists(ROOTPATH . '/configs/'.$this->iniFile))
			return FALSE;
		
		// Generate file contents
		$text = '; '.$desc.' (automatically genereated)'.PHP_EOL;
		$text .= '; File location: ./PHPInSimMod/configs/'.$this->iniFile.PHP_EOL;
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
		if (!file_put_contents(ROOTPATH.'/configs/'.$this->iniFile, $text))
			return FALSE;
		
		return TRUE;
	}
	
	protected function rewriteLine($section, $key, $value)
	{
		// Check if file exists
		if (!file_exists(ROOTPATH.'/configs/'.$this->iniFile))
			return false;
		
		$newValue = (is_numeric($value)) ? $value : '"'.$value.'"';

		// Read the contents of the file into an array of lines
		$lines = file(ROOTPATH.'/configs/'.$this->iniFile, FILE_IGNORE_NEW_LINES);
		
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
						if (!file_put_contents(ROOTPATH.'/configs/'.$this->iniFile, implode(PHP_EOL, $lines).PHP_EOL))
							return false;
						return true;
					}
					$foundSection = false;
				}
				continue;
			}
			
			if ($foundSection && preg_match('/^'.$key.'\s*=\s*.*$/', $line))
			{
				// Rewrite the line and store the updated file
				$line = preg_replace('/^'.$key.'\s*=\s*"?.+"?(\s*;.*)?$/U', $key.' = '.$newValue.'\\1', $line);
				if (!file_put_contents(ROOTPATH.'/configs/'.$this->iniFile, implode(PHP_EOL, $lines).PHP_EOL))
					return false;
				return true;
			}
		}
		
		// In case the last section of the file is the section we were looking for,
		// but it did not contain the value, then we need to add it here.
		if ($foundSection)
		{
			$append = PHP_EOL.$key.' = '.$newValue.PHP_EOL.PHP_EOL;
			if (!file_put_contents(ROOTPATH.'/configs/'.$this->iniFile, $append, FILE_APPEND))
				return false;
		}
		
		return true;
	}
	
	protected function appendSection($section, array &$values)
	{
		// Check if file exists
		if (!file_exists(ROOTPATH.'/configs/'.$this->iniFile))
		{
			// Just create a new file for the new section then...
			$this->createIniFile($this->iniFile, 'Configuration File', array($section => $values));
			return true;
		}
		
		// Loop through the new values
		$append = PHP_EOL.'['.$section.']'.PHP_EOL;
		foreach ($values as $key => $value)
		{
			$append .= $key.' = '.((is_numeric($value)) ? $value : '"'.$value.'"').PHP_EOL;
		}
		
		if (!file_put_contents(ROOTPATH.'/configs/'.$this->iniFile, $append, FILE_APPEND))
			return false;
		
		return true;
	}
	
	protected function removeSection($section)
	{
		// Check if file exists
		if (!file_exists(ROOTPATH.'/configs/'.$this->iniFile))
			return false;
		
		// Read the contents of the file into an array of lines
		$lines = file(ROOTPATH.'/configs/'.$this->iniFile, FILE_IGNORE_NEW_LINES);
		
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
		
		if (!file_put_contents(ROOTPATH.'/configs/'.$this->iniFile, implode(PHP_EOL, $newLines)))
			return false;
		
		return true;
	}
}

?>