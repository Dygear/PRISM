<?php
/**
 * PHPInSimMod - Http Module
 * @package PRISM
 * @subpackage Http
*/

require_once(ROOTPATH . '/modules/prism_sectionhandler.php');
require_once(ROOTPATH . '/modules/prism_phpparser.php');

define('HTTP_AUTH_REALM', 'Prism administration');	// Token used for http auth & digest
define('HTTP_KEEP_ALIVE', 10);						// Keep-alive timeout in seconds
define('HTTP_MAX_REQUEST_SIZE', 2097152);			// Max http request size in bytes (headers + data)
define('HTTP_MAX_URI_LENGTH', 4096);				// Max length of the uri in the first http header
define('HTTP_MAX_CONN', 1024);						// Max number of simultaneous http connections
													// Experimentation showed it's best to keep this pretty high.
													// FD_SETSIZE is usually 1024; the max connections allowed on a socket.

class HttpHandler extends SectionHandler
{
	private $httpSock		= NULL;
	private $httpClients	= array();
	private $httpNumClients	= 0;

	private $httpVars		= array();
	public $cache			= null;
	private $nonceCache		= array();
	
	private $docRoot		= '';
	private $logFile		= '';
	private $siteDomain		= '';

	public function __construct()
	{
		$this->iniFile = 'http.ini';
	}
	
	public function getHttpNumClients()
	{
		return $this->httpNumClients;
	}

	public function &getHttpInfo()
	{
		$info = array();
		foreach ($this->httpClients as $k => $v)
		{
			$info[] = array(
				'ip' => $v->getRemoteIP(),
				'port' => $v->getRemotePort(),
				'lastActivity' => $v->getLastActivity(),
			);
		}
		return $info;
	}

	public function getDocRoot()
	{
		return $this->docRoot;
	}
	
	public function getLogFile()
	{
		return $this->logFile;
	}
	
	public function getSiteDomain()
	{
		return $this->siteDomain;
	}
	
	public function getHttpAuthPath()
	{
		return $this->httpVars['httpAuthPath'];
	}
	
	public function getHttpAuthType()
	{
		return $this->httpVars['httpAuthType'];
	}
	
	public function getNonceInfo(&$nonce)
	{
		if (!isset($this->nonceCache[$nonce]))
			return false;
		
		return array($this->nonceCache[$nonce][0], $this->nonceCache[$nonce][1], $this->nonceCache[$nonce][2]);
	}
	
	public function addNewNonce(&$nonce)
	{
		$opaque = createRandomString(16, RAND_HEX);
		$this->nonceCache[$nonce] = array(time(), 0, $opaque);
		return $opaque;
	}
	
	public function incNonceCounter(&$nonce, &$nc)
	{
		if (!isset($this->nonceCache[$nonce]))
			return false;
		
		$this->nonceCache[$nonce][0] = time();
		$this->nonceCache[$nonce][1] = $nc;
		
		return true;
	}
	
	public function __destruct()
	{
		$this->close(true);
	}
	
	private function close($all)
	{
		if (is_resource($this->httpSock))
			fclose($this->httpSock);
		
		if (!$all)
			return;
		
		for ($k=0; $k<$this->httpNumClients; $k++)
		{
			array_splice($this->httpClients, $k, 1);
			$k--;
			$this->httpNumClients--;
		}
	}
	
	public function initialise()
	{
		global $PRISM;
		
		$this->httpVars = array
		(
			'ip'			=> '', 
			'port'			=> 0, 
			'path'			=> 'www-docs', 
			'siteDomain'	=> '', 
			'httpAuthPath'	=> '', 
			'httpAuthType'	=> 'Digest', 
			'logFile'		=> 'logs/http.log',
		);
		
		if ($this->loadIniFile($this->httpVars, false))
		{
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded '.$this->iniFile);
		}
		else
		{
			# We ask the client to manually input the connection details here.
			require_once(ROOTPATH . '/modules/prism_interactive.php');
			Interactive::queryHttp($this->httpVars);
			
			# Then build a http.ini file based on these details provided.
			$extraInfo = <<<ININOTES
;
; Http listen details (for administration web pages).
; 0.0.0.0 will bind the listen socket to all available network interfaces.
; To limit the bind to one interface only, you can enter its IP address here.
; If you do not want to use the http feature, you can comment or remove the lines,
; or enter "" and 0 for the ip and port.
;

ININOTES;
			if ($this->createIniFile('HTTP Configuration (web admin)', array('http' => &$this->httpVars), $extraInfo))
				console('Generated config/'.$this->iniFile);
		}

		// Set docRoot
		if (!$this->setDocRoot())
			return false;
		
		// Set logFile
		if (!$this->setLogFile())
			return false;
		
		// Setup http socket to listen on
		if (!$this->setupListenSocket())
			return false;
		
		// Setup site domain
		$this->setupSiteDomain();
		
		// Validate httpAuthPath
		if (!$this->validateAuthPath())
			return false;
		
		// Validate httpAuthType
		if ($this->httpVars['httpAuthType'] != 'Digest' & $this->httpVars['httpAuthType'] != 'Basic')
		{
			console('Invalid httpAuthType in '.$this->iniFile);
			return false;
		}
		
		return true;
	}
	
	private function setupListenSocket()
	{
		$this->close(false);
		
		if ($this->httpVars['ip'] != '' && $this->httpVars['port'] > 0)
		{
			$this->httpSock = @stream_socket_server('tcp://'.$this->httpVars['ip'].':'.$this->httpVars['port'], $httpErrNo, $httpErrStr);
			if (!is_resource($this->httpSock) || $this->httpSock === FALSE || $httpErrNo)
			{
				console('Error opening http socket : '.$httpErrStr.' ('.$httpErrNo.')');
				return false;
			}
			else
			{
				console('Listening for http requests on '.$this->httpVars['ip'].':'.$this->httpVars['port']);
			}
		}
		return true;
	}
	
	private function setDocRoot()
	{
		// Strip trailing slashes
		$this->httpVars['path'] = preg_replace('/(.*)([\/\\\]*)$/U', '\\1', $this->httpVars['path']);
		
		if ($this->httpVars['path'] == '')
			$this->httpVars['path'] = 'www-docs';
		
		// Store in docRoot
		$this->docRoot = 
			($this->httpVars['path'][0] == '/' || (isset($this->httpVars['path'][1]) && $this->httpVars['path'][1] == ':')) ? 
			$this->httpVars['path'] : 
			ROOTPATH.'/'.$this->httpVars['path'];
		
		// Check if it's valid
		if (!file_exists($this->docRoot))
		{
			console('The path to your web-root does not exist : '.$this->httpVars['path']);
			return false;
		}
		
		return true;
	}

