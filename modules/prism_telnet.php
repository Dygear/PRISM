<?php

class TelnetHandler extends SectionHandler
{
	private $telnetSock		= null;
	private $clients		= array();
	private $numClients		= 0;
	
	private $telnetVars		= array();
	
	public function __construct()
	{
		$this->iniFile = 'telnet.ini';
	}
	
	public function initialise()
	{
		global $PRISM;
		
		$this->telnetVars = array
		(
			'ip' => '', 
			'port' => 0,
		);

		if ($this->loadIniFile($this->telnetVars, false))
		{
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded '.$this->iniFile);
		}
		else
		{
			# We ask the client to manually input the connection details here.
			require_once(ROOTPATH . '/modules/prism_interactive.php');
			Interactive::queryTelnet($this->telnetVars);
			
			# Then build a telnet.ini file based on these details provided.
			$extraInfo = <<<ININOTES
;
; Telnet listen details (for remote console access).
; 0.0.0.0 (default) will bind the socket to all available network interfaces.
; To limit the bind to one interface only, you can enter its IP address here.
; If you do not want to use the telnet feature, you can comment or remove the 
; lines, or enter "" and 0 for the ip and port.
;

ININOTES;
			if ($this->createIniFile('Telnet Configuration (remote console)', array('telnet' => &$this->telnetVars), $extraInfo))
				console('Generated config/'.$this->iniFile);
		}
		
		// Setup telnet socket to listen on
		if (!$this->setupListenSocket())
			return false;
		
		return true;
	}

	private function setupListenSocket()
	{
		$this->close(false);
		
		if ($this->telnetVars['ip'] != '' && $this->telnetVars['port'] > 0)
		{
			$this->telnetSock = @stream_socket_server('tcp://'.$this->telnetVars['ip'].':'.$this->telnetVars['port'], $errNo, $errStr);
			if (!is_resource($this->telnetSock) || $this->telnetSock === FALSE || $errNo)
			{
				console('Error opening telnet socket : '.$errStr.' ('.$errNo.')');
				return false;
			}
			else
			{
				console('Listening for telnet input on '.$this->telnetVars['ip'].':'.$this->telnetVars['port']);
			}
		}
		return true;
	}

	private function close($all)
	{
		if (is_resource($this->telnetSock))
			fclose($this->telnetSock);
		
		if (!$all)
			return;
		
		for ($k=0; $k<$this->numClients; $k++)
		{
			array_splice($this->clients, $k, 1);
			$k--;
			$this->numClients--;
		}
	}

	public function getSelectableSockets(array &$sockReads, array &$sockWrites)
	{
		// Add http sockets to sockReads
		if (is_resource($this->telnetSock))
			$sockReads[] = $this->telnetSock;

		for ($k=0; $k<$this->numClients; $k++)
		{
			if (is_resource($this->clients[$k]->getSocket()))
			{
				$sockReads[] = $this->clients[$k]->getSocket();
				
				// If write buffer was full, we must check to see when we can write again
				if ($this->clients[$k]->getSendQLen() > 0)
					$sockWrites[] = $this->clients[$k]->getSocket();
			}
		}
	}

	public function checkTraffic(array &$sockReads, array &$sockWrites)
	{
		$activity = 0;

		// telnetSock input (incoming telnet connection)
		if (in_array($this->telnetSock, $sockReads))
		{
			$activity++;
			
			// Accept the new connection
			$peerInfo = '';
			$sock = @stream_socket_accept ($this->telnetSock, NULL, $peerInfo);
			if (is_resource($sock))
			{
				stream_set_blocking ($sock, 0);
				
				// Add new connection to clients array
				$exp = explode(':', $peerInfo);
				$this->clients[] = new TelnetClient($sock, $exp[0], $exp[1]);
				$this->numClients++;
				console('Telnet Client '.$exp[0].':'.$exp[1].' connected.');
			}
			unset ($sock);
		}
		
		// telnet clients input
		for ($k=0; $k<$this->numClients; $k++) {
			// Recover from a full write buffer?
			if ($this->clients[$k]->getSendQLen() > 0 &&
				in_array($this->clients[$k]->getSocket(), $sockWrites))
			{
				$activity++;
				
				// Flush the sendQ (bit by bit, not all at once - that could block the whole app)
				if ($this->clients[$k]->getSendQLen() > 0)
					$this->clients[$k]->flushSendQ();
			}
			
			// Did we receive something from a httpClient?
			if (!in_array($this->clients[$k]->getSocket(), $sockReads))
				continue;

			$activity++;
			
			$data = $this->clients[$k]->read($data);
			
			// Did the client hang up?
			if ($data == '')
			{
				console('Closed telnet client (client initiated) '.$this->clients[$k]->getRemoteIP().':'.$this->clients[$k]->getRemotePort());
				array_splice ($this->clients, $k, 1);
				$k--;
				$this->numClients--;
				continue;
			}

			// Ok we recieved some input from the telnet client.
			$this->clients[$k]->addInputToBuffer($data);
			do
			{
				$line = $this->clients[$k]->getInputLine();
				if ($line === false)
					break;
				console('TELNET INPUT : '.$line);
			} while(true);

//			// Pass the data to the HttpClient so it can handle it.
//			if (!$this->httpClients[$k]->handleInput($data, $errNo))
//			{
//				// Something went wrong - we can hang up now
//				console('Closed httpClient ('.$errNo.' - '.HttpResponse::$responseCodes[$errNo].') '.$this->httpClients[$k]->getRemoteIP().':'.$this->httpClients[$k]->getRemotePort());
//				array_splice ($this->httpClients, $k, 1);
//				$k--;
//				$this->httpNumClients--;
//				continue;
//			}
		}
		
		return $activity;
	}
}

