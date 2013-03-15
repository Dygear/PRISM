<?php
# Almost PSR
namespace PRISM\Module;

class InsimConnection
{
    private $connType;
	private $socketType;
	
	private $socket;
	private $socketMCI;						# secondary, udp socket to listen on, if udpPort > 0
											# note that this follows the exact theory of how insim deals with tcp and udp sockets
											# see InSim.txt in LFS distributions for more info
	private $socketOutgauge;				# separate udp socket to listen on for outgauge packets, if outgaugePort > 0
	
	private $connStatus		= CONN_NOTCONNECTED;
	
	// Counters and timers
	private $mustConnect	= 0;
	private $connTries		= 0;
	private $connTime		= 0;
	private $lastReadTime	= 0;
	private $lastWriteTime	= 0;
	
	// TCP stream buffer
	private $streamBuf		= '';
	private $streamBufLen	= 0;
	
	// send queue used in emergency cases (if host appears lagged or overflown with packets)
	private $sendQ			= '';
	private $sendQLen		= 0;
	private $sendWindow		= STREAM_WRITE_BYTES;	// dynamic window size

	// connection & host info
	private $id				= '';			# the section id from the ini file
	private $ip				= '';			# ip or hostname to connect to
	private $connectIP		= '';			# the actual ip used to connect
	private $port			= 0;			# the port
	private $flags			= 72;			# Defaults to ISF_MSO_COLS (8) & ISF_CON (64) options on.
	private $udpPort		= 0;			# the secundary udp port to listen on for NLP/MCI packets, in case the main port is tcp
	private $outgaugePort	= 0;			# the outgauge udp port to listen on
	private $adminPass		= '';			# adminpass for both relay and direct usage
	private $specPass		= '';			# specpass for relay usage
	private $pps			= 3;		
	private $hostName		= '';			# the hostname. Can be populated by user in case of relay.

	public function __construct(array &$icVars)
	{
    global $PRISM;
    
		$this->connType		= ($icVars['connType'] == CONNTYPE_RELAY) ? CONNTYPE_RELAY : CONNTYPE_HOST;
		$this->socketType	= ($icVars['socketType'] == SOCKTYPE_UDP) ? SOCKTYPE_UDP : SOCKTYPE_TCP;
		$this->id			= $icVars['id'];
		$this->ip			= $icVars['ip'];
		$this->port			= $icVars['port'];
		$this->flags		= $icVars['flags'];
		$this->pps			= $icVars['pps'];
		$this->adminPass	= $icVars['adminPass'];
		$this->prefix		= ($icVars['prefix'] == '') ? $PRISM->config->cvars['prefix'] : $icVars['prefix'];

		$this->udpPort		= isset($icVars['udpPort']) ? $icVars['udpPort'] : 0;
		$this->outgaugePort	= isset($icVars['outgaugePort']) ? $icVars['outgaugePort'] : 0;
		$this->hostName		= isset($icVars['hostName']) ? $icVars['hostName'] : '';
		$this->specPass		= isset($icVars['specPass']) ? $icVars['specPass'] : '';
	}
	
	public function __destruct()
	{
		$this->close(TRUE);
        
		if ($this->socketMCI) {
			fclose($this->socketMCI);
		}
        
		if ($this->socketOutgauge) {
			fclose($this->socketOutgauge);
		}
	}
	
	public function &getSocket()
	{
		return $this->socket;
	}
	
	public function &getSocketMCI()
	{
		return $this->socketMCI;
	}
	
	public function &getSocketOutgauge()
	{
		return $this->socketOutgauge;
	}
	
	public function &getSocketType()
	{
		return $this->socketType;
	}
	
	public function &getConnStatus()
	{
		return $this->connStatus;
	}
	
	public function setConnStatus($connStatus)
	{
		$this->connStatus = $connStatus;
	}
	
	public function &getMustConnect()
	{
		return $this->mustConnect;
	}
	
	public function setConnTries($connTries)
	{
		$this->connTries = $connTries;
	}
	
	public function setConnTime($connTime)
	{
		$this->connTime = $connTime;
	}

	public function &getConnTime()
	{
		return $this->connTime;
	}
	
