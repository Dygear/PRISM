<?php

require_once(ROOTPATH . '/modules/prism_sectionhandler.php');

class ConfigHandler extends SectionHandler
{
//	public $prefix				= '!';
//	public $debugMode			= PRISM_DEBUG_ALL;
//	public $logMode				= 7;
//	public $logFileMode			= 3;
//	public $relayIP				= 'isrelay.lfs.net';
//	public $relayPort			= 47474;
//	public $relayPPS			= 2
//	public $dateFormat			= 'M jS Y';
//	public $timeFormat			= 'H:i:s';
//	public $logFormat			= 'm-d-y@H:i:s';
//	public $logNameFormat		= 'Ymd';
//	public $httpIP				= '0.0.0.0';
//	public $httpPort			= 1800;
	public $cvars				= array('prefix'		=> '!',
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
									'httpIP'		=> '0.0.0.0',
									'httpPort'		=> '1800');
	
	public function initialise()
	{
		if ($this->loadIniFile($this->cvars, 'cvars.ini', FALSE))
		{
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded cvars.ini');
		}
		else
		{
			console('Using cvars defaults.');
			if ($this->createIniFile('cvars.ini', 'PHPInSimMod Configuration Variables', array('prism' => &$this->cvars)))
				console('Generated config/cvars.ini');
		}

		return true;
	}
}

?>