<?php
/**
 * PHPInSimMod - Connections Module
 * @package PRISM
 * @subpackage Connections
*/

define('CONNTYPE_HOST',			0);			# object is connected directly to a host
define('CONNTYPE_RELAY',		1);			# object is connected to host via relay

define('KEEPALIVE_TIME',		29);		# the time in seconds of write inactivity, after which we'll send a ping
define('HOST_TIMEOUT', 			90);		# the time in seconds of silence after we will disconnect from a host
define('HOST_RECONN_TIMEOUT',	3);
define('HOST_RECONN_TRIES',		5);

define('CONN_TIMEOUT',			10);		# host long may a connection attempt last

define('CONN_NOTCONNECTED',		0);			# not connected to the host
define('CONN_CONNECTING',		1);			# in the process of connecting to the host
define('CONN_CONNECTED',		2);			# connected to a host
define('CONN_VERIFIED',			3);			# it has been verified that we have a working insim connection

define('SOCKTYPE_BEST',			0);
define('SOCKTYPE_TCP',			1);
define('SOCKTYPE_UDP',			2);

define('STREAM_READ_BYTES',		1024);

class InsimConnection
{
	private $connType;
	public $socketType;
	
	public $socket;
	public $socketMCI;						# secundary, udp socket to listen on, if udpPort > 0
											# note that this follows the exact theory of how insim deals with tcp and udp sockets
											# see InSim.txt in LFS distributions for more info
	
	public $connStatus		= CONN_NOTCONNECTED;
	public $sockErrNo		= 0;
	public $sockErrStr		= '';
	
	// Counters and timers
	public $mustConnect		= 0;
	public $connTries		= 0;
	public $connTime		= 0;
	public $lastReadTime	= 0;
	public $lastWriteTime	= 0;
	
	// TCP stream buffer
	private $streamBuf		= '';
	private $streamBufLen	= 0;
	
	// send queue used in emergency cases (if host appears lagged or overflown with packets)
	public $sendQ			= array();
	public $sendQStatus		= 0;
	public $sendQTime		= 0;
	
	// connection & host info
	public $id				= '';			# the section id from the ini file
	public $ip				= '';			# ip or hostname to connect to
	public $connectIp		= '';			# the actual ip used to connect
	public $port			= 0;			# the port
	public $udpPort			= 0;			# the secundary udp port to listen on for NLP/MCI packets, in case the main port is tcp
	public $hostName		= '';			# the hostname. Can be populated by user in case of relay.
	public $adminPass		= '';			# adminpass for both relay and direct usage
	public $specPass		= '';			# specpass for relay usage
	public $pps				= 3;		
	
	public function __construct($connType = CONNTYPE_HOST, $socketType = SOCKTYPE_TCP)
	{
		$this->connType		= ($connType == CONNTYPE_RELAY) ? $connType : CONNTYPE_HOST;
		$this->socketType	= ($socketType == SOCKTYPE_UDP) ? $socketType : SOCKTYPE_TCP;
	}
	
	public function __destruct()
	{
		$this->close();
		if ($this->socketMCI)
			fclose($this->socketMCI);
	}
	
	public function connect()
	{
		// If we're already connected, then we'll assume this is a forced reconnect, so we'll close
		$this->close(FALSE, TRUE);
		
		// Figure out the proper IP address. We do this every time we connect in case of dynamic IP addresses.
		$this->connectIp = $this->getIP();
		if (!$this->connectIp)
		{
			console('Cannot connect to host, Invalid IP : '.$this->ip.':'.$this->port.' : '.$this->sockErrStr);
			$this->socket		= NULL;
			$this->connStatus	= CONN_NOTCONNECTED;
			$this->mustConnect	= -1;					// Something completely failed - we will no longer try this connection
			return FALSE;
		}
		
		if ($this->socketType == SOCKTYPE_UDP)
			$this->connectUDP();
		else
			$this->connectTCP();
	}
	
	public function connectUDP()
	{
		// Create UDP socket
		$this->socket = @stream_socket_client('udp://'.$this->connectIp.':'.$this->port, $this->sockErrNo, $this->sockErrStr);
		if ($this->socket === FALSE || $this->sockErrNo)
		{
			console ('Error opening UDP socket for '.$this->connectIp.':'.$this->port.' : '.$this->sockErrStr);
			$this->socket		= NULL;
			$this->connStatus	= CONN_NOTCONNECTED;
			$this->mustConnect	= -1;					// Something completely failed - we will no longer try this connection
			return FALSE;
		}
		
		// We set the connection time here, so we can track how long we're trying to connect
		$this->connTime = time();
	
		console('Connecting to '.$this->ip.':'.$this->port.' ... #'.($this->connTries + 1));
		$this->connectFinish();
		$this->lastReadTime = time() - HOST_TIMEOUT + 10;
		
		return TRUE;		
	}
	
