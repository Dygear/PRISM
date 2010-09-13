<?php

	define('ADMIN_ALL',					134217727);	# All flags, a - z.
	include('./modules/prism_functions.php');

	$userFlags = 'abcdefgz';

	$bitFlags = flagsToInteger($userFlags);

	echo sprintf('%32b', $bitFlags) . ' : ' . $bitFlags . PHP_EOL;

	$strFlags = flagsToString($bitFlags);

	echo $strFlags . PHP_EOL;

?>