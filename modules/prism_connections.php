<?

define('CONNTYPE_HOST', 0);			// object is connected directly to a host
define('CONNTYPE_RELAY', 1);		// object is connected to host via relay

define('KEEPALIVE_TIME',		29);		# the time in seconds of write inactivity, after which we'll send a ping
define('HOST_TIMEOUT', 			90);		# the time in seconds of silence after we will disconnect from a host
define('HOST_RECONN_TIMEOUT',	3);
define('HOST_RECONN_TRIES',		5);

define('CONN_TIMEOUT', 10);					# host long may a connection attempt last
define('CONN_NOTCONNECTED', 0);
define('CONN_CONNECTING', 1);
define('CONN_CONNECTED', 2);

define('SOCKTYPE_BEST', 0);
define('SOCKTYPE_TCP', 1);
define('SOCKTYPE_UDP', 2);

define ('STREAM_READ_BYTES', 1024);

class insimConnection {
	private $connType;
	private $socketType;
	
	public $socket;
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
	public $sendQ				= array();
	public $sendQStatus			= 0;
	public $sendQTime			= 0;
	
	// connection & host info
	public $id				= '';			// the section id from the ini file
	public $ip				= '';			// ip or hostname to connect to
	public $port			= 0;			// the port
	public $hostName		= '';			// the hostname. Can be populated by user in case of relay.
	public $adminPass		= '';			// adminpass for both relay and direct usage
	public $specPass		= '';			// specpass for relay usage
	public $pps				= 3;		
	
	public function __construct($connType = CONNTYPE_HOST, $socketType = SOCKTYPE_TCP)
	{
		$this->connType		= ($connType == CONNTYPE_RELAY) ? $connType : CONNTYPE_HOST;
		$this->socketType	= ($socketType == SOCKTYPE_UDP) ? $socketType : SOCKTYPE_TCP;
	}
	
	public function __destruct()
	{
		$this->close();
	}
	
	public function connect()
	{
		// If we're already connected, then we'll assume this is a forced reconnect, so we'll close
		$this->close(FALSE, TRUE);

		// Figure out the proper IP address. We do this every time we connect in case of dynamic IP addresses.
		$ip = $this->getIP();
		if (!$ip) {
	    	console('Cannot connect to host, Invalid IP : '.$this->ip.':'.$this->port.' : '.$this->sockErrStr);
		    $this->socket		= NULL;
			$this->connStatus	= CONN_NOTCONNECTED;
			$this->mustConnect	= -1;					// Something completely failed - we will no longer try this connection
			return FALSE;
		}
		
		// Here we create the socket and initiate the connection. This is done asynchronously.
	    $this->socket = @stream_socket_client('tcp://'.$ip.':'.$this->port, $this->sockErrNo, $this->sockErrStr, CONN_TIMEOUT, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
		if ($this->sockErrNo) {
	    	console ('Error opening socket for '.$ip.':'.$this->port.' : '.$this->sockErrStr);
		    $this->socket		= NULL;
			$this->connStatus	= CONN_NOTCONNECTED;
			$this->mustConnect	= -1;					// Something completely failed - we will no longer try this connection
		    return FALSE;
		}
		
		// Set socket status to 'SYN sent'
		$this->connStatus = CONN_CONNECTING;
		// We set the connection time here, so we can track how long we're trying to connect
		$this->connTime = time();
		
		stream_set_blocking ($this->socket, 0);
		
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
			$ISP->UDPPort	= 0;
			$ISP->Flags		= 0;
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
			$this->connTime = time();
			$this->connTries = 0;
		}
	}
	
	// $permanentClose	- set to TRUE to close this connection once and for all.
	// $quick			- set to TRUE to bypass the reconnection mechanism. If TRUE this disconnect would not count towards the reconnection counter.
	//
	public function close($permanentClose = FALSE, $quick = FALSE)
	{
		if (is_resource($this->socket))
		{
			if ($this->connStatus == CONN_CONNECTED && $this->connType == CONNTYPE_HOST)
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
		return $this->write($packet->pack());
	}
	
	public function write(&$data, $sendQPacket = FALSE)
	{
		$bytes = 0;
		
		if ($this->connStatus != CONN_CONNECTED)
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
		
			    if (!$bytes || $bytes != strlen($data)) {
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
			$this->sendQTime	= time ();
		$this->sendQ[]			= $data;
		$this->sendQStatus++;
	}
	
	public function read()
	{
		$buffer = fread($this->socket, STREAM_READ_BYTES);
		$this->lastReadTime = time ();
		
		return $buffer;
	}
	
	public function appendToBuffer(&$data)
	{
		$this->streamBuf	.= $data;
		$this->streamBufLen	= strlen ($this->streamBuf);
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
			console('Split packet ...');
		    return FALSE;
		}
		
		// We should have a whole packet in the buffer now
		$packet					= substr($this->streamBuf, 0, $sizebyte);
//		$packetType				= strtolower(ISPackets::$types[ord ($packet[1])]);
		$packetType				= ord($packet[1]);
//		$packet_func			= "proc_".strtolower ($packet_type);

		// Cleanup streamBuffer
		$this->streamBuf		= substr ($this->streamBuf, $sizebyte);
		$this->streamBufLen		= strlen ($this->streamBuf);
		
		console('Bytes left in buffer : '.$this->streamBufLen);
		
		return $packet;
	}
	
	private function getIP() {
		if ($this->verifyIP($this->ip)) {
			return $this->ip;
		} else {
			$tmp_ip = @gethostbyname($this->ip);
			if ($this->verifyIP($tmp_ip)) {
				return $tmp_ip;
			}
		}
		
		return FALSE;
	}
	
	private function verifyIP($ip) {
		$exp = explode('.', $ip);
		if (!is_array($exp) || count($exp) != 4)
			return FALSE;
		
		foreach ($exp as $v) {
			$val = (int) $v;
			if ($val != $v || $val > 255)
				return FALSE;
		}
		return TRUE;
	}
}

?>