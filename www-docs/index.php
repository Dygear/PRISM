<?php
if (isset($SERVER['PHP_AUTH_USER']))
{
	if (!isset($_SESSION))
	{
		$_SESSION = array
		(
			'user' => $SERVER['PHP_AUTH_USER'],
			'autoLogin' => true,
			'counter' => 0,
		);
	}
	else
	{
		$_SESSION['user'] = $SERVER['PHP_AUTH_USER'];
		$_SESSION['autoLogin'] = true;
	}
}
else
{
	if (isset($_SESSION['autoLogin']))
		unset($_SESSION['autoLogin']);
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en">
	<head>
		<title>PRISM :: Server Status</title>
		<link rel="stylesheet" href="/style/default.css" type="text/css" media="screen" />
	</head>
	<body>
		<div class="loginArea">
<?php	if (isset($_SESSION['user'])):	?>
			Welcome <?php echo htmlspecialchars($_SESSION['user']); ?>.
<?php		if (!isset($_SESSION['autoLogin']) || $_SESSION['autoLogin'] == false):	?>
				-<a href="/login.php?logout">Logout</a>
<?php		endIf;	?>
<?php	else:	?>
			<form method="post" action="/login.php">
				<input type="text" name="loginUser" value="" size="16" maxlength="24" />
				<input type="password" name="loginPass" value="" size="16" maxlength="24" />
				<input type="submit" value="Login" />
			</form>
<?php	endIf;	?>
		</div>
		<table>
			<tbody>
				<tr>
					<th width="16%">Server Name</th>
					<th width="10%">Type</th>
					<th width="10%">Uptime</th>
					<th width="8%"><abbr title="Packets Per Second">PPS</abbr> <abbr title="Transmitted">TX</abbr></th>
					<th width="8%"><abbr title="Packets Per Second">PPS</abbr> <abbr title="Recived">RX</abbr></th>
					<th width="8%">Clients</th>
					<th width="8%">Players</th>
					<th width="8%">Slots</th>
					<th width="24%">Clients (Players) / Slots</th>
				</tr>
<?php	forEach ($PRISM->hosts->getHostsInfo() as $info):	?>
				<tr>
					<td><?php echo $info['id']; ?></td>
					<td><?php echo ($info['useRelay']) ? 'Relay' : 'Direct'; ?></td>
					<td>1:01:24:25</td>
					<td>2</td>
					<td>24</td>
					<td><?php echo count($PRISM->hosts->state[$info['id']]->NumConns); ?></td>
					<td><?php echo count($PRISM->hosts->state[$info['id']]->NumP); ?></td>
					<td>64</td>
					<td>
						<div class="meter">
							<div class="bar" style="width: 6.25%;"></div>
							<div class="text">4 (1) / 64</div>
						</div>
					</td>
				</tr>
<?php	endForEach;	?>
		</table>
	</body>
</html>
<?php
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