# Commands
There are two types of commands that have their own sub types, these are;

* **Server Commands** - Fired from one of the following input methods.
	* The server console itself, for example / commands. (PRISM can not directly detect these, but can issue commands in this way).
	* The remote console - Known as RCON Commands, can be issued from the PRISM Console (Available Directly on Linux & Max OS X).
	* The Plugin::serverCommand function.
* **Console Commands** - Fire from one of the following input methods.
	* A client local console (/i)
	* A client remote console (/o)
	* Any other server command input methods.
		* Say commands.
		* PRISM Console via Telnet (For Windows)
		* PRISM Console via HTTP Server.
		* PRISM RCON say command. (prism rcon <command>)

For server commands, there is no client username, it is NULL. For console/client commands, the issuing client's username is given, but it may be NULL to indicate that the command is from the server.

## Server Commands
As noted above, server commands are fired through the server console, whether remote or local.
Server commands are registered through the registerServerCommand() function defined in console.inc. When registering a server command, you may be hooking to a command that is already hooked by another method or another plugin and thus the return value is important.

* **PLUGIN_CONTINUE** - The command will be processed and any subsueqent hooks into this command will be proccessed.
* **PLUGIN_HANDLED** - The command will be processed, no further hooks will be called for this command until it issued again.
* **PLUGIN_STOP** - This is used only to stop repeating timers within PRISM.

## Adding Commands
```php
class example extends Plugins {
	public function __construct() {
		$this->registerSayCommand('command', 'callbackMethod', 'Information about the triggers purpose goes here.');
	}
	public function callbackMethod($Msg, $UName) {
		var_dump($Msg, $UName);
		return PLUGIN_HANDLED;
	}
}
```

## Hooking Commands
A common example is registering a say command, as shown above. Let's say we want to tell players how many clients are in the server.

Before we implement this, a common point of confusion is that the whole 'Msg' is returned by default, meaning you get not only the args, but also the trigger string.

* Argument string: !prism command arg1 arg2
* Argument count: 4
* Argument #1: !prism
* Argument #2: command
* Argument #3: arg1
* Argument #4: arg2

```php
class example extends Plugins {
	public function __construct() {
		$this->registerSayCommand('command', 'callbackMethod', 'Information about the function');
	}
	public function callbackMethod($Msg, $UName) {
		$this->clientPrint($UName, PRINT_CHAT, count($this->getClients()));
	}
}
```

## Creating Admin Commands
The only diffrence from creating an admin command verse that of a regular command is one more argument to the registerSayCommand method. This argument can be omitted if you want the command available to all clients. However, should you chose to use it, it must be an int value, or a bit mask combonation from the ADMIN_* defines.

Let's create a simple admin command which kicks another client by their user name.
```php
class example extends Plugins {
	public function __construct() {
		$this->registerSayCommand('prism kick', 'cmdKick', '<UName> - Kicks a client from the server', ADMIN_KICK);
	}
	public function cmdKick($Msg, $UName) {
		if (($argc = count($argv = explode(' ', $Msg))) < 3) {
			$this->clientPrint($UName, PRINT_CHAT, 'Usage: \`prism kick <UName>\`');

			return PLUGIN_HANDLED;
		}

		$target = array_pop($argv);

		foreach ($this->getClients() as $ClUName => $client) {
			if ($Target == $ClUName) {
				$this->serverCommand("/kick $target");
				return PLUGIN_HANDLED;
			}
		}

		$this->clientPrint($UName, PRINT_CHAT, "Could not find any player with the name: '{$target}'.");
		return PLUGIN_HANDLED;
	}
}
```

## Immunity
In our previous example, we did not take immunity into account. Two functions are used to find this information.

* Admin::canAdminTarget(): Tests raw UName values for immunity.
* Admin::canUserTarget(): Tests in-game clients for immunity.

When checking for immunity, the following heuristics are performed in this exact order:

* If the targeting client is not an admin, targeting fails.
* If the targetted client is not an admin, targeting succeeds.
* If the targeting client has ADMIN_ROOT, targeting succeeds.
* If the targetted client has ADMIN_IMMUNITY AND the targeting client does not have ADMIN_UNIMUNIZE, targeting fails.
* If no conclusion is reached via the previous steps, targeting succeeds.

So, how can we adapt our function about to use immunity?

```php
public function cmdKick($Msg, $UName) {
	if (($argc = count($argv = explode(' ', $Msg))) < 3) {
		$this->clientPrint('Useage: \`prism kick <UName>\`', PRINT_CHAT, $UName);
		return PLUGIN_HANDLED;
	}

	$target = array_pop($argv);

	foreach ($this->getClients() as $ClUName => $client) {
		if ($Target == $ClUName) {
			$this->serverCommand("/kick $target");
			return PLUGIN_HANDLED;
		}
	}

	if (!$this->canUserTarget($UName, $Target)) {
		$this->clientPrint($UName, PRINT_CHAT, 'You cannot target this client.');
		return PLUGIN_HANDLED;
	}

	$this->clientPrint($UName, PRINT_CHAT, "Could not find any player with the name: '{$target}'.");
	return PLUGIN_HANDLED;
}
```