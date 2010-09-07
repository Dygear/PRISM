<?php
class admin extends Plugins
{
	const NAME = 'Admin Base';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = '';

	public $adminsAccess = array();
	private $adminPasswords = array();

	public function __construct(&$parent)
	{
		$this->parent =& $parent;
		$this->cmdLoad();
		$this->registerPacket('onClientConnect', ISP_NCN);
		$this->registerPacket('onClientDisconnect', ISP_CNL);
		$this->registerPacket('onClientRenamed', ISP_CPR);
		$this->registerCommand('prism admins list', 'cmdList', NULL, '- Displays a list of admins.');
		$this->registerCommand('prism admins reload', 'cmdLoad', ADMIN_CFG);
	}

	public function onClientConnect($packet)
	{
		
	}

	public function onClientDisconnect($packet)
	{
		
	}

	public function onClientRenamed($packet)
	{
		echo "Command: $cmd from $plid, $ucid";
	}

	public function cmdList($cmd, $plid, $ucid)
	{
		echo "Command: $cmd from $plid, $ucid";
	}

	public function cmdLoad($cmd = NULL, $plid = NULL, $ucid = NULL)
	{
		if (($loadedAdmins = $this->loadAdmins()) === FALSE)
		{
#			if ($this->parent->cvars['debugMode'] & PRISM_DEBUG_PLUGINS)
				console('Could not load any admins, could not find the file.');
		}
		else if ($loadedAdmins == 1)
		{
#			if ($this->parent->cvars['debugMode'] & PRISM_DEBUG_PLUGINS)
				console('Loaded 1 admin.');
		}
		else
		{
#			if ($this->parent->cvars['debugMode'] & PRISM_DEBUG_PLUGINS)
				console("Loaded $loadedAdmins admins.");
		}
	}

	public function loadAdmins()
	{
		$usersFilePath = PHPInSimMod::ROOTPATH.'/configs/users.ini';

		# If theres no file, we have a problem.
		if (!is_file($usersFilePath))
			return FALSE;

		$file = file($usersFilePath);

		$adminCount = 0; # Running count of how many admins we loaded.
		$currentSection = NULL; # Setups up our global vs local admins.
		foreach ($file as $lineNumber => $line)
		{
			$line = trim($line);

			# Skip empty lines
			if ($line == '')
				continue;

			# If this is the case, this line is comment, skip this line.
			if ($line[0] == ';') 
				continue;

			# Is this is the start of a new section?
			if ($line[0] == '[')
			{
				$currentSection = substr($line, 1, -1); # This could be made safer with regular expression. (But then I'd have two problems :))
				$this->adminsAccess[$currentSection] = array();
				continue;
			}

			# Make sure we enough input.
			if (($count = count(explode(' ', $line))) < 3)
				continue;

			# Spearate and Sanitize + Presurves end of line comments.
			if ($count != 3)
				list($username, $password, $accessFlags, $comment) = explode(' ', $line, 4);
			else
			{
				list($username, $password, $accessFlags) = explode(' ', $line, 3);
				$comment = NULL;
			}

			$username = str_replace('"', NULL, $username);
			$password = str_replace('"', NULL, $password);
			$accessFlags = str_replace('"', NULL, $accessFlags);

			// From here we add the admin to our list.
			# Convert something like 'abcxyz' to something we can do bitwise operations on.
			$accessBits = $this->readFlags($accessFlags);

			# Setups up global vs local admins.
			if ($currentSection == NULL)
				$this->adminsAccess[$username] = $accessBits;
			else
				$this->adminsAccess[$currentSection][$username] = $accessBits;

			# Read their password field
			if (strlen($password) == 40)
				$this->adminPasswords[$username] = $password;
			else
			{	# $password is in plain text, secure it!
				$updateFile = TRUE;
				$this->adminPasswords[$username] = sha1($password);
				# Update the file line with these details.
				$file[$lineNumber] = "\"$username\" \"{$this->adminPasswords[$username]}\" \"$accessFlags\" $comment";
			}

			# We added an admin!
			++$adminCount;
		}

		# Updates the passwords to include the hash, and there by secure the file.
		if (isset($updateFile) && $updateFile == TRUE)
			file_put_contents($usersFilePath, implode(PHP_EOL, $file));

		return $adminCount;
	}

	public function isPasswordCorrect($username, $password)
	{
		# Let's make sure that the admin is set in the first place.
		if (!isset($this->adminPasswords[$username]))
			return FALSE;

		if ($this->adminPasswords[$username] == sha1($password))
			return TRUE;

		return FALSE;
	}

}
?>