	private function setLogFile()
	{
		// Strip trailing slashes
		$this->httpVars['logFile'] = preg_replace('/(.*)([\/\\\]*)$/U', '\\1', $this->httpVars['logFile']);
		
		if ($this->httpVars['logFile'] == '')
			$this->httpVars['logFile'] = 'logs/http.log';
		
		// Store in logFile
		$this->logFile = 
			($this->httpVars['logFile'][0] == '/' || (isset($this->httpVars['logFile'][1]) && $this->httpVars['logFile'][1] == ':')) ? 
			$this->httpVars['logFile'] : 
			ROOTPATH.'/'.$this->httpVars['logFile'];
		
		// Check if its path is valid
		$logPath = pathinfo($this->logFile);
		if (!isset($logPath['filename']) || $logPath['filename'] == '' || !file_exists($logPath['dirname']))
		{
			console('The path to your log folder does not exist : '.$logPath);
			return false;
		}
		else if (is_dir($this->logFile))
		{
			console('The path to your http log folder is a folder itself : '.$this->logFile);
			return false;
		}
		
		return true;
	}

	private function setupSiteDomain()
	{
		$this->siteDomain = '';
		
		// Ignore site domain? (accept any incoming request, no matter what host the request contains)
		if ($this->httpVars['siteDomain'] == '')
			return;
		if (!getIP($this->httpVars['siteDomain']))
		{
			console('Invalid siteDomain provided in '.$this->iniFile.' (it does not resolve). Ignoring this setting.');
			return;
		}
		
		$this->siteDomain = $this->httpVars['siteDomain'];
	}
	
	private function validateAuthPath()
	{
		if ($this->httpVars['httpAuthPath'] == '')
			return true;
		if ($this->httpVars['httpAuthPath'] == '/')
		{
			$this->httpVars['httpAuthPath'] = $this->docRoot.$this->httpVars['httpAuthPath'];
			return true;
		}
		
		// Strip trailing slashes
		$this->httpVars['httpAuthPath'] = preg_replace('/(.+)([\/\\\]*)$/U', '\\1', $this->httpVars['httpAuthPath']);
		
		// Check relative or absolute
		$this->httpVars['httpAuthPath'] = 
			($this->httpVars['httpAuthPath'][0] == '/' || (isset($this->httpVars['httpAuthPath'][1]) && $this->httpVars['httpAuthPath'][1] == ':')) ? 
			$this->httpVars['httpAuthPath'] : 
			$this->docRoot.'/'.$this->httpVars['httpAuthPath'];
		
		// Check if it's valid
		if (!file_exists($this->httpVars['httpAuthPath']))
		{
			console('httpAuthPath path does not exist : '.$this->httpVars['httpAuthPath']);
			return false;
		}
		
		return true;
	}
	
	public function getSelectableSockets(array &$sockReads, array &$sockWrites)
	{
		// Add http sockets to sockReads
		if (is_resource($this->httpSock))
			$sockReads[] = $this->httpSock;

		for ($k=0; $k<$this->httpNumClients; $k++)
		{
			if (is_resource($this->httpClients[$k]->getSocket()))
			{
				$sockReads[] = $this->httpClients[$k]->getSocket();
				
				// If write buffer was full, we must check to see when we can write again
				if ($this->httpClients[$k]->getSendQLen() > 0 || $this->httpClients[$k]->getSendFilePntr() > -1)
					$sockWrites[] = $this->httpClients[$k]->getSocket();
			}
		}
	}

	public function checkTraffic(array &$sockReads, array &$sockWrites)
	{
		$activity = 0;

		// httpSock input (incoming http connection)
		if (in_array ($this->httpSock, $sockReads))
		{
			$activity++;
			
			// Accept the new connection
			$peerInfo = '';
			$sock = @stream_socket_accept ($this->httpSock, NULL, $peerInfo);
			if (is_resource($sock))
			{
				stream_set_blocking ($sock, 0);
				
				// Add new connection to httpClients array
				$exp = explode(':', $peerInfo);
				$this->httpClients[] = new HttpClient($this, $sock, $exp[0], $exp[1]);
				$this->httpNumClients++;
				console('HTTP Client '.$exp[0].':'.$exp[1].' connected.');
			}
			unset ($sock);
		}
		
		// httpClients input
		for ($k=0; $k<$this->httpNumClients; $k++) {
			// Recover from a full write buffer?
			if (($this->httpClients[$k]->getSendQLen() > 0  || 
				 $this->httpClients[$k]->getSendFilePntr() > -1) &&
				in_array($this->httpClients[$k]->getSocket(), $sockWrites))
			{
				$activity++;
				
				// Flush the sendQ (bit by bit, not all at once - that could block the whole app)
				if ($this->httpClients[$k]->getSendQLen() > 0)
					$this->httpClients[$k]->flushSendQ();
				else
					$this->httpClients[$k]->writeFile();
			}
			
			// Did we receive something from a httpClient?
			if (!in_array($this->httpClients[$k]->getSocket(), $sockReads))
				continue;

			$activity++;
			
			$data = $this->httpClients[$k]->read();
			
			// Did the client hang up?
			if ($data == '')
			{
				console('Closed httpClient (client initiated) '.$this->httpClients[$k]->getRemoteIP().':'.$this->httpClients[$k]->getRemotePort());
				array_splice ($this->httpClients, $k, 1);
				$k--;
				$this->httpNumClients--;
				continue;
			}

			// Ok we recieved some input from the http client.
			// Pass the data to the HttpClient so it can handle it.
			if (!$this->httpClients[$k]->handleInput($data, $errNo))
			{
				// Something went wrong - we can hang up now
				console('Closed httpClient ('.$errNo.' - '.HttpResponse::$responseCodes[$errNo].') '.$this->httpClients[$k]->getRemoteIP().':'.$this->httpClients[$k]->getRemotePort());
				array_splice ($this->httpClients, $k, 1);
				$k--;
				$this->httpNumClients--;
				continue;
			}
		}
		
		return $activity;
	}

	public function maintenance()
	{
		for ($k=0; $k<$this->httpNumClients; $k++)
		{
			if ($this->httpClients[$k]->getLastActivity() < time() - HTTP_KEEP_ALIVE)
			{
				console('Closed httpClient (keep alive) '.$this->httpClients[$k]->getRemoteIP().':'.$this->httpClients[$k]->getRemotePort());
				array_splice ($this->httpClients, $k, 1);
				$k--;
				$this->httpNumClients--;
			}
		}
	}
}

class HttpClient
{
	private $http			= null;
	private $socket			= null;
	private $ip				= '';
	private $port			= 0;
	private $localIP		= '';
	private $localPort		= 0;
	
	private $lastActivity	= 0;
	
