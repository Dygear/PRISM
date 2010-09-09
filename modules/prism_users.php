<?php

require_once(ROOTPATH . '/modules/prism_sectionhandler.php');

class UserHandler extends SectionHandler
{
	private $users		= array();
	
	public function initialise()
	{
		global $PRISM;
		
		if ($this->loadIniFile($this->users, 'users.ini'))
		{
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded users.ini');
		}
		else
		{
			# We ask the client to manually input the user details here.
			require_once(ROOTPATH . '/modules/prism_interactive.php');
			Interactive::queryUsers($this->users);
			
			# Then build a users.ini file based on these details provided.
			if ($this->createIniFile('users.ini', 'User Configuration File', $this->users))
				console('Generated config/users.ini');
		}
		
		return true;
	}
}

?>