	public function &getLastReadTime()
	{
		return $this->lastReadTime;
	}
	
	public function &getLastWriteTime()
	{
		return $this->lastWriteTime;
	}
	
	public function &getConnectIP()
	{
		return $this->connectIP;
	}
	
	public function &getIP()
	{
		return $this->ip;
	}
	
	public function &getPort()
	{
		return $this->port;
	}
	
	public function &getUdpPort()
	{
		return $this->udpPort;
	}
	
	public function &getOutgaugePort()
	{
		return $this->outgaugePort;
	}
	
	public function &getFlags()
	{
		return $this->flags;
	}

	public function &getPPS()
	{
		return $this->pps;
	}

	public function isAdmin()
	{
		return ($this->adminPass != '') ? TRUE : FALSE;
	}
	
	public function isRelay()
	{
		return ($this->connType == CONNTYPE_RELAY) ? TRUE : FALSE;
	}
	
	public function &getHostname()
	{
		return $this->hostName;
	}
	
	public function setUdpPort($udpPort)
	{
		// Set the new value
		$this->udpPort = $udpPort;

		// Should we reinit the udp listening socket?
		$this->closeMCISocket();
		$this->createMCISocket();
	}
	
	public function setOutgaugePort($outgaugePort)
	{
		// Set the new value
		$this->outgaugePort = $outgaugePort;

		// Should we reinit the udp listening socket?
		$this->closeOutgaugeSocket();
		$this->createOutgaugeSocket();
	}
		
	public function &getSendQLen()
	{
		return $this->sendQLen;
	}
	
	public function connect()
	{
		// If we're already connected, then we'll assume this is a forced reconnect, so we'll close
		$this->close(FALSE, TRUE);
		
		// Figure out the proper IP address. We do this every time we connect in case of dynamic IP addresses.
		$this->connectIP = getIP($this->ip);
        
		if (!$this->connectIP) {
			console('Cannot connect to host, Invalid IP : '.$this->ip.':'.$this->port);
			$this->socket		= NULL;
			$this->connStatus	= CONN_NOTCONNECTED;
			$this->mustConnect	= -1;					// Something completely failed - we will no longer try this connection
			return false;
		}
		
		if ($this->socketType == SOCKTYPE_UDP) {
			$this->connectUDP();
		} else {
			$this->connectTCP();
		}
		
		return true;
	}
	
	public function connectUDP()
	{
		// Create UDP socket
		$this->socket = @stream_socket_client('udp://'.$this->connectIP.':'.$this->port, $sockErrNo, $sockErrStr);
        
		if ($this->socket === FALSE || $sockErrNo) {
			console ('Error opening UDP socket for '.$this->connectIP.':'.$this->port.' : '.$sockErrStr);
			$this->socket		= NULL;
			$this->connStatus	= CONN_NOTCONNECTED;
			$this->mustConnect	= -1;					// Something completely failed - we will no longer try this connection
			return false;
		}
		
		// We set the connection time here, so we can track how long we're trying to connect
		$this->connTime = time();
	
		console('Connecting to '.$this->ip.':'.$this->port.' ... #'.($this->connTries + 1));
		$this->connectFinish();
		$this->lastReadTime = time() - HOST_TIMEOUT + 10;
		
		return true;		
	}
	
