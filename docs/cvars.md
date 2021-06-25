# CVARs
CVARs are **c**onsole **var**iables. They store string, float, or numerical values. These values can be changed via the console or .cfg files, and sometimes even persist across server sessions.

## Introduction
CVARs are accessed through functions. There are two ways to obtain a CVARs; You can either create a new CVAR, or find an existing one. If you create a CVAR that already exists, it will automatically re-use the old one.

## Finding CVARs
Finding CVARs is very simple. For example, let's say you want to use pMsgsPerSecond from antiflood.php.

```php
class example extends Plugins {
	private $pMsgsPerSecond;

	public function __construct() {
		$this->pMsgsPerSecond = $this->findCVAR('pMsgsPerSecond');
	}
}
```

*Note: Plugins::findCVAR() will return INVALID_HANDLE if the ConVar is not found. Keep this in mind if you are trying to read ConVars from other plugins.*

## Creating CVARs
A simple CVAR only requires two parameters, a name and a default value. However, it's a good idea to include a description:

```php
class myplugin extends Plugins {
	private $cvars;

	public function __construct() {
		$this->cvars['Enable'] = $this->createCVAR('myplugin_enabled', 1, 'Sets whether my plugin is enabled.');
	}
}
```

You can also specify value constraints. For example, let's create a cvar called myplugin_ratio which cannot go above 1.0 or below 0.1.

```php
class myplugin extends Plugins {
	private $cvars;

	public function __construct() {
		$this->cvars['Ratio'] = $this->createCVAR(
			'myplugin_ratio',	# CVAR Name
			0.6,				# Default Value
			'Sets a ratio',		# Info
			NULL,				# Flags will be discussed later
			TRUE,				# Has a minimum
			0.1,				# Lowest value permissible
			TRUE,				# Has a maximum
			1.0					# Highest value permissible
		);
	}
}
```

The default value can be of any valid datatype noted above, and it does not restrict future data types that can be used. However, the minimum and maximum constraints always interpret the value as a float.
If you create a CVAR that already exists, you will receive a refrence to that CVAR. Furthermore, the reference itself will be identical, as neither plugin will own the reference. The description, default value, or constraints will not be changed.

## Using/Changing Values

## Flags

## Change Callbacks

http://wiki.alliedmods.net/ConVars_(SourceMod_Scripting)