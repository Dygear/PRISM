<?php

require_once(ROOTPATH . '/modules/prism_sectionhandler.php');

class UserHandler extends SectionHandler
{
	private $users		= array();
	
	public function initialise()
	{
		global $PRISM;
		
		$this->users = array();
		
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
			$extraInfo = <<<ININOTES
; Line starting with ; is a comment

; Access flags:
; a - access to remote console (RCON) and rcon password cvar (by `!prism cvar` command)
; b - /ban and /unban commands (`prism ban` and `prism unban` commands)
; c - access to `prism cvar` command (not all cvars will be available)
; d - access to `prism cfg` command (allows you to change lfs configuration settings)
; e - Env commands (/wind, /weather)
; f - Functions (/restart, /qualify, /end, /names & /reinit)
; g - game commands (/qual, /laps & /hours)
; h - host commands (/ip, /port, /maxguests & /insim)
; i - immunity (cannot be kicked/baned/speced/pited and affected by other commmands)
; j - 
; k - /kick command (`prism kick` command)
; l - 
; m - access to /track `prism track` & `prism map` command
; n - /track command
; o - options (/maxguests, /adminslots, /carsmax, /carshost, /carsguest & /pps X)
; p - access to /pass cvar (by `!prism cvar` command)
; q - 
; r - reservation (can join on reserved slots)
; s - /spec and /pit commands
; t - chat commands (`prism chat` and other chat commands)
; u - Unimmunized, given the ability to run commands on immunized admins.
; v - vote commands (/vote & `prism vote`)
; w - 
; x - 
; y - 
; z - 

; Format of admin account:
; [LFS Username]
; password = "<password>"
; accessFlags = "<Access flags>"
;
; NOTE about the password - you can write it in plain text.
; When you then run PRISM, the password will be converted into a safer format.
;

ININOTES;
			if ($this->createIniFile('users.ini', 'User Configuration File', $this->users, $extraInfo))
				console('Generated config/users.ini');
		}
		
		// Read account vars to verify / maybe generate password hashes
		foreach ($this->users as $username => &$details)
		{
			// Convert password?
			if (strlen($details['password']) != 40) {
				$details['password'] = sha1($details['password'].$PRISM->config->cvars['secToken']);
				
				// Rewrite this particular config line in users.ini
				$this->rewriteLine('users.ini', $username, 'password', $details['password']);
			}
			
			// Convert flags?
			if (!is_numeric($details['accessFlags']))
			{
				$details['accessFlags'] = flagsToInteger($details['accessFlags']);
			}
		}
		
		return true;
	}
	
	public function isPasswordCorrect(&$username, &$password)
	{
		global $PRISM;
		
		return (
			isset($this->users[$username]) && 
			$this->users[$username]['password'] == sha1($password.$PRISM->config->cvars['secToken'])
		);
	}
}

?>