	public function connectTCP()
	{
		// If we're already connected, then we'll assume this is a forced reconnect, so we'll close
		$this->close(false, true);
	
		// Here we create the socket and initiate the connection. This is done asynchronously.
		$this->socket = @stream_socket_client('tcp://'.$this->connectIP.':'.$this->port, 
												$sockErrNo, 
												$sockErrStr, 
												CONN_TIMEOUT, 
												STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
                                                
		if ($this->socket === FALSE || $sockErrNo) {
			console ('Error opening TCP socket for '.$this->connectIP.':'.$this->port.' : '.$sockErrStr);
			$this->socket		= NULL;
			$this->connStatus	= CONN_NOTCONNECTED;
			$this->mustConnect	= -1;					// Something completely failed - we will no longer try this connection
			return false;
		}
		
		// Set socket status to 'SYN sent'
		$this->connStatus = CONN_CONNECTING;
		// We set the connection time here, so we can track how long we're trying to connect
		$this->connTime = time();
		
		stream_set_blocking($this->socket, 0);
		
		console('Connecting to '.$this->ip.':'.$this->port.' ... #'.($this->connTries + 1));
		
		return true;		
	}
	
	public function connectFinish()
	{
		// Here we finalise the connection cycle. Send an init packet to start the insim stream and while at it, detect if the socket is real.
		$this->connStatus	= CONN_CONNECTED;
		
		if ($this->connType == CONNTYPE_HOST) {
			// Send IS_ISI packet
			$ISP			= new IS_ISI();
			$ISP->ReqI		= true;
			$ISP->UDPPort	= (isset($this->udpPort) && $this->udpPort > 0) ? $this->udpPort : 0;
			$ISP->Flags		= (isset($this->flags)) ? $this->flags : ISF_MSO_COLS & ISF_CON;
			$ISP->Prefix	= (isset($this->prefix)) ? ord($this->prefix) : ord('!');
			$ISP->Interval	= round(1000 / $this->pps);
			$ISP->Admin		= $this->adminPass;
			$ISP->IName		= 'PRISM v' . PHPInSimMod::VERSION;
			$this->writePacket($ISP);
		} else if ($this->connType == CONNTYPE_RELAY) {
			// Send IR_SEL packet
			$SEL			= new IR_SEL();
			$SEL->ReqI		= true;
			$SEL->HName		= $this->hostName;
			$SEL->Admin		= $this->adminPass;
			$SEL->Spec		= $this->specPass;
			$this->writePacket($SEL);
		} else {
			// I'm not sure what we connected to. Shouldn't be possible. Permanently close.
			$this->close(true);
		}
		
		console('Connected to '.$this->ip.':'.$this->port);
	}
	
	public function createMCISocket()
	{
		$this->closeMCISocket();
        
	    if ($this->udpPort == 0) {
	        return true;
	    }
		
		$this->socketMCI = @stream_socket_server('udp://0.0.0.0:'.$this->udpPort, $errNo, $errStr, STREAM_SERVER_BIND);
        
		if (!$this->socketMCI || $errNo > 0) {
			console ('Error opening additional UDP socket to listen on : '.$errStr);
			$this->socketMCI	= NULL;
			$this->udpPort		= 0;
			return true;
		}
		
		console('Listening for NLP/MCI on secundary UDP port '.$this->udpPort);
		
		return true;
	}
	
	private function closeMCISocket()
	{
		if (is_resource($this->socketMCI)) {
			fclose($this->socketMCI);
		}
        
		$this->socketMCI = null;
	}
	
	public function createOutgaugeSocket()
	{
	    $this->closeOutgaugeSocket();
	    if ($this->outgaugePort == 0) {
	        return true;
	    }
		
		$this->socketOutgauge = @stream_socket_server('udp://0.0.0.0:'.$this->outgaugePort, $errNo, $errStr, STREAM_SERVER_BIND);
        
		if (!$this->socketOutgauge || $errNo > 0) {
			console ('Error opening OutGauge UDP socket to listen on : '.$errStr);
			$this->socketOutgauge	= NULL;
			$this->outgaugePort		= 0;
			return false;
		}
		
		console('Listening for OutGauge packets on UDP port '.$this->outgaugePort);
		
		return true;
	}
	
	private function closeOutgaugeSocket()
	{
		if (is_resource($this->socketOutgauge)) {
			fclose($this->socketOutgauge);
		}
        
		$this->socketOutgauge = NULL;
	}
	
	// $permanentClose	- set to TRUE to close this connection once and for all.
	// $quick			- set to TRUE to bypass the reconnection mechanism. If TRUE this disconnect would not count towards the reconnection counter.
	//
	public function close($permanentClose = FALSE, $quick = FALSE)
	{
		if (is_resource($this->socket)) {
			if ($this->connStatus == CONN_VERIFIED && $this->connType == CONNTYPE_HOST) {
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
		$this->lastReadTime		= 0;
		$this->lastWriteTime	= 0;
		$this->clearBuffer();
		$this->sendQReset();
		
		if ($quick) {
			return;
		}
		
		if (!$permanentClose) {
			if (++$this->connTries < HOST_RECONN_TRIES) {
				$this->mustConnect = time() + HOST_RECONN_TIMEOUT;
			} else {
				console('Cannot seem to connect to '.$this->ip.':'.$this->port.' - giving up ...');
				$this->mustConnect = -1;
			}
		} else {
			$this->mustConnect = -1;
		}
	}
	
	public function writePacket(Struct &$packet)
	{
		if ($this->socketType	== SOCKTYPE_UDP) {
			return $this->writeUDP($packet->pack());
		} else {
			return $this->writeTCP($packet->pack());
		}
	}
	
	public function writeUDP($data)
	{
		$this->lastWriteTime = time();
        
		if (($bytes = @fwrite($this->socket, $data)) === FALSE) {
			console('UDP: Error sending packet through socket.');
		}
        
		return $bytes;
	}
	
	public function writeTCP($data, $sendQPacket = false)
	{
		$bytes = 0;
		
		if ($this->connStatus < CONN_CONNECTED) {
			return $bytes;
		}
	
		if ($sendQPacket == true) {
			// This packet came from the sendQ. We just try to send this and don't bother too much about error checking.
			// That's done from the sendQ flushing code.
			if (($bytes = @fwrite($this->socket, $data)) === FALSE) {
				console('TCP: Error sending packet through socket.');
			}
		} else {
			if ($this->sendQLen == 0) {
				// It's Ok to send packet
				if (($bytes = @fwrite($this->socket, $data)) === FALSE) {
					console('TCP: Error sending packet through socket.');
				}
                
				$this->lastWriteTime = time();
		
				if (!$bytes || $bytes != strlen($data)) {
					$this->addPacketToSendQ (substr($data, $bytes));
				}
			} else {
				// Host is lagged
				$this->addPacketToSendQ ($data);
			}
		}
	
		return $bytes;
	}
	
	private function addPacketToSendQ($data)
	{
		$this->sendQ .= $data;
		$this->sendQLen += strlen($data);
	}

	public function flushSendQ()
	{
		// Send chunk of data
		$bytes = $this->writeTCP(substr($this->sendQ, 0, $this->sendWindow), true);
		
		// Dynamic window sizing
		if ($bytes == $this->sendWindow) {
			$this->sendWindow += STREAM_WRITE_BYTES;
		} else {
			$this->sendWindow -= STREAM_WRITE_BYTES;
            
			if ($this->sendWindow < STREAM_WRITE_BYTES) {
				$this->sendWindow = STREAM_WRITE_BYTES;
			}
		}

		// Update the sendQ
		$this->sendQ = substr($this->sendQ, $bytes);
		$this->sendQLen -= $bytes;

		// Cleanup / reset timers
		if ($this->sendQLen == 0) {
			// All done flushing - reset queue variables
			$this->sendQReset();
		}

		if ($bytes > 0) {
			$this->lastWriteTime	= time();
		}
		//console('Bytes sent : '.$bytes.' - Bytes left : '.$this->sendQLen.' - '.$this->ip);
	}
	
	private function sendQReset()
	{
		$this->sendQ			= '';
		$this->sendQLen			= 0;
		$this->lastActivity		= time();
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
	
	public function readOutgauge(&$peerInfo)
	{
		$this->lastReadTime = time();
		return stream_socket_recvfrom($this->socketOutgauge, STREAM_READ_BYTES, 0, $peerInfo);
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
			return false;
		
		$sizebyte = ord($this->streamBuf[0]);
		if ($sizebyte == 0)
		{
			return false;
		}
		else if ($this->streamBufLen < $sizebyte)
		{
			//console('Split packet ...');
			return false;
		}
		
		// We should have a whole packet in the buffer now
		$packet					= substr($this->streamBuf, 0, $sizebyte);
		$packetType				= ord($packet[1]);
	
		// Cleanup streamBuffer
		$this->streamBuf		= substr($this->streamBuf, $sizebyte);
		$this->streamBufLen		= strlen($this->streamBuf);
		
		return $packet;
	}
}
