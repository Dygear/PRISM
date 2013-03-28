<?php

// Very simple login example

$adminUser = isset($_POST['loginUser']) ? $_POST['loginUser'] : '';
$adminPass = isset($_POST['loginPass']) ? $_POST['loginPass'] : '';

if (isset($_GET['logout']) || isset($_POST['logout']))
{
	if (isset($_SESSION['user']))
		unset($_SESSION['user']);
}
else if ($adminUser && $adminPass)
{
	if ($PRISM->admins->isPasswordCorrect($adminUser, $adminPass))
	{
		if (!isset($_SESSION))
			$_SESSION = array();
		$_SESSION['user'] = $adminUser;
	}
	else
		unset($_SESSION['user']);
}

$_RESPONSE->addHeader('Location: /');

?>