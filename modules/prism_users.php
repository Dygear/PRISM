<?php

require_once(ROOTPATH . '/modules/prism_sectionhandler.php');

// Admin Flags
define('ADMIN_NONE',				0);			# No flags.
define('ADMIN_ACCESS',				1);			# Flag "a", Allows you to issue commands from the remote console or web admin area.
define('ADMIN_BAN',					2);			# Flag "b", Allows you to ban and unban clients.
define('ADMIN_CFG',					4);			# Flag "c", Allows you to change the, runtime, configuration of LFS.
define('ADMIN_CVAR',				8);			# Flag "d", Allows you to change the, runtime, configuration of PRISM.
define('ADMIN_LEVEL_E',				16);		# Flag "e", 
define('ADMIN_LEVEL_F',				32);		# Flag "f", 
define('ADMIN_GAME',				64);		# Flag "g", Allows you to change the way the game is played.
define('ADMIN_HOST',				128);		# Flag "h", Allows you to change the way the host runs.
define('ADMIN_IMMUNITY',			256);		# Flag "i", Allows you to be immune to admin commands.
define('ADMIN_LEVEL_J',				512);		# Flag "j", 
define('ADMIN_KICK',				1024);		# Flag "k", Allows you to kick clients from server.
define('ADMIN_LEVEL_L',				2048);		# Flag "l", 
define('ADMIN_TRACK',				4096);		# Flag "m", Allows you to change the track on the server.
define('ADMIN_LEVEL_N',				8192);		# Flag "n", 
define('ADMIN_LEVEL_O',				16384);		# Flag "o", 
define('ADMIN_PENALTIES',			32768);		# Flag "p", Allows you to set a penalty on any client.
define('ADMIN_RESERVATION',			65536);		# Flag "q", Allows you to join in a reserved slot.
define('ADMIN_RCM',					131072);	# Flag "r", Allows you to send race control messages.
define('ADMIN_SPECTATE',			262144);	# Flag "s", Allows you to spectate and pit a client or all clients.
define('ADMIN_CHAT',				524288);	# Flag "t", Allows you to send messages to clients in their chat area.
define('ADMIN_UNIMMUNIZE',			1048576);	# Flag "u", Allows you to run commands on immune admins also.
define('ADMIN_VOTE',				2097152);	# Flag "v", Allows you to start or stop votes for anything.
define('ADMIN_LEVEL_W',				4194304);	# Flag "w", 
define('ADMIN_LEVEL_X',				8388608);	# Flag "x", 
define('ADMIN_LEVEL_Y',				16777216);	# Flag "y", 
define('ADMIN_LEVEL_Z',				33554432);	# Flag "z", 
define('ADMIN_ALL',					134217727);	# All flags, a - z.

class UserHandler extends SectionHandler
{
	private $users		= array();

	public function getUsers()
	{
		return $this->users;
	}

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
; f - Functions (/restart, /qualify, /end & /names)
; g - game commands (/qual, /laps & /hours)
; h - host commands (/ip, /port, /maxguests, /insim & /reinit)
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
			if (strlen($details['password']) != 40)
			{
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

		return TRUE;
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