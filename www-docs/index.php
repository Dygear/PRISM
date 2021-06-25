<?php	include_once('./session.php'); ?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
	<head>
		<title>PRISM :: Server Status</title>
		<link rel="stylesheet" href="resources/style/default.css" type="text/css" media="screen" />
		<link rel="shortcut icon" href="resources/images/logo/logo-1-black.png" />
		<link rel="shortcut icon" href="/favicon.png"> 
	</head>
	<body>
		<div class="loginArea">
<?php	if (isset($_SESSION['user'])):	?>
			Welcome <?php echo htmlspecialchars($_SESSION['user']); ?>.
<?php		if (!isset($_SESSION['autoLogin']) || $_SESSION['autoLogin'] == FALSE):	?>
				-<a href="/login.php?logout">Logout</a>
<?php		endIf;
		else:	?>
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
					<th width="24%">Host Alias</th>
					<th width="10%">Type</th>
					<th width="10%">IP:Port (UDP Port)</th>
					<th width="16%"><abbr title="Packets Per Second">PPS</abbr> <abbr title="Transmitted">TX</abbr></th>
					<th width="16%"><abbr title="Packets Per Second">PPS</abbr> <abbr title="Received">RX</abbr></th>
					<th width="24%">Clients (Players) / Slots</th>
				</tr>
<?php	forEach ($PRISM->hosts->getHostsInfo() as $info):	?>
				<tr>
					<td><?php echo $info['id']; ?></td>
					<td><?php echo ($info['useRelay']) ? 'Relay' : 'Direct'; ?></td>
					<td><?php echo "{$info['ip']}:{$info['port']} ({$info['udpPort']})"; ?></td>
					<td>2</td>
					<td>24</td>
<?php		if ($info['connStatus'] == CONN_VERIFIED):	?>
					<td>
						<div class="meter">
<!--						<div class="bar admins" style="width: 6.25%; float:left;"></div> -->
							<div class="bar players" style="width: <?php echo ($PRISM->hosts->state[$info['id']]->NumP / 47) * 100; ?>%; float:left;"></div>
							<div class="bar clients" style="width: <?php echo ($PRISM->hosts->state[$info['id']]->NumConns / 47) * 100; ?>%; float:left;"></div>
							<div class="text"><?php echo "{$PRISM->hosts->state[$info['id']]->NumConns} ({$PRISM->hosts->state[$info['id']]->NumP}) / 47"; ?></div>
						</div>
					</td>
<?php		else:	?>
					<td>NOT CONNECTED</td>
<?php		endIf;	?>
				</tr>
<?php	endForEach;	?>
			</tbody>
		</table>
		<table>
			<tbody>
				<tr>
					<th>Command</th>
					<th>Description</th>
				</tr>
<?php
	forEach ($PRISM->plugins->getPlugins() as $plugin => $details):
		forEach ($details->sayCommands as $command => $detail):
?>
				<tr>
					<td><?php echo $command; ?></td>
					<td><?php echo htmlspecialchars($detail['info'], ENT_QUOTES); ?></td>
				</tr>
<?php
		endForEach;
	endForEach;
?>
			</tbody>
		</table>
		<table>
			<tbody>
				<tr>
					<th>Name</th>
					<th>Version</th>
					<th>Author</th>
					<th>Description</th>
				</tr>
<?php	forEach ($PRISM->plugins->getPlugins() as $plugin => $details):	?>
				<tr>
					<td><?php echo $plugin::NAME; ?></td>
					<td><?php echo $plugin::VERSION; ?></td>
					<td><?php echo $plugin::AUTHOR; ?></td>
					<td><?php echo $plugin::DESCRIPTION; ?></td>
				</tr>
<?php	endForEach;	?>
			</tbody>
		</table>
		<table>
			<tbody>
				<tr>
					<th>IP</th>
					<th>PORT</th>
					<th>LAST ACTIVITY</th>
				</tr>
<?php	forEach ($PRISM->http->getHttpInfo() as $Client):	?>
				<tr>
					<td><?php echo $Client['ip']; ?></td>
					<td><?php echo $Client['port']; ?></td>
					<td><?php echo time() - $Client['lastActivity']; ?></td>
				</tr>
<?php	endForEach;	?>
				<tr>
					<td colspan="3">Counted <?php echo $PRISM->http->getHttpNumClients(); ?> http client(s).</td>
				</tr>
			</tbody>
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