<?php
/**
 * PHPInSimMod - Admin Module
 * @package PRISM
 * @subpackage Admin
*/

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
define('ADMIN_OBJECT',				16384);		# Flag "o", Allows you to set & remove autox objects in the track.
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

define('ADMIN_MOD', ADMIN_BAN + ADMIN_KICK + ADMIN_SPECTATE); # Low level access to some basic admin commands.
define('ADMIN_ADMIN', ADMIN_MOD + ADMIN_CFG + ADMIN_GAME + ADMIN_HOST + ADMIN_TRACK + ADMIN_PENALTIES + ADMIN_RCM + ADMIN_VOTE); # Same as giving /admin password to this user.
define('ADMIN_SERVER', ADMIN_ADMIN + ADMIN_IMMUNITY + ADMIN_UNIMMUNIZE); # Automicaly given to server hosts.
define('ADMIN_ROOT', ADMIN_ALL); # Root level access. (Full Access)

/**
 * AdminHandler public functions :
 * ->initialise()										# (re)loads the config files and (re)connects to the host(s)
 * ->getAdminsInfo()									# get information about all user accounts
 * ->getAdminInfo(&$username)							# get information about a single user account
 * ->adminExists(&$username)							# check if a user account exists
 * ->isPasswordCorrect(&$username, &$password)			# verify username + password
 * ->addAccount($username, $password, $accessFlags = 0, $connection = '', $store = true)	# Create a new admin account
 * ->deleteAccount($username, $password = '', $store = true)	# Remove an account
 * ->addAccessFlags($username, $flags, $store = true)			# Add extra accessFlags permissions
 * ->revokeAccessFlags($username, $flags, $store = true)		# Revoke certain accessFlags permissions
*/
class AdminHandler extends SectionHandler
{
	private $admins		= array();

	public function __construct()
	{
		$this->iniFile = 'admins.ini';
	}
	
	public function initialise()
	{
		global $PRISM;
		
		$this->admins = array();
		
		if ($this->loadIniFile($this->admins)) {
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE) {
				console('Loaded '.$this->iniFile);
			}
		} else {
			# We ask the client to manually input the user details here.
			require_once(ROOTPATH . '/modules/prism_interactive.php');
			Interactive::queryAdmins($this->admins);
			
			# Then build a admins.ini file based on these details provided.
			$extraInfo = <<<ININOTES
;
; Line starting with ; is a comment
;
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
;
; Format of admin account:
; [lfs username]  ; Should always be lowercase.
; password = "<password>"
; accessFlags = "<Access flags>"
; connection = "<host id name>"
; realmDigest = "<md5 hash>"	; never change this yourself
;
; NOTE about the username - it should be lower case only.
; NOTE about the password - you can write it in plain text.
; When you then run PRISM, the password will be converted into a safer format & the username will be lowercased.
;

ININOTES;
			if ($this->createIniFile('Admins Configuration File', $this->admins, $extraInfo)) {
				console('Generated config/'.$this->iniFile);
			}
		}
		
		// Read account vars to verify / maybe generate password hashes
		foreach ($this->admins as $username => &$details)
		{
			// Convert password?
			if (strlen($details['password']) != 40) {
				$details['realmDigest'] = md5($username.':'.HTTP_AUTH_REALM.':'.$details['password']);
				$details['password'] = sha1($details['password'].$PRISM->config->cvars['secToken']);
				
				// Rewrite these particular config lines in admins.ini
				$this->rewriteLine($username, 'password', $details['password']);
				$this->rewriteLine($username, 'realmDigest', $details['realmDigest']);
			}
			
			// Convert flags?
			if (!is_numeric($details['accessFlags'])) {
				$details['accessFlags'] = flagsToInteger($details['accessFlags']);
			} else {
				$this->rewriteLine($username, 'accessFlags', flagsToString($details['accessFlags']));
			}
		}

		# Crazy stuff we have to do to make sure that usernames are lowercase.
		$tempAdmins = array();
        
		foreach ($this->admins as $username => &$details) {
			$tempAdmins[strToLower($username)] = $details;
		}

		$this->admins = $tempAdmins;