	// send queue used for backlog, in case we can't send a reply in one go
	private $sendQ			= '';
	private $sendQLen		= 0;
	
	private $sendFile		= null;				// contains handle to file we're sending
	private $sendFilePntr	= -1;				// Points to where we are in the file
	private $sendFileSize	= 0;				// Points to where we are in the file

	private $sendWindow		= STREAM_WRITE_BYTES;	// dynamic window size

	private $httpRequest	= null;
	
	public function __construct(HttpHandler &$http, &$sock, &$ip, &$port)
	{
		$this->http			= $http;
		$this->socket		= $sock;
		$this->ip			= $ip;
		$this->port			= $port;
		
		$localInfo = stream_socket_get_name($this->socket, false);
		$exp = explode(':', $localInfo);
		$this->localIP		= $exp[0];
		$this->localPort	= (int) $exp[1];
		
		$this->lastActivity	= time();
	}
	
	public function __destruct()
	{
		if (is_resource($this->socket))
			fclose($this->socket);
		
		if ($this->sendFile)
			$this->writeFileReset();
		if ($this->sendQLen > 0)
			$this->sendQReset();
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
			$this->sendWindow += STREAM_WRITE_BYTES;
		else
		{
			$this->sendWindow -= STREAM_WRITE_BYTES;
			if ($this->sendWindow < STREAM_WRITE_BYTES)
				$this->sendWindow = STREAM_WRITE_BYTES;
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
	
	public function &getSendFilePntr()
	{
		return $this->sendFilePntr;
	}
	
	public function writeFile($fileName = '', $startOffset = 0)
	{
		if ($fileName != '' && $this->sendFile == null)
		{
			$this->sendFile = fopen($fileName, 'rb');
			if (!$this->sendFile)
				return false;
			$this->sendFilePntr = (int) $startOffset;
			fseek($this->sendFile, $this->sendFilePntr);
			$this->sendFileSize = filesize($fileName);
			$this->sendWindow	+= STREAM_WRITE_BYTES;
		}
		
		$bytes = @fwrite($this->socket, fread($this->sendFile, $this->sendWindow));
		$this->sendFilePntr += $bytes;
		fseek($this->sendFile, $this->sendFilePntr);
		$this->lastActivity = time();
		
		// Dynamic window sizing
		if ($bytes == $this->sendWindow)
			$this->sendWindow += STREAM_WRITE_BYTES;
		else
		{
			$this->sendWindow -= STREAM_WRITE_BYTES;
			if ($this->sendWindow < STREAM_WRITE_BYTES)
				$this->sendWindow = STREAM_WRITE_BYTES;
		}
		
		//console('BYTES : '.$bytes.' - PNTR : '.$this->sendFilePntr);
		// Done?
		if ($this->sendFilePntr >= $this->sendFileSize)
			$this->writeFileReset();
	}
	
	private function writeFileReset()
	{
		@fclose($this->sendFile);
		$this->sendFile = null;
		$this->sendFileSize = 0;
		$this->sendFilePntr = -1;
	}

	private function createErrorPage($errNo, $errStr = '', $appendPadding = false)
	{
		$eol = "\r\n";
		$out = '<html>'.$eol;
		$out .= '<head><title>'.$errNo.' '.HttpResponse::$responseCodes[$errNo].'</title></head>'.$eol;
		$out .= '<body bgcolor="white">'.$eol;
		$out .= '<center><h1>'.$errNo.' '.(($errStr != '') ? $errStr : HttpResponse::$responseCodes[$errNo]).'</h1></center>'.$eol;
		$out .= '<hr><center>PRISM v'.PHPInSimMod::VERSION.'</center>'.$eol;
		$out .= '</body>'.$eol;
		$out .= '</html>'.$eol;
		
		if ($appendPadding)
		{
			for ($a=0; $a<6; $a++)
				$out .= '<!-- a padding to disable MSIE and Chrome friendly error page -->'.$eol;
		}
		return $out;
	}
	
	public function read()
	{
		$this->lastActivity	= time();
		return fread($this->socket, STREAM_READ_BYTES);
	}
	
	public function handleInput(&$data, &$errNo)
	{
		// What is this? we're getting input while we're sending a reply?
		if ($this->sendFile)
		{
			$this->writeFileReset();
			$this->httpRequest = null;
		}
		else if ($this->sendQLen > 0)
		{
			$this->sendQReset();
			$this->httpRequest = null;
		}

		if (!$this->httpRequest)
			$this->httpRequest = new HttpRequest();

		// Pass the incoming data to the HttpRequest class, so it can handle it.		
		if (!$this->httpRequest->handleInput($data))
		{
			// An error was encountered while receiving the requst.
			// Send reply (unless 444, a special 'direct reject' code) and return false to close this connection.
			if ($this->httpRequest->errNo != 444)
			{
				$r = new HttpResponse('1.1', $this->httpRequest->errNo);
				$r->addBody($this->createErrorPage($this->httpRequest->errNo, $this->httpRequest->errStr));
				
				if ($this->httpRequest->errNo == 405)
				{
					$r->addHeader('Allow: GET, POST, HEAD');
					$r->addHeader('Access-Control-Allow-Methods: GET, POST, HEAD');
				}
					
				$this->write($r->getHeaders());
				$this->write($r->getBody());

				$this->logRequest($r->getResponseCode(), (($r->getHeader('Content-Length')) ? $r->getHeader('Content-Length') : 0));
			}
			else
			{
				$this->logRequest(444, 0);
			}
			$errNo = $this->httpRequest->errNo;
			return false;
		}
		
		// If we have no headers, or we are busy with receiving.
		// Just return and wait for more data.
		if (!$this->httpRequest->hasHeaders || 			// We're still receiving headers
			$this->httpRequest->isReceiving) 			// We're still receiving the body of a request
			return true;								// Return true to just wait and try again later
		
		// At this point we have a fully qualified and parsed HttpRequest
		// The HttpRequest object contains all info about the headers / GET / POST / COOKIE / FILES
		// Just finalise it by adding some extra client info.
		$this->httpRequest->SERVER['REMOTE_ADDR']			= $this->ip;
		$this->httpRequest->SERVER['REMOTE_PORT']			= $this->port;
		$this->httpRequest->SERVER['SERVER_ADDR']			= $this->localIP;
		$this->httpRequest->SERVER['SERVER_PORT']			= $this->localPort;
		$exp = explode(':', $this->httpRequest->headers['Host']);
		$this->httpRequest->SERVER['SERVER_NAME']			= $exp[0];
		$this->httpRequest->SERVER['HTTP_HOST']				= $this->httpRequest->headers['Host'];
		$this->httpRequest->SERVER['HTTP_USER_AGENT']		= isset($this->httpRequest->headers['User-Agent']) ? $this->httpRequest->headers['User-Agent'] : '';
		$this->httpRequest->SERVER['HTTP_ACCEPT']			= isset($this->httpRequest->headers['Accept']) ? $this->httpRequest->headers['Accept'] : '';
		$this->httpRequest->SERVER['HTTP_ACCEPT_LANGUAGE']	= isset($this->httpRequest->headers['Accept-Language']) ? $this->httpRequest->headers['Accept-Language'] : '';
		$this->httpRequest->SERVER['HTTP_ACCEPT_ENCODING']	= isset($this->httpRequest->headers['Accept-Encoding']) ? $this->httpRequest->headers['Accept-Encoding'] : '';
		$this->httpRequest->SERVER['HTTP_ACCEPT_CHARSET']	= isset($this->httpRequest->headers['Accept-Charset']) ? $this->httpRequest->headers['Accept-Charset'] : '';
		$this->httpRequest->SERVER['HTTP_CONNECTION']		= isset($this->httpRequest->headers['Connection']) ? $this->httpRequest->headers['Connection'] : '';
		$this->httpRequest->SERVER['HTTP_KEEP_ALIVE']		= isset($this->httpRequest->headers['Keep-Alive']) ? $this->httpRequest->headers['Keep-Alive'] : '';
		if (isset($this->httpRequest->headers['Referer']))
			$this->httpRequest->SERVER['HTTP_REFERER']		= $this->httpRequest->headers['Referer'];
		if (isset($this->httpRequest->headers['Range']))
			$this->httpRequest->SERVER['HTTP_RANGE']		= $this->httpRequest->headers['Range'];
		if (isset($this->httpRequest->headers['Cookie']))
			$this->httpRequest->SERVER['HTTP_COOKIE']= $this->httpRequest->headers['Cookie'];
		if (isset($this->httpRequest->headers['Authorization']))
			$this->httpRequest->SERVER['HTTP_AUTHORIZATION']= $this->httpRequest->headers['Authorization'];
		$this->httpRequest->SERVER['REQUEST_TIME']			= time();
		
		// Check if we have to match siteDomain
		if ($this->http->getSiteDomain() != '' &&
			$this->http->getSiteDomain() != $this->httpRequest->SERVER['SERVER_NAME'])
		{
			$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 404);
			$r->addBody($this->createErrorPage(404));
			$this->write($r->getHeaders());
			$this->write($r->getBody());
			$errNo = 404;
			$this->logRequest($r->getResponseCode(), (($r->getHeader('Content-Length')) ? $r->getHeader('Content-Length') : 0));
			return false;
		}
		
		// HTTP Authorisation?
		if ($this->http->getHttpAuthPath() != '')
		{
			$scriptPath = pathinfo($this->httpRequest->SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
			
			// Check if path must be auth'd and if HTTP_AUTHORIZATION header exists and if so, validate it
			if (isDirInDir($this->http->getHttpAuthPath(), $this->http->getDocRoot().$scriptPath) &&
				(!isset($this->httpRequest->SERVER['HTTP_AUTHORIZATION']) ||
				 !$this->validateAuthorization()))
			{
				// Not validated - send 401 Unauthorized
				do
				{
					$nonce = createRandomString(17, RAND_HEX);
					if (!$this->http->getNonceInfo($nonce))
						break;
				} while(true);
				$opaque = $this->http->addNewNonce($nonce);
				
				$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 401);
				if ($this->http->getHttpAuthType() == 'Digest')
					$r->addHeader('WWW-Authenticate: Digest realm="'.HTTP_AUTH_REALM.'", qop="auth", nonce="'.$nonce.'", opaque="'.$opaque.'"');
				else
					$r->addHeader('WWW-Authenticate: Basic realm="'.HTTP_AUTH_REALM.'"');
				$r->addBody($this->createErrorPage(401, '', true));
				$this->write($r->getHeaders());
				$this->write($r->getBody());
				$errNo = 401;
				$this->logRequest($r->getResponseCode(), (($r->getHeader('Content-Length')) ? $r->getHeader('Content-Length') : 0));
				
				$this->httpRequest = null;
				return true;		// we return true this time because we may stay connected
			}
		}
		
		//var_dump($this->httpRequest->headers);
		//var_dump($this->httpRequest->SERVER);
		//var_dump($this->httpRequest->GET);
		//var_dump($this->httpRequest->POST);
		//var_dump($this->httpRequest->COOKIE);
		
		// Rewrite script name? (keep it internal - don't rewrite SERVER header
		$scriptName = ($this->httpRequest->SERVER['SCRIPT_NAME'] == '/') ? '/index.php' : $this->httpRequest->SERVER['SCRIPT_NAME'];
		
		if (file_exists($this->http->getDocRoot().$scriptName))
		{
			// Should we serve a file or pass the request to PHPParser for page generation?
			if (preg_match('/^.*\.php$/', $scriptName))
			{
				if ($this->httpRequest->SERVER['REQUEST_METHOD'] == 'HEAD')
				{
					$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 200);
					$this->write($r->getHeaders());
				}
				else
				{
					// 'Parse' the php file
					$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 200);
					$html = PHPParser::parseFile(
						$r,
						$scriptName,
						$this->httpRequest->SERVER,
						$this->httpRequest->GET,
						$this->httpRequest->POST,
						$this->httpRequest->COOKIE,
						$this->httpRequest->FILES
					);
		
					$r->addBody($html);
					
					$this->write($r->getHeaders());
					$this->write($r->getBody());
				}
			}
			else if (is_dir($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']))
			{
				// 403 - not allowed to view folder contents
				$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 403);
				$r->addBody($this->createErrorPage(403));
	
				$this->write($r->getHeaders());
				$this->write($r->getBody());
			}
			else
			{
				// Send a file
				if ($this->httpRequest->SERVER['REQUEST_METHOD'] == 'HEAD')
				{
					$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 200);
					$this->write($r->getHeaders());
				}
				else
				{
					$r = $this->serveFile();
				}
			}
		}
		else
		{
			// 404
			$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 404);
			$r->addBody($this->createErrorPage(404));

