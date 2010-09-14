<?php
require_once('testinclude.php');

// Cookie examples
$_RESPONSE->setCookie('testCookie', 'a test value in this cookie', time() + 60*60*24*7, '/', $SERVER['SERVER_NAME']);
$_RESPONSE->setCookie('anotherCookie', '#@$%"!$:;%@{}P$%', time() + 60*60*24*7, '/', $SERVER['SERVER_NAME']);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en">
<head>
<title>Prism http server test page</title>
<style type="text/css">
div.loginArea {
	float: right;
	clear: both;
}
</style>
</head>
<body>
<?php

$html = '';
if (isset($_SESSION['user']))
{
	$html .= '<div class="loginArea">';
	$html .= 'Welcome '.htmlspecialchars($_SESSION['user']).'.';
	$html .= ' -<a href="/login.php?logout">Logout</a>';
	$html .= '</div>';
}
else
{
	$html .= '<div class="loginArea">';
	$html .= '<form method="post" action="/login.php">';
	$html .= '<input type="text" name="loginUser" value="" size="16" maxlength="24" />';
	$html .= '<input type="password" name="loginPass" value="" size="16" maxlength="24" />';
	$html .= '<input type="submit" value="Login" />';
	$html .= '</form>';
	$html .= '</div>';
}
$html .= '<a href="/"><img src="images/test.gif" border="0" alt="" style="float: right; clear: both;" /></a>';
$html .= someTestFunction().'<br />';

if (isset($_SESSION))
{
	$html .= 'The following SESSION values have been found :<br />';
	if (is_array($_SESSION))
	{
		foreach ($_SESSION as $k => $v)
			$html .= htmlspecialchars($k.' => '.$v).'<br />';
	}
	else
		$html .= htmlspecialchars($_SESSION).'<br />';
	$html .= '<br />';
}

if (count($_COOKIE) > 0)
{
	$html .= '		The following COOKIE values have been found :<br />';
	foreach ($_COOKIE as $k => $v)
		$html .= htmlspecialchars($k.' => '.$v).'<br />';
	$html .= '<br />';
}

if (count($_GET) > 0)
{
	$html .= '		You submitted the following GET values :<br />';
	foreach ($_GET as $k => $v)
		$html .= htmlspecialchars($k.' => '.$v).'<br />';
	$html .= '<br />';
}

if (count($_POST) > 0)
{
	$html .= '		You submitted the following POST values :<br />';
	foreach ($_POST as $k => $v)
	{
		if (is_array($v))
		{
			$html .= '<strong>'.$k.'-array</strong><br />';
			foreach ($v as $k2 => $v2)
				$html .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$k.'['.htmlspecialchars($k2).'] => '.htmlspecialchars($v2).'<br />';
		}
		else
		{
			$html .= htmlspecialchars($k.' => '.$v).'<br />';
		}
	}
	$html .= '<br />';
}

$html .= 'Here\'s a form to test POST requests<br />';
$html .= '<form method="post" action="/?'.$SERVER['QUERY_STRING'].'">';
$html .= '';
for ($c=0; $c<3; $c++)
	$html .= '			name="postval'.$c.'" : <input type="text" name="postval'.$c.'" value="'.htmlspecialchars(createRandomString(24)).'" maxlength="48" size="32" /><br />';
for ($c=0; $c<3; $c++)
	$html .= '			name="postval[blah'.$c.']" : <input type="text" name="postval[blah'.$c.']" value="'.htmlspecialchars(createRandomString(24)).'" maxlength="48" size="32" /><br />';
for ($c=0; $c<3; $c++)
	$html .= '			name="postval[]" : <input type="text" name="postval[]" value="'.htmlspecialchars(createRandomString(24)).'" maxlength="48" size="32" /><br />';
$html .= '			name="postvalother" : <input type="text" name="postvalother" value="" maxlength="48" size="32" /><br />';
$html .= '			<input type="submit" value="Submit the form" />';
$html .= '		</form>';

for ($x = 0; $x < 100; $x++)
{
	$html .= '<br /><br />SERVER values :<br />';
	foreach ($SERVER as $k => $v)
		$html .= htmlspecialchars($k.' => '.$v).'<br />';
}

$html .= '	</body>';
$html .= '</html>';

echo $html;

//unset($_SESSION);
if (isset($_SESSION))
{
	$_SESSION['counter']++;
	$_SESSION['lasttime'] = time();
}
else
{
	$_SESSION = array
	(
		'counter' => 1, 
		'staticvar' => 'mooh', 
		'lasttime' => time()
	);
}

?>