class TelnetClient
{
	private $socket			= null;
	private $ip				= '';
	private $port			= 0;
	
	private $inputBuffer	= '';
	
	// send queue used for backlog, in case we can't send a reply in one go
	private $sendQ			= '';
	private $sendQLen		= 0;

	private $sendWindow		= STREAM_READ_BYTES;	// dynamic window size
	
	private $lastActivity	= 0;
	
	public function __construct(&$sock, &$ip, &$port)
	{
		$this->socket		= $sock;
		$this->ip			= $ip;
		$this->port			= $port;
		
		$this->lastActivity	= time();
	}
	
	public function __destruct()
	{
		if ($this->sendQLen > 0)
			$this->sendQReset();

		if (is_resource($this->socket))
			fclose($this->socket);
	}

	public function &getSocket()
	{
		return $this->socket;
	}
	
	public function &getRemoteIP()
	{
		return $this->ip;
	}
	
	public function &getRemotePort()
	{
		return $this->port;
	}
	
	public function &getLastActivity()
	{
		return $this->lastActivity;
	}
	
	public function read(&$data)
	{
		$this->lastActivity	= time();
		return fread($this->socket, STREAM_READ_BYTES);
	}
	
	public function addInputToBuffer(&$data)
	{
		$this->inputBuffer .= $data;
	}
	
	public function getInputLine()
	{
		if (($pos = strpos($this->inputBuffer, "\n")) === false)
			return false;

		$data = rtrim(substr($this->inputBuffer, 0, $pos));
		$this->inputBuffer = substr($this->inputBuffer, $pos + 1);

		return $data;
	}
	
	public function write($data, $sendQPacket = FALSE)
	{
		$bytes = 0;
		$dataLen = strlen($data);
		if ($dataLen == 0)
			return 0;
		
		if (!is_resource($this->socket))
			return $bytes;
	
		if ($sendQPacket == TRUE)
		{
			// This packet came from the sendQ. We just try to send this and don't bother too much about error checking.
			// That's done from the sendQ flushing code.
			$bytes = @fwrite($this->socket, $data);
		}
		else
		{
			if ($this->sendQLen == 0)
			{
				// It's Ok to send packet
				$bytes = @fwrite($this->socket, $data);
				$this->lastActivity = time();
		
				if (!$bytes || $bytes != $dataLen)
				{
					// Could not send everything in one go - send the remainder to sendQ
					$this->addPacketToSendQ (substr($data, $bytes));
				}
			}
			else
			{
				// Remote is lagged
				$this->addPacketToSendQ($data);
			}
		}
	
		return $bytes;
	}

	public function &getSendQLen()
	{
		return $this->sendQLen;
	}
	
	public function addPacketToSendQ($data)
	{
		$this->sendQ			.= $data;
		$this->sendQLen			+= strlen($data);
	}

	public function flushSendQ()
	{
		// Send chunk of data
		$bytes = $this->write(substr($this->sendQ, 0, $this->sendWindow), TRUE);
		
		// Dynamic window sizing
		if ($bytes == $this->sendWindow)
			$this->sendWindow += STREAM_READ_BYTES;
		else
		{
			$this->sendWindow -= STREAM_READ_BYTES;
			if ($this->sendWindow < STREAM_READ_BYTES)
				$this->sendWindow = STREAM_READ_BYTES;
		}

		// Update the sendQ
		$this->sendQ = substr($this->sendQ, $bytes);
		$this->sendQLen -= $bytes;

		// Cleanup / reset timers
		if ($this->sendQLen == 0)
		{
			// All done flushing - reset queue variables
			$this->sendQReset();
		} 
		else if ($bytes > 0)
		{
			// Set when the last packet was flushed
			$this->lastActivity		= time();
		}
		//console('Bytes sent : '.$bytes.' - Bytes left : '.$this->sendQLen);
	}
	
	public function sendQReset()
	{
		$this->sendQ			= '';
		$this->sendQLen			= 0;
		$this->lastActivity		= time();
	}
}

?>