			$this->write($r->getHeaders());
			$this->write($r->getBody());
		}
		
		// log line
		$this->logRequest($r->getResponseCode(), (($r->getHeader('Content-Length')) ? $r->getHeader('Content-Length') : 0));
		
		// Reset httpRequest
		$this->httpRequest = null;

		return true;
	}
	
	private function &serveFile()
	{
		// Serve file - we can do this using the writeFile() method, which is memory friendly
		$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 200);
		
		// Cache?
		$useCache = false;
		$scriptnameHash = md5($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']);
		if (isset($this->httpRequest->headers['Cache-Control']) || isset($this->httpRequest->headers['Pragma']))
		{
			$ifModifiedSince =
				isset($this->httpRequest->headers['If-Modified-Since']) ?
				(int) strtotime($this->httpRequest->headers['If-Modified-Since']) :
				0;
			$cacheControl = 
				isset($this->httpRequest->headers['Cache-Control']) ?
				$this->httpRequest->parseHeaderValue($this->httpRequest->headers['Cache-Control']) :
				array();
			$pragma = 
				isset($this->httpRequest->headers['Pragma']) ?
				$this->httpRequest->parseHeaderValue($this->httpRequest->headers['Pragma']) :
				array();
			
			// Detect 'If-Modified-Since' (weak) cache validator (http1.1)
			if ($ifModifiedSince > 0)
			{
				if (isset($this->http->cache[$scriptnameHash]))
				{
					if ($this->http->cache[$scriptnameHash] == $ifModifiedSince)
					{
						// File has not been changed - tell the browser to use the cache (send a 304)
						$useCache = true;
					}
				}
				else
				{
					$scriptMTime = filemtime($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']);
					$this->http->cache[$scriptnameHash] = $scriptMTime;
					if ($scriptMTime == $ifModifiedSince)
					{
						// File has not been changed - tell the browser to use the cache (send a 304)
						$useCache = true;
					}
				}
			}
			// Otherwise detect 'Cache-Control' or 'Pragma' (strong) validators (http1.0/http1.1)
			else if ((isset($cacheControl['max-age']) && $cacheControl['max-age'] == 0) &&
						$cacheControl != 'no-cache' &&
						$pragma != 'no-cache' &&
						isset($this->http->cache[$scriptnameHash]))
			{
				$scriptMTime = filemtime($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']);
				if ($this->http->cache[$scriptnameHash] == $scriptMTime)
				{
					// File has not been changed - tell the browser to use the cache (send a 304)
					$useCache = true;
				}
				else
				{
					// File has been updated - store new mtime in cache
					$this->http->cache[$scriptnameHash] = $scriptMTime;
				}
				clearstatcache();
			}
		}

		if ($useCache)
		{				
			$r->setResponseCode(304);
			$this->write($r->getHeaders());
		}
		else
		{
			$scriptMTime = filemtime($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']);

			$r->addHeader('Content-Type: '.$this->getMimeType());
			$r->addHeader('Last-Modified: '.date('r', $scriptMTime));
			
			if (isset($this->httpRequest->SERVER['HTTP_RANGE']))
			{
				console('HTTP_RANGE HEADER : '.$this->httpRequest->SERVER['HTTP_RANGE']);
			    $exp = explode('=', $this->httpRequest->SERVER['HTTP_RANGE']);
			    $startByte = (int) substr($exp[1], 0, -1);

				$r->addHeader('Content-Length: '.(filesize($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']) - $startByte));
				$this->write($r->getHeaders());
				$this->writeFile($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME'], $startByte);
			}
			else
			{
				$r->addHeader('Content-Length: '.filesize($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']));
				$this->write($r->getHeaders());
				$this->writeFile($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']);
			}
			
			// Store the filemtime in $cache
			if (!isset($this->http->cache[$scriptnameHash]))
			{
				$this->http->cache[$scriptnameHash] = $scriptMTime;
			}
			clearstatcache();
		}
		
		return $r;
	}

	private function validateAuthorization()
	{
		global $PRISM;

		$matches = array();
		if (preg_match('/^Digest (.*)$/', $this->httpRequest->SERVER['HTTP_AUTHORIZATION'], $matches))
		{
			// Digest method
			$info = array();
			$infoTmp = $this->httpRequest->parseHeaderValue($matches[1]);
			foreach ($infoTmp as $a => $b)
			{
				foreach ($b as $k => $v)
					$info[$k] = preg_replace('/"?(.*)"?/U', '\\1', $v);
			}
			
			// Check that all values are provided
			if (!isset($info['username']) ||
				!isset($info['realm']) ||
				!isset($info['nonce']) ||
				!isset($info['uri']) ||
				!isset($info['response']) ||
				!isset($info['opaque']) ||
				!isset($info['qop']) ||
				!isset($info['nc']) ||
				!isset($info['cnonce']))
				return false;

			//  Check that nonce exists and nc is not reused AND that opaque value matches
			if (!($nonceInfo = $this->http->getNonceInfo($info['nonce'])))
				return false;
			if ($nonceInfo[1] >= $info['nc'])
				return false;
			if (!isset($nonceInfo[2]))
				return false;
			if ($nonceInfo[2] != $info['opaque'])
				return false;		
						
			// Do the digest check
			if (!($ha1 = $PRISM->admins->getRealmDigest($info['username'])))
				return false;
			$ha2 = md5($this->httpRequest->SERVER['REQUEST_METHOD'].':'.$info['uri']);
			$response = md5($ha1.':'.$info['nonce'].':'.$info['nc'].':'.$info['cnonce'].':'.$info['qop'].':'.$ha2);
			if ($response != $info['response'])
				return false;
			
			// Validated!
			$this->http->incNonceCounter($info['nonce'], $info['nc']);
			$this->httpRequest->SERVER['PHP_AUTH_USER']	= $info['username'];
		}
		else if (preg_match('/^Basic (.*)$/', $this->httpRequest->SERVER['HTTP_AUTHORIZATION'], $matches))
		{
			// Basic method
			$auth = explode(':', base64_decode($matches[1]), 2);
			if (count($auth) != 2 || !$PRISM->admins->isPasswordCorrect($auth[0], $auth[1]))
				return false;
			
			// Validated!
			$this->httpRequest->SERVER['PHP_AUTH_USER']	= $auth[0];
			$this->httpRequest->SERVER['PHP_AUTH_PW']	= $auth[1];
		}
		else
		{
			// Unknown or no authorisation provided
			return false;
		}

		return true;
	}
	
	private function getMimeType()
	{
		$pathInfo = pathinfo($this->httpRequest->SERVER['SCRIPT_NAME']);
		
		$mimeType = 'application/octet-stream';
		switch(strtolower($pathInfo['extension']))
		{
			case 'txt' :
				$mimeType = 'text/plain';
				break;

			case 'html' :
			case 'htm' :
			case 'shtml' :
				$mimeType = 'text/html';
				break;

			case 'css' :
				$mimeType = 'text/css';
				break;

			case 'xml' :
				$mimeType = 'text/xml';
				break;

			case 'gif' :
				$mimeType = 'image/gif';
				break;

			case 'jpeg' :
			case 'jpg' :
				$mimeType = 'image/jpeg';
				break;

			case 'png' :
				$mimeType = 'image/png';
				break;

			case 'tif' :
			case 'tiff' :
				$mimeType = 'image/tiff';
				break;

			case 'wbmp' :
				$mimeType = 'image/vnd.wap.wbmp';
				break;

			case 'bmp' :
				$mimeType = 'image/x-ms-bmp';
				break;

			case 'svg' :
				$mimeType = 'image/svg+xml';
				break;

			case 'ico' :
				$mimeType = 'image/x-icon';
				break;

			case 'js' :
				$mimeType = 'application/x-javascript';
				break;

			case 'atom' :
				$mimeType = 'application/atom+xml';
				break;

			case 'rss' :
				$mimeType = 'application/rss+xml';
				break;
		}
		return $mimeType;
	}
	
	private function logRequest($code, $size = 0)
	{
		if ($this->http->getLogFile() == '')
			return;
		
		$logLine =
			$this->ip.' '.
			'- '.
			((isset($this->httpRequest->SERVER['PHP_AUTH_USER'])) ? str_replace(' ', '_', $this->httpRequest->SERVER['PHP_AUTH_USER']) : '-').' '.
			'['.date('d/M/Y:H:i:s O').'] '.
			'"'.$this->httpRequest->requestLine.'" '.
			$code.' '.
			$size.' '.
			'"'.((isset($this->httpRequest->SERVER['HTTP_REFERER'])) ? $this->httpRequest->SERVER['HTTP_REFERER'] : '-').'" '.
			'"'.((isset($this->httpRequest->SERVER['HTTP_USER_AGENT'])) ? $this->httpRequest->SERVER['HTTP_USER_AGENT'] : '-').'" '.
			'"-"';
		console($logLine);
		file_put_contents($this->http->getLogFile(), $logLine."\r\n", FILE_APPEND);
	}
}

class HttpRequest
{
	private $rawInput		= '';
	
	public $isReceiving		= false;
	public $hasRequestUri	= false;
	public $requestLine		= '';
	public $hasHeaders		= false;
	
	public $errNo			= 0;
	public $errStr			= '';
	
	public $headers			= array();		// This will hold all of the request headers from the clients browser.
	private $tmpFiles		= array();
	
	public $SERVER			= array();
	public $GET				= array();		// With these arrays we try to recreate php's global vars a bit.
	public $POST			= array();
	public $FILES			= array();
	public $COOKIE			= array();
	
	public function __construct()
	{

	}
	
	public function __destruct()
	{
		// tmpFiles cleanup
		foreach ($this->tmpFiles as $v)
			unlink($v);
	}

	public function handleInput(&$data)
	{
		// We need to buffer the input - no idea how much data will 
		// be coming in until we have received all the headers.
		// Normally though all headers should come in unfragmented, but don't rely on that.
		$this->rawInput .= $data;
		if (strlen($this->rawInput) > HTTP_MAX_REQUEST_SIZE)
		{
			$this->errNo = 413;
			$this->errStr = 'You tried to send more than '.HTTP_MAX_REQUEST_SIZE.' bytes to the server, which it doesn\'t like.';
			return false;
		}

		// Check if we have header lines in the buffer, for as long as !$this->hasHeaders
		if (!$this->hasHeaders)
		{
			if (!$this->parseHeaders())
			{
				if ($this->errNo == 0)
					$this->errNo = 400;
				return false;				// returns false is something went wrong (bad headers)
			}
		}
		
		// If we have headers then we can now figure out if we have received all there is,
		// or if there is more to come. If there is, just return true and wait for more.
		if ($this->hasHeaders)
		{
			// With a GET there will be no extra data. With a POST however ...
			if ($this->SERVER['REQUEST_METHOD'] == 'POST')
			{
				// Check if we have enough and proper data to read the POST
				if (!isset($this->headers['Content-Length']))
				{
					$this->errNo = 411;
					return false;
				}
				$contentType = isset($this->headers['Content-Type']) ? $this->parseContentType($this->headers['Content-Type']) : '';
				if (!$contentType || 
					($contentType['mediaType'] != 'application/x-www-form-urlencoded' &&
					 $contentType['mediaType'] != 'multipart/form-data'))
				{
					$this->errNo = 415;
					$this->errStr = 'No Content-Type was provided that I can handle. At the moment I only like application/x-www-form-urlencoded and multipart/form-data.';
					return false;
				}
				
				// Should we expect more data to come in?
				if ((int) $this->headers['Content-Length'] > strlen($this->rawInput))
				{
					// We have not yet received all the POST data, so I'll return and wait.
					$this->isReceiving = true;
					return true;
				}
				
				// At this point we have the whole POST body
				$this->isReceiving = false;
				
				// Parse POST variables
				if ($contentType['mediaType'] == 'application/x-www-form-urlencoded')
					$this->parsePOSTurlenc(substr($this->rawInput, 0, $this->headers['Content-Length']));
				else if ($contentType['mediaType'] == 'multipart/form-data')
				{
					if (!$this->parsePOSTformdata($this->rawInput, $contentType['boundary'][1]))
					{
						$this->errNo = 400;
						$this->errStr = 'Bad Request - Problems parsing body data';
						return false;
					}
				}

				// Cleanup rawInput
				$this->rawInput = substr($this->rawInput, $this->headers['Content-Length']);
			}
			else
			{
				$this->isReceiving = false;
				
			}
			
			// At this point we have received the entire request. So finally let's parse the (remaining) user variables.
			// Parse GET variables
			$this->parseGET();

			// Parse cookie values
			$this->parseCOOKIE();
			
			// At this point we have parsed the entire request. We are done.
			// Because isReceiving is now false, the HttpClient::handleInput function will 
			// serve the request using the variables from this class HttpRequest.
		}
		
		return true;
	}
	
	private function parseHeaders()
	{
		// Loop through each individual header line
		do
		{
			// Do we have a header line?
			$pos = strpos($this->rawInput, "\r\n");
			if ($pos === false)
			{
				// Extra (garbage) input error checking here
				if (!$this->hasRequestUri)
				{
					$len = strlen($this->rawInput);
					if ($len > HTTP_MAX_URI_LENGTH)
					{
						$this->errNo = 414;
						return false;
					}
					else if ($len > 3 && !preg_match('/^(GET|POST|HEAD).*$/', $this->rawInput))
					{
						$this->errNo = 444;
						return false;
					}
				}
				
				// Otherwise just return and wait for more data
				return true;
			}
			else if ($pos === 0)
			{
				// This cannot possibly be the end of headers, if we don't even have a request uri (or host header)
				if (!$this->hasRequestUri || !isset($this->headers['Host']))
				{
					$this->errNo = 444;
					return false;
				}
				
				// This should be end of headers
				$this->hasHeaders = true;
				$this->rawInput = substr($this->rawInput, 2);		// remove second \r\n
				return true;
			}
			
			$header = substr($this->rawInput, 0, $pos);
			$this->rawInput = substr($this->rawInput, $pos+2);		// +2 to include \r\n

			// Do we have a request line already? If not, try to parse this header line as a request line
			if (!$this->hasRequestUri)
			{
				// Read the first header (the request line)
				if (!$this->parseRequestLine($header))
				{
					if ($this->errNo == 0)
						$this->errNo = 400;
					return false;
				}
				$this->hasRequestUri = true;
			}
			else if (!$this->hasHeaders)
			{
				if (strpos($header, ':') === false)
				{
					$this->errNo = 400;
					return false;
				}
				
				// Parse regular header line
				$exp = explode(':', $header, 2);
				if (count($exp) == 2)
					$this->headers[trim($exp[0])] = trim($exp[1]);
			}
		} while (true);
		return true;
	}
	
	private function parseSubHeaders(&$headers)
	{
		$parsed = array();
		
		// Split header lines
		$lines = explode("\r\n", $headers);
		
		foreach ($lines as $header)
		{
			$exp = explode(':', $header, 2);
			if (count($exp) == 2)
				$parsed[trim($exp[0])] = $this->parseHeaderValue(trim($exp[1]));
		}
		
		return $parsed;
	}
	
	public function parseHeaderValue($header, $level = 0)
	{
//		image/png,image/*;q=0.8,*/*;q=0.5
//		image/png,
//		          image/*;q=0.8,
//		                        */*;q=0.5
		
		// Split by ...
		switch($level)
		{
			case 0 :			// ,
				$items = explode(',', $header);
				break;
			
			case 1 :			// ;
				$items = explode(';', $header);
				break;
			
			case 2 :			// =
				$items = explode('=', $header);
				break;
		}
		
		if ($level == 2)
		{
			if (count($items) == 1)
				return $header;
			else
				return array(trim($items[0]) => $items[1]);
		}
		if (count($items) == 1)
			return $this->parseHeaderValue($header, $level + 1);

		$parsed = array();
		
		foreach ($items as $k => $v)
		{
			$parsed[$k] = $this->parseHeaderValue($v, $level + 1);
		}
		
		return $parsed;
	}

	public function parseContentType(&$header)
	{
		if ($header == '')
			return false;
		
		// Split?
		$parsed = array();
		$exp = explode(';', $header);
		$parsed['mediaType']	= $exp[0];
		$parsed['boundary']		= isset($exp[1]) ? explode('=', $exp[1]) : false;
		
		return $parsed;
	}
	
	private function parseRequestLine($line)
	{
		$this->requestLine = $line;

		$exp = explode(' ', $line);
		if (count($exp) != 3)
		{
			$this->errNo = 444;
			return false;
		}
		
		// check the request command
		if ($exp[0] != 'GET' && $exp[0] != 'POST' && $exp[0] != 'HEAD')
		{
			$this->errNo = 444;
			return false;
		}
		$this->SERVER['REQUEST_METHOD'] = $exp[0];
		
		// Check the request uri
		$this->SERVER['REQUEST_URI'] = $exp[1];
		if (($uri = parse_url($this->SERVER['REQUEST_URI'])) === false)
			return false;
			
		// Path sanitation
		$uri['path'] = filter_var(trim($uri['path']), FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
		if (!isset($uri['path'][0]) || $uri['path'][0] != '/')
			return false;
		$this->SERVER['SCRIPT_NAME'] = $uri['path'];
		
		// Set the query string - all chars allowed in there
		$this->SERVER['QUERY_STRING'] = isset($uri['query']) ? $uri['query'] : '';
		
		// Check for user trying to go below webroot
		$exp2 = explode('/', $this->SERVER['SCRIPT_NAME']);
		foreach ($exp2 as $v)
		{
			if (trim($v) == '..')
			{
				// Ooops the user probably tried something nasty (reach a file outside of our www folder)
				return false;
			}
		}
		
		// Check the HTTP protocol version
		$this->SERVER['SERVER_PROTOCOL'] = $exp[2];
		$httpexp = explode('/', $exp[2]);
		if ($httpexp[0] != 'HTTP' || ($httpexp[1] != '1.0' && $httpexp[1] != '1.1'))
			return false;
		$this->SERVER['httpVersion'] = $httpexp[1];

		return true;
	}
	
	private function parseGET()
	{
		$exp = explode('&', $this->SERVER['QUERY_STRING']);
		foreach ($exp as $v)
		{
			if ($v == '')
				continue;
			$exp2 = explode('=', $v, 2);
			$this->GET[urldecode($exp2[0])] = isset($exp2[1]) ? urldecode($exp2[1]) : '';
		}
	}
	
	private function parsePOSTurlenc($raw)
	{
		$exp = explode('&', $raw);
		foreach ($exp as $v)
		{
			$exp2 = explode('=', $v);
			$key = urldecode($exp2[0]);
			$value = urldecode($exp2[1]);
			
			if (preg_match('/^(.*)\[(.*)\]$/', $key, $matches))
			{
				if (!isset($this->POST[$matches[1]]))
					$this->POST[$matches[1]] = array();

				if ($matches[2] == '')
					$this->POST[$matches[1]][] = $value;
				else
					$this->POST[$matches[1]][$matches[2]] = $value;
			}
			else
			{
				$this->POST[$key] = $value;
			}
		}
	}
	
	private function parsePOSTformdata($raw, $boundary)
	{
		// Check if the raw data at least begins and ends with the boundary
		$bLen = strlen($boundary);
		if (substr($raw, 0, ($bLen + 2)) != '--'.$boundary ||
			trim(substr($raw, -($bLen + 2))) != substr($boundary, 2).'--')
			return false;

		// Split into separate parts
		$parts = explode('--'.$boundary, $raw);
		
		// Always remove the first and last entries, as they are bogus
		array_shift($parts);
		array_pop($parts);
		
		foreach ($parts as $part)
		{
			// Split part headers & data
			$exp = explode("\r\n\r\n", substr($part, 2, -2), 2);
			$headers = $this->parseSubHeaders($exp[0]);

			$key = preg_replace('/^"(.*)"$/', '\\1', $headers['Content-Disposition'][1]['name']);
			$value = $exp[1];

			$contentType = '';
			if (isset($headers['Content-Disposition'][2]['filename']))
				$fileName = preg_replace('/^"(.*)"$/', '\\1', $headers['Content-Disposition'][2]['filename']);
			if (isset($fileName) && isset($headers['Content-Type']))
				$contentType = $headers['Content-Type'];
			
			if (isset($fileName))
			{
				if (!$fileName)
					continue;
				
				$fileError = UPLOAD_ERR_OK;
				
				// Store the uploaded file in a temp place
				$tmpFileName = tempnam(sys_get_temp_dir(), 'Prism');
				if (!@file_put_contents($tmpFileName, $value))
					$fileError = UPLOAD_ERR_CANT_WRITE;
				else
					$this->tmpFiles[] = $tmpFileName;
				
				// Fill $FILES with details on the file
				if (preg_match('/^(.*)\[(.*)\]$/', $key, $matches))
				{
					// Create entry array if not yet exists
					if (!isset($this->FILES[$matches[1]]))
					{
						$this->FILES[$matches[1]] = array(
							'name'		=> array(),
							'tmp_name'	=> array(),
							'type'		=> array(),
							'size'		=> array(),
							'error'		=> array(),
						);
					}
					
					// Fill in the values
					if ($matches[2] == '')
					{
						$this->FILES[$matches[1]]['name'][]		= $fileName;
						$this->FILES[$matches[1]]['tmp_name'][]	= $tmpFileName;
						$this->FILES[$matches[1]]['type'][]		= $contentType;
						$this->FILES[$matches[1]]['size'][]		= strlen($value);
						$this->FILES[$matches[1]]['error'][]	= $fileError;
					}
					else
					{
						$this->FILES[$matches[1]]['name'][$matches[2]]		= $fileName;
						$this->FILES[$matches[1]]['tmp_name'][$matches[2]]	= $tmpFileName;
						$this->FILES[$matches[1]]['type'][$matches[2]]		= $contentType;
						$this->FILES[$matches[1]]['size'][$matches[2]]		= strlen($value);
						$this->FILES[$matches[1]]['error'][$matches[2]]		= $fileError;
					}
				}
				else
				{
					$this->FILES[$key] = array(
						'name'		=> $fileName,
						'tmp_name'	=> $tmpFileName,
						'type'		=> $contentType,
						'size'		=> strlen($value),
						'error'		=> $fileError,
					);
				}
			}
			else
			{
				if (preg_match('/^(.*)\[(.*)\]$/', $key, $matches))
				{
					if (!isset($this->POST[$matches[1]]))
						$this->POST[$matches[1]] = array();
	
					if ($matches[2] == '')
						$this->POST[$matches[1]][] = $value;
					else
						$this->POST[$matches[1]][$matches[2]] = $value;
				}
				else
				{
					$this->POST[$key] = $value;
				}
			}
		}
		
		//var_dump($this->POST);
		return true;
	}
	
	private function parseCOOKIE()
	{
		if (!isset($this->headers['Cookie']))
			return;
		
		$exp = explode(';', $this->headers['Cookie']);
		foreach ($exp as $v)
		{
			$exp2 = explode('=', $v);
			$this->COOKIE[urldecode(ltrim($exp2[0]))] = urldecode($exp2[1]);
		}
	}
}

class HttpResponse
{
	static $responseCodes	= array
		(
			200 => 'OK',
			204 => 'No Content',
			206 => 'Partial Content',
			301 => 'Moved Permanently',
			302 => 'Found',
			304 => 'Not Modified',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'File Not Found',
			405 => 'Method Not Allowed',
			408 => 'Request Timeout',
			411 => 'Length Required',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			444 => 'Garbage Request Rejected',
		);

	private $responseCode	= 200;
	private $httpVersion	= '1.1';
	private $headers		= array
		(
			'Server'		=> '',
			'Date'			=> '',
			'Content-Type'	=> 'text/html',
		);
	private $cookies		= array();
	private $body			= '';
	private $bodyLen		= 0;
		
	public function __construct($httpVersion = '1.1', $code = 200)
	{
		$this->httpVersion = $httpVersion;
		$this->setResponseCode($code);
		$this->headers['Server'] = 'PRISM/'.PHPInSimMod::VERSION;
	}
	
	public function setResponseCode($code)
	{
		$this->responseCode = (int) $code;
	}
	
	public function getResponseCode()
	{
		return $this->responseCode;
	}
	
	public function addHeader($header)
	{
		// Parse the header (validate it)
		$exp = explode(':', $header, 2);
		if (count($exp) != 2)
			return false;
		
		$exp[0] = trim($exp[0]);
		$exp[1] = trim($exp[1]);
		// Check for duplicate (can't do that the easy way because i want to do a case insensitive check)
		foreach ($this->headers as $k => $v)
		{
			if (strtolower($exp[0]) == strtolower($k))
			{
				unset($this->headers[$k]);
				break;
			}
		}
		
		// Store the header
		$this->headers[$exp[0]] = $exp[1];
		
		return true;
	}
	
	public function getHeader($key)
	{
		return isset($this->headers[$key]) ? $this->headers[$key] : false;
	}
	
	public function getHeaders()
	{
		$this->finaliseHeaders();
		
		$headers = 'HTTP/'.$this->httpVersion.' '.$this->responseCode.' '.self::$responseCodes[$this->responseCode]."\r\n";
		foreach ($this->headers as $k => $v)
		{
			$headers .= $k.': '.$v."\r\n";
		}

		foreach ($this->cookies as $k => $v)
			$headers .= 'Set-Cookie: '.urlencode($k).'='.urlencode($v[0]).'; expires='.date('l, d-M-y H:i:s T', (int) $v[1]).'; path='.$v[2].'; domain='.$v[3].(($v[4]) ? '; secure' : '')."\r\n";

		return $headers."\r\n";
	}
	
	private function finaliseHeaders()
	{
		// Adjust the response code for a redirect?
		if (isset($this->headers['Location']))
			$this->responseCode = 302;

		// Set server-side headers
		$this->headers['Date']					= date('r');
		$this->headers['Accept-Ranges']			= 'bytes';
		
		if (!isset($this->headers['Content-Length']) && 
			$this->responseCode != 304)
		{
			$this->headers['Content-Length']	= $this->bodyLen;
		}
		
		if ($this->responseCode == 200 || 
			$this->responseCode == 302 || 
			$this->responseCode == 404)
		{
			$this->headers['Connection']		= 'Keep-Alive';
			$this->headers['Keep-Alive']		= 'timeout='.HTTP_KEEP_ALIVE;
		}
	}
	
	public function addBody($data)
	{
		$this->body .= $data;
		$this->bodyLen += strlen($data);
	}
	
	public function &getBody()
	{
		return $this->body;
	}
	
	public function setCookie($name, $value, $expire, $path, $domain, $secure = false, $httponly = false)
	{
		// Some value sanitation here, because it's user-input.
		$expire = (int) $expire;
		if ($path[0] != '/')
			$path = '/'.$path;
		
		$this->cookies[$name] = array($value, $expire, $path, $domain, $secure, $httponly);
	}
}

?>