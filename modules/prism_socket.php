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
 * PHPInSimMod - Socket Module
 * @package PRISM
 * @subpackage Socket
 * @author morpha (Constantin Köpplinger) <morpha@xigmo.net>
 * @author Dygear (Mark Tomlin) <Dygear@gmail.com>
*/


class socket
{
	public function isConnected()
	{
		return (isset($this->connected)) ? $this->connected : NULL;
	}

	public function sendPacket(&$packet)
	{
		return $this->send($packet->pack());
	}

	public function &sock()
	{
		return $this->socket;
	}
}

?>