	public function connectTCP()
	{
		// If we're already connected, then we'll assume this is a forced reconnect, so we'll close
		$this->close(FALSE, TRUE);
	
		// Here we create the socket and initiate the connection. This is done asynchronously.
		$this->socket = @stream_socket_client('tcp://'.$this->connectIp.':'.$this->port, $this->sockErrNo, $this->sockErrStr, CONN_TIMEOUT, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
		if ($this->socket === FALSE || $this->sockErrNo)
		{
			console ('Error opening TCP socket for '.$this->connectIp.':'.$this->port.' : '.$this->sockErrStr);
			$this->socket		= NULL;
			$this->connStatus	= CONN_NOTCONNECTED;
			$this->mustConnect	= -1;					// Something completely failed - we will no longer try this connection
			return FALSE;
		}
		
		// Set socket status to 'SYN sent'
		$this->connStatus = CONN_CONNECTING;
		// We set the connection time here, so we can track how long we're trying to connect
		$this->connTime = time();
		
		stream_set_blocking($this->socket, 0);
		
		console('Connecting to '.$this->ip.':'.$this->port.' ... #'.($this->connTries + 1));
		
		return TRUE;		
	}
	
	public function connectFinish()
	{
		// Here we finalise the connection cycle. Send an init packet to start the insim stream and while at it, detect if the socket is real.
		$result				= FALSE;
		$this->connStatus	= CONN_CONNECTED;
		
		if ($this->connType == CONNTYPE_HOST)
		{
			// Send IS_ISI packet
			$ISP			= new IS_ISI();
			$ISP->ReqI		= TRUE;
			$ISP->UDPPort	= ($this->udpPort > 0) ? $this->udpPort : 0;
			$ISP->Flags		= ISF_MCI;
			$ISP->Prefix	= '!';
			$ISP->Interval	= round(1000 / $this->pps);
			$ISP->Admin		= $this->adminPass;
			$ISP->IName		= 'PRISM v' . PHPInSimMod::VERSION;
			if ($this->writePacket($ISP) > 0)
				$result		= TRUE;
		}
		else if ($this->connType == CONNTYPE_RELAY)
		{
			// Send IR_SEL packet
			$ISP			= new IR_SEL();
			$ISP->ReqI		= TRUE;
			$ISP->HName		= $this->hostName;
			$ISP->Admin		= $this->adminPass;
			$ISP->Spec		= $this->specPass;
	
			if ($this->writePacket($ISP) > 0)
				$result		= TRUE;
		}
		else
		{
			// I'm not sure what we connected to. Shouldn't be possible. Permanently close.
			$this->close(TRUE);
		}
		
		// Check the final outcome of the finalise
		if (!$result)
		{
			// AHA! the connection failed
			console ('Could not connect to '.$this->ip.':'.$this->port);
			$this->connStatus	= CONN_CONNECTING;
			$this->close();
		}
		else
		{
			console('Connected to '.$this->ip.':'.$this->port);
		}
	}
	
	public function createMCISocket()
	{
		$this->socketMCI = @stream_socket_server('udp://0.0.0.0:'.$this->udpPort, $errNo, $errStr, STREAM_SERVER_BIND);
		if (!$this->socketMCI || $errNo > 0)
		{
			console ('Error opening additional UDP socket to listen on : '.$this->sockErrStr);
			$this->socketMCI	= NULL;
			$this->udpPort		= 0;
			return FALSE;
		}
		
		console('Listening for NLP/MCI on secundary UDP port '.$this->udpPort);
		
		return TRUE;
	}
	
	// $permanentClose	- set to TRUE to close this connection once and for all.
	// $quick			- set to TRUE to bypass the reconnection mechanism. If TRUE this disconnect would not count towards the reconnection counter.
	//
	public function close($permanentClose = FALSE, $quick = FALSE)
	{
		if (is_resource($this->socket))
		{
			if ($this->connStatus == CONN_VERIFIED && $this->connType == CONNTYPE_HOST)
			{
				// Send goodbye packet to host
				$ISP		= new IS_TINY();
				$ISP->SubT	= TINY_CLOSE;
				$this->writePacket($ISP);
			}
	
			fclose($this->socket);
			console('Closed connection to '.$this->ip.':'.$this->port);
		}
		
		// (re)set some variables.
		$this->socket			= NULL;
		$this->connStatus		= CONN_NOTCONNECTED;
		$this->sendQ			= array();
		$this->sendQStatus		= 0;
		$this->sendQTime		= 0;
		$this->lastReadTime		= 0;
		$this->lastWriteTime	= 0;
		
		if ($quick)
			return;
		
		if (!$permanentClose)
		{
			if (++$this->connTries < HOST_RECONN_TRIES)
				$this->mustConnect = time() + HOST_RECONN_TIMEOUT;
			else
			{
				console('Cannot seem to connect to '.$this->ip.':'.$this->port.' - giving up ...');
				$this->mustConnect = -1;
			}
		}
		else
			$this->mustConnect = -1;
	}
	
	public function writePacket(&$packet)
	{
		if ($this->socketType	== SOCKTYPE_UDP)
			return $this->writeUDP($packet->pack());
		else
			return $this->writeTCP($packet->pack());
	}
	
	public function writeUDP(&$data)
	{
		$this->lastWriteTime = time();
		return @fwrite ($this->socket, $data);
	}
	
	public function writeTCP(&$data, $sendQPacket = FALSE)
	{
		$bytes = 0;
		
		if ($this->connStatus < CONN_CONNECTED)
			return $bytes;
	
		if ($sendQPacket == TRUE)
		{
			// This packet came from the sendQ. We just try to send this and don't bother too much about error checking.
			// That's done from the sendQ flushing code.
			$bytes = @fwrite ($this->socket, $data);
		}
		else
		{
			if ($this->sendQStatus == 0)
			{
				// It's Ok to send packet
				$bytes = @fwrite ($this->socket, $data);
				$this->lastWriteTime = time();
		
				if (!$bytes || $bytes != strlen($data))
				{
					console('Writing '.strlen($data).' bytes to socket '.$this->ip.':'.$this->port.' failed (wrote '.$bytes.' bytes). Error : '.(($this->connStatus == CONN_CONNECTING) ? 'Socket connection not completed.' : $this->sockErrStr).' (connStatus : '.$this->connStatus.')');
					$this->addPacketToSendQ (substr($data, $bytes));
				}
			}
			else
			{
				// Host is lagged
				$this->addPacketToSendQ ($data);
			}
		}
	
		return $bytes;
	}
	
	public function addPacketToSendQ($data)
	{
		if ($this->sendQStatus == 0)
			$this->sendQTime	= time();
		$this->sendQ[]			= $data;
		$this->sendQStatus++;
	}
	
	public function read(&$peerInfo)
	{
		$this->lastReadTime = time();
		return stream_socket_recvfrom($this->socket, STREAM_READ_BYTES, 0, $peerInfo);
	}
	
	public function readMCI(&$peerInfo)
	{
		$this->lastReadTime = time();
		return stream_socket_recvfrom($this->socketMCI, STREAM_READ_BYTES, 0, $peerInfo);
	}
	
	public function appendToBuffer(&$data)
	{
		$this->streamBuf	.= $data;
		$this->streamBufLen	= strlen ($this->streamBuf);
	}
	
	public function clearBuffer()
	{
		$this->streamBuf	= '';
		$this->streamBufLen	= 0;
	}
	
	public function findNextPacket()
	{
		if ($this->streamBufLen == 0)
			return FALSE;
		
		$sizebyte = ord($this->streamBuf[0]);
		if ($sizebyte == 0)
		{
			return FALSE;
		}
		else if ($this->streamBufLen < $sizebyte)
		{
			//console('Split packet ...');
			return FALSE;
		}
		
		// We should have a whole packet in the buffer now
		$packet					= substr($this->streamBuf, 0, $sizebyte);
		$packetType				= ord($packet[1]);
	
		// Cleanup streamBuffer
		$this->streamBuf		= substr($this->streamBuf, $sizebyte);
		$this->streamBufLen		= strlen($this->streamBuf);
		
		return $packet;
	}
	
	private function getIP()
	{
		if ($this->verifyIP($this->ip))
			return $this->ip;
		else
		{
			$tmp_ip = @gethostbyname($this->ip);
			if ($this->verifyIP($tmp_ip))
				return $tmp_ip;
		}
		
		return FALSE;
	}
	
	private function verifyIP($ip)
	{
		return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
	}
}

?>