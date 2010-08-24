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
 * UDP Socket Class
 * @author morpha (Constantin Köpplinger) <morpha@xigmo.net>
 * @coauthor Dygear (Mark Tomlin) <Dygear@gmail.com>
*/
class socketUDP
{
	public $socket;
	private $retmoteHost = '127.0.0.1';
	private $remotePort = '63392';
	private $localHost = '0.0.0.0';
	private $localPort = '30000';

	public function __construct($remoteHost, $remotePort, $localHost = NULL, $localPort = NULL)
	{
		$this->remoteHost = $remoteHost;
		$this->remotePort = $remotePort;
		$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if($localHost !== NULL)
			$this->localHost = $localHost;
		if($localPort !== NULL)
			$this->localPort = $localPort;

		if(!@socket_bind($this->socket, $this->localHost, $this->localPort))
			echo "[UDP_SOCKET] Could not bind to {$this->localHost}:{$this->localPort} - Error Code: " . socket_last_error($this->socket) . PHP_EOL . socket_strerror(socket_last_error($this->socket));
		else
			socket_set_nonblock($this->socket);
	}

	public function send($bstr)
	{
		if(!is_string($bstr))
		{
			trigger_error('[UDP_SOCKET->send] given parameter is not a string.', E_USER_WARNING);
			return false;
		}
		return socket_sendto($this->socket, $bstr, strlen($bstr), 0, $this->remoteHost, $this->remotePort);
	}

	public function recv()
	{
		return socket_read($this->socket, 512, PHP_BINARY_READ);
	}
}

?>