		return TRUE;
	}
	
	public function &getAdminsInfo()
	{
		$info = array();
        
		foreach ($this->admins as $user => $details) {
			$info[$user] = array(
				'accessFlags' => $details['accessFlags'],
				'connection' => $details['connection'],
				'temporary' => isset($details['temporary']) ? $details['temporary'] : false,
			);
		}
        
		return $info;
	}

	public function getAdminInfo(&$username)
	{
		$username = strToLower($username);

		if (!isset($this->admins[$username])) {
			return false;
		}

		return array(
			'accessFlags' => $this->admins[$username]['accessFlags'],
			'connection' => $this->admins[$username]['connection'],
			'temporary' => isset($this->admins[$username]['temporary']) ? $this->admins[$username]['temporary'] : false,
		);
	}
	
	public function getRealmDigest(&$username)
	{
		$username = strToLower($username);
        
		if (!isset($this->admins[$username])) {
			return false;
		}

		return $this->admins[$username]['realmDigest'];
	}
	
	public function adminExists(&$username)
	{
		$username = strToLower($username);
		return isset($this->admins[$username]);
	}

	public function isPasswordCorrect(&$username, &$password)
	{
		$username = strToLower($username);
		global $PRISM;
		
		return (
			isset($this->admins[$username]) &&
			$this->admins[$username]['password'] == sha1($password.$PRISM->config->cvars['secToken'])
		);
	}
	
	public function addAccount($username, $password, $accessFlags = 0, $connection = '', $store = true)
	{
		global $PRISM;

		$username = strToLower($username);
		
		if (isset($this->admins[$username])) {
			return false;
		}
		
		// Add new user to $this->admins
		$this->admins[$username] = array(
			'password'		=> sha1($password.$PRISM->config->cvars['secToken']),
			'connection'	=> $connection,
			'accessFlags'	=> $accessFlags,
			'realmDigest'	=> md5($username.':'.HTTP_AUTH_REALM.':'.$password),
		);
		
		// Add new user section to admin.ini
		if ($store)	{
			$this->appendSection($username, $this->admins[$username]);
		} else {
			$this->admins[$username]['temporary'] = true;
		}

		return true;
	}
	
	public function makePermanent($username)
	{
		$username = strToLower($username);
        
		if (!isset($this->admins[$username]) || !isset($this->admins[$username]['temporary']) || !$this->admins[$username]['temporary']) {
			return false;
		}
		
		unset($this->admins[$username]['temporary']);
		$this->appendSection($username, $this->admins[$username]);
		
		return true;
	}
	
	public function deleteAccount($username, $store = true)
	{
		$username = strToLower($username);
        
		if (!isset($this->admins[$username])) {
			return false;
		}
		
		// Remove the account from $this->admins
		unset($this->admins[$username]);

		// Remove user's section from admin.ini
		if ($store) {
			$this->removeSection($username);
		}

		return true;
	}
	
	public function changePassword($username, $password, $store = true)
	{
		$username = strToLower($username);
		global $PRISM;
		
		console('Writing new password for '.$username);
        
		if (!isset($this->admins[$username])) {
			return false;
		}

		// Update the password in $this->admins
		$this->admins[$username]['realmDigest'] = md5($username.':'.HTTP_AUTH_REALM.':'.$password);
		$this->admins[$username]['password'] = sha1($password.$PRISM->config->cvars['secToken']);

		// Rewrite password and realmDigest lines for user in admins.ini
		if ($store) {
			$this->rewriteLine($username, 'password', $this->admins[$username]['password']);
			$this->rewriteLine($username, 'realmDigest', $this->admins[$username]['realmDigest']);
		}

		return true;
	}
	
	public function setAccessFlags($username, $flags, $store = true)
	{
		$username = strToLower($username);
        
		if (!isset($this->admins[$username])) {
			return false;
		}

		// Set the permissions
		$this->admins[$username]['accessFlags'] = $flags;

		// Rewrite accessFlags line for user in admins.ini
		if ($store) {
			$this->rewriteLine($username, 'accessFlags', flagsToString($this->admins[$username]['accessFlags']));
		}

		return true;
	}
	
	public function addAccessFlags($username, $flags, $store = true)
	{
		$username = strToLower($username);
        
		if (!isset($this->admins[$username])) {
			return false;
		}

		// Add the permissions
		$this->admins[$username]['accessFlags'] |= $flags;

		// Rewrite accessFlags line for user in admins.ini
		if ($store) {
			$this->rewriteLine($username, 'accessFlags', flagsToString($this->admins[$username]['accessFlags']));
		}

		return true;
	}
	
	public function revokeAccessFlags($username, $flags, $store = true)
	{
		$username = strToLower($username);
        
		if (!isset($this->admins[$username])) {
			return false;
		}

		// Revoke the permissions
		$this->admins[$username]['accessFlags'] &= ~$flags;

		// Rewrite accessFlags line for user in admins.ini
		if ($store) {
			$this->rewriteLine($username, 'accessFlags', flagsToString($this->admins[$username]['accessFlags']));
		}
		
		return true;
	}
}
