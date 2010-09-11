<?php

class IniLoader
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
			return FALSE;
		
		// Read the contents of the file into an array of lines
		$lines = file(ROOTPATH.'/configs/'.$iniFile, FILE_IGNORE_NEW_LINES);
		
		// Loop through the lines to detect Section and then Key
		$foundSection = false;
		foreach ($lines as $num => &$line)
		{
			if ($line == '['.$section.']')
			{
				$foundSection = true;
				continue;
			}
			
			if ($foundSection && preg_match('/^'.$key.'\s*=\s*.*$/', $line))
			{
				// Check if there's a comment on this line (after the key = value)
				$comment = array();
				preg_match('/^[a-zA-Z0-9]+\s*=\s*"?.+"?\s*(;.*)$/U', $line, $comment);
				
				// Rewrite this line
				$line = $key.' = '.((is_numeric($value)) ? $value : '"'.$value.'"').((isset($comment[1])) ? "\t".$comment[1] : '');

				file_put_contents(ROOTPATH.'/configs/'.$iniFile, implode(PHP_EOL, $lines));
				break;
			}
		}
	}
}

?>