<?php
/*
	Copyright 2010 Constantin Köpplinger

	Licensed under the Apache License, Version 2.0 (the "License");
	you may not use this file except in compliance with the License.
	You may obtain a copy of the License at

		http://www.apache.org/licenses/LICENSE-2.0

	Unless required by applicable law or agreed to in writing, software
	distributed under the License is distributed on an "AS IS" BASIS,
	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	See the License for the specific language governing permissions and
	limitations under the License.
*/

/**
 * PHPInSimMod - tcpSocket Module
 * @package PRISM
 * @subpackage tcpSocket
 * @author morpha (Constantin Köpplinger) <morpha@xigmo.net>
 * @author Dygear (Mark Tomlin) <Dygear@gmail.com>
*/
class socketTCP extends socket
{
	public $socket;
	protected $connected = FALSE;
	private $remoteHost = NULL;
	private $remotePort = NULL;

	public function __construct($remoteHost, $remotePort)
	{
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		$this->remoteHost = $remoteHost;
		$this->remotePort = $remotePort;

		# If there is an error, I will do something about it, so we supress the warning.
		$socketStatus = @socket_connect($this->socket, $this->remoteHost, $this->remotePort);

		if ($socketStatus === TRUE)
		{
			echo "[TCP_SOCKET] Connected to {$this->remoteHost}:{$this->remotePort}." . PHP_EOL;
			$this->connected = TRUE;
			socket_set_nonblock($this->socket);
		}
		else
		{
			echo "[TCP_SOCKET] Could not connect to {$this->remoteHost}:{$this->remotePort} - Error Code: "
			. socket_last_error($this->socket) . PHP_EOL . socket_strerror(socket_last_error($this->socket));
			$this->connected = FALSE;
		}

		return $this->connected;
	}

	public function send($bstr)
	{
		if(!$this->connected)
			return FALSE;
		if(!is_string($bstr))
		{
			trigger_error('[TCP_SOCKET->send] given parameter is not a string.', E_USER_WARNING);
			return FALSE;
		}
		return socket_write($this->socket, $bstr, strlen($bstr));
	}

	public function recv()
	{
		if(!$this->connected)
			return FALSE;
		return socket_read($this->socket, 512, PHP_BINARY_READ);
	}

}

?>