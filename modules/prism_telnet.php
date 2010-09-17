<?php

class TelnetHandler extends SectionHandler
{
	private $telnetVars		= array();
	
	public function __construct()
	{
		$this->iniFile = 'telnet.ini';
	}
	
	public function initialise()
	{
		$this->telnetVars = array
		(
			'ip' => '', 
			'port' => 0,
		);

//		if ($this->loadIniFile($this->telnetVars, false))
//		{
//			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE)
//				console('Loaded '.$this->iniFile);
//		}
		
		return true;
	}
}

?>