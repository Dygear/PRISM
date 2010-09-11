<?php

require_once(ROOTPATH . '/modules/prism_sectionhandler.php');

class ConfigHandler extends SectionHandler
{
	public $cvars	= array
		(
			'prefix'		=> '!',
			'debugMode'		=> PRISM_DEBUG_ALL,
			'logMode'		=> 7,
			'logFileMode'	=> 3,
			'relayIP'		=> 'isrelay.lfs.net',
			'relayPort'		=> 47474,
			'relayPPS'		=> 2,
			'dateFormat'	=> 'M jS Y',
			'timeFormat'	=> 'H:i:s',
			'logFormat'		=> 'm-d-y@H:i:s',
			'logNameFormat'	=> 'Ymd',
			'secToken'		=> 'X-0ZbIY)TN>.@sr}',
			'phpLocation'	=> '',
		);
	
	public function initialise()
	{
		global $PRISM;
		
		if ($this->loadIniFile($this->cvars, 'cvars.ini', FALSE))
		{
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded cvars.ini');
		}
		else
		{
			$this->cvars['secToken'] = str_replace(array('"', '\'', ' '), '.', createRandomString(16));
			$this->cvars['phpLocation'] = findPHPLocation($PRISM->isWindows);
			
			console('Using cvars defaults.');
			if ($this->createIniFile('cvars.ini', 'PHPInSimMod Configuration Variables', array('prism' => &$this->cvars)))
				console('Generated config/cvars.ini');
		}

		return true;
	}
}

?>