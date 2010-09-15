<?php
class admin extends Plugins
{
	const NAME = 'Admin Base';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Admin functions for PRISM.';

	public function __construct()
	{
		# Debug
		$this->registerSayCommand('!', 'cmdDebug', 'Debug Console.');

		# Help
		$this->registerSayCommand('help', 'cmdHelp', 'Displays this command list.');
		$this->registerSayCommand('prism help', 'cmdHelp', 'Displays this command list.');

		# Plugins
		$this->registerSayCommand('prism plugins', 'cmdPluginList', 'Displays a list of plugins.');
		$this->registerSayCommand('prism plugins list', 'cmdPluginList', 'Displays a list of plugins.');

		# Admins
		$this->registerSayCommand('prism admins', 'cmdAdminList', 'Displays a list of admins.');
		$this->registerSayCommand('prism admins list', 'cmdAdminList', 'Displays a list of admins.');
		$this->registerSayCommand('prism admins reload', 'cmdAdminReload', 'Reloads the admins.', ADMIN_CFG);
	}

	// Debug
	public function cmdDebug($cmd, $plid, $ucid)
	{	// These will all be registed console commands soon.
		global $PRISM;

		# default
		console(NULL); # Print a blank line.
		console('Available Commands:');
		console('	h - show host info');
		console('	I - re-initialise PRISM (reload ini files / reconnect to hosts / reset http socket');
		console('	p - show plugin info');
		console('	x - exit PHPInSimMod');
		console('	w - show www connections');
		console('	c - show command list');

		# c
		console(NULL); # Print a blank line.
		console(sprintf('%32s %64s', 'COMMAND', 'DESCRIPTOIN'));
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			foreach ($details->sayCommands as $command => $detail)
			{
				console(sprintf('%32s - %64s', $command, $detail['info']));
			}
		}

		# h
		console(NULL); # Print a blank line.
		console(sprintf('%14s %28s:%-5s %8s %22s', 'Host ID', 'IP', 'PORT', 'UDPPORT', 'STATUS'));
		foreach ($PRISM->hosts->getHostsInfo() as $host)
		{
			$status = (($host['connStatus'] == CONN_CONNECTED) ? '' : (($host['connStatus'] == CONN_VERIFIED) ? 'VERIFIED &' : ' NOT')).' CONNECTED';
			$socketType = (($host['socketType'] == SOCKTYPE_TCP) ? 'tcp://' : 'udp://');
			console(sprintf('%14s %28s:%-5s %8s %22s', $host['id'], $socketType.$host['ip'], $host['port'], $host['udpPort'], $status));
		}

		# I
		console(NULL); # Print a blank line.
#		console('RE-INITIALISING PRISM...');
#		$PRISM->initialise(null, null);

		# p
		console(NULL); # Print a blank line.
		console(sprintf('%28s %8s %24s %64s', 'NAME', 'VERSION', 'AUTHOR', 'DESCRIPTION'));
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			console(sprintf("%28s %8s %24s %64s", $plugin::NAME, $plugin::VERSION, $plugin::AUTHOR, $plugin::DESCRIPTION));
		}

		# x
		console(NULL); # Print a blank line.
#		$PRISM->isRunning = FALSE;

		# w
		console(NULL); # Print a blank line.
		$httpNumClients = $PRISM->http->getHttpNumClients();
		console(sprintf('%15s:%5s %5s', 'IP', 'PORT', 'LAST ACTIVITY'));
		for ($k = 0; $k < $httpNumClients; ++$k)
		{
			$httpClient = $PRISM->http->getHttpClient($k);
			$lastAct = time() - $httpClient->lastActivity;
			console(sprintf('%15s:%5s %5d %13d', $httpClient->ip, $httpClient->port, $lastAct));
		}
		console('Counted '.$httpNumClients.' http client'.(($httpNumClients == 1) ? '' : 's'));

		return PLUGIN_CONTINUE;
	}

	// Help
	public function cmdHelp($cmd, $plid, $ucid)
	{
		global $PRISM;

		// (For button alignments)#  LEFT       LEFT
		echo sprintf("%32s %64s", 'COMMAND', 'DESCRIPTOIN') . PHP_EOL;
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			foreach ($details->sayCommands as $command => $detail)
				echo sprintf("%32s - %64s", $command, $detail['info']) . PHP_EOL;
		}

		return PLUGIN_CONTINUE;
	}

	// Plugins
	public function cmdPluginList($cmd, $plid, $ucid)
	{
		global $PRISM;

		// (For button alignments)		#  MIDDLE    MIDDLE   RIGHT     LEFT
		echo sprintf("%28s %8s %24s %64s", 'NAME', 'VERSION', 'AUTHOR', 'DESCRIPTION') . PHP_EOL;
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			echo sprintf("%28s %8s %24s %64s", $plugin::NAME, $plugin::VERSION, $plugin::AUTHOR, $plugin::DESCRIPTION) . PHP_EOL;
		}

		return PLUGIN_CONTINUE;
	}

	// Admins
	public function cmdAdminList($cmd, $plid, $ucid)
	{
		global $PRISM;

		// (For button alignments)		#  MIDDLE    MIDDLE   RIGHT     LEFT
		echo sprintf("%28s %8s %24s %64s", 'NAME', 'VERSION', 'AUTHOR', 'DESCRIPTION') . PHP_EOL;
		foreach ($PRISM->admins->getAdmins() as $user => $details)
		{
			echo $user;
			print_r($details);
		}

		return PLUGIN_CONTINUE;
	}

	public function cmdAdminReload($cmd, $plid, $ucid)
	{
		global $PRISM;

		print_r($PRISM->admins->getAdmins());

		return PLUGIN_CONTINUE;
	}
}
?>