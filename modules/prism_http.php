<?php

require_once(ROOTPATH . '/modules/prism_sectionhandler.php');
require_once(ROOTPATH . '/modules/prism_phpparser.php');

define('HTTP_KEEP_ALIVE', 10);					// Keep-alive timeout in seconds
define('HTTP_MAX_REQUEST_SIZE', 2097152);		// Max http request size in bytes (headers + data)
define('HTTP_MAX_URI_LENGTH', 4096);			// Max length of the uri in the first http header
define('HTTP_MAX_CONN', 1024);					// Max number of simultaneous http connections
												// Experimentation showed it's best to keep this pretty high.
												// FD_SETSIZE is usually 1024; the max connections allowed on a socket.

class HttpHandler extends SectionHandler
{
	private $httpSock		= NULL;
	private $httpClients	= array();
	private $httpNumClients	= 0;

	private $httpVars		= array('ip' => '', 'port' => 0);
	public $cache			= null;

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
			// Request timed out?
			if ($this->httpClients[$k]->hasHttpRequest())
			{
				$r = new HttpResponse('1.1', 408);
				$r->addBody($this->httpClients[$k]->createErrorPage(408));
				$this->httpClients[$k]->write($r->getHeaders());
				$this->httpClients[$k]->write($r->getBody());
			}

			array_splice($this->httpClients, $k, 1);
			$k--;
			$this->httpNumClients--;
		}
	}
	
	public function initialise()
	{
		global $PRISM;
		
		$this->httpVars = array('ip' => '', 'port' => 0);
		
		if ($this->loadIniFile($this->httpVars, 'http.ini', false))
		{
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded http.ini');
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
			if ($this->createIniFile('http.ini', 'HTTP Configuration (web admin)', array('http' => &$this->httpVars), $extraInfo))
				console('Generated config/http.ini');
		}

		// Setup http socket to listen on
		$this->close(false);
		
		if ($this->httpVars['ip'] != '' && $this->httpVars['port'] > 0)
		{
			$this->httpSock = @stream_socket_server('tcp://'.$this->httpVars['ip'].':'.$this->httpVars['port'], $httpErrNo, $httpErrStr);
			if (!is_resource($this->httpSock) || $this->httpSock === FALSE || $httpErrNo)
			{
				console('Error opening http socket : '.$httpErrStr.' ('.$httpErrNo.')');
			}
			else
			{
				console('Listening for http requests on '.$this->httpVars['ip'].':'.$this->httpVars['port']);
			}
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

	private $sendWindow		= STREAM_READ_BYTES;	// dynamic window size

	private $httpRequest	= null;
	
	public function __construct(HttpHandler &$http, &$sock, $ip, $port)
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
	
	public function hasHttpRequest()
	{
		return ($this->httpRequest) ? true : false;
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
			$this->sendWindow	+= STREAM_READ_BYTES;
		}
		
		$bytes = @fwrite($this->socket, fread($this->sendFile, $this->sendWindow));
		$this->sendFilePntr += $bytes;
		fseek($this->sendFile, $this->sendFilePntr);
		$this->lastActivity = time();
		
		// Dynamic window sizing
		if ($bytes == $this->sendWindow)
			$this->sendWindow += STREAM_READ_BYTES;
		else
		{
			$this->sendWindow -= STREAM_READ_BYTES;
			if ($this->sendWindow < STREAM_READ_BYTES)
				$this->sendWindow = STREAM_READ_BYTES;
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

	private function createErrorPage($errNo, $errStr = '')
	{
		$eol = "\r\n";
		$out = '<html>'.$eol;
		$out .= '<head><title>'.$errNo.' '.HttpResponse::$responseCodes[$errNo].'</title></head>'.$eol;
		$out .= '<body bgcolor="white">'.$eol;
		$out .= '<center><h1>'.$errNo.' '.(($errStr != '') ? $errStr : HttpResponse::$responseCodes[$errNo]).'</h1></center>'.$eol;
		$out .= '<hr><center>PRISM v'.PHPInSimMod::VERSION.'</center>'.$eol;
		$out .= '</body>'.$eol;
		$out .= '</html>'.$eol;
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
			// Send reply and return false to close this connection (unless 444, a special 'direct reject' code).
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
			}
			$errNo = $this->httpRequest->errNo;
			$this->httpRequest = null;
			return false;
		}
		
		// If we have no headers, or we are busy with receiving.
		// Just return and wait for more data.
		if (!$this->httpRequest->hasHeaders || 			// We're still receiving headers
			$this->httpRequest->isReceiving) 			// We're still receiving the body of a request
			return true;								// Return true to just wait and try again later
		
		// At this point we have a fully qualified and parsed HttpRequest
		// The HttpRequest object contains all info about the headers / GET / POST / COOKIE
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
		$this->httpRequest->SERVER['HTTP_KEEP_ALIVE']		= isset($this->httpRequest->headers['Keep-Alive']) ? $this->httpRequest->headers['Keep-Alive'] : '';
		if (isset($this->httpRequest->headers['Referer']))
			$this->httpRequest->SERVER['HTTP_REFERER']		= $this->httpRequest->headers['Referer'];
		if (isset($this->httpRequest->headers['Range']))
			$this->httpRequest->SERVER['HTTP_RANGE']		= $this->httpRequest->headers['Range'];
		
		//var_dump($this->httpRequest->headers);
		//var_dump($this->httpRequest->SERVER);
		//var_dump($this->httpRequest->GET);
		//var_dump($this->httpRequest->POST);
		//var_dump($this->httpRequest->COOKIE);
		
		// Rewrite script name? (keep it internal - don't rewrite SERVER header
		$scriptName = ($this->httpRequest->SERVER['SCRIPT_NAME'] == '/') ? '/index.php' : $this->httpRequest->SERVER['SCRIPT_NAME'];
		
		if (file_exists(ROOTPATH.'/www-docs'.$scriptName))
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
						$this->httpRequest->COOKIE
					);
		
					$r->addBody($html);
					
					$this->write($r->getHeaders());
					$this->write($r->getBody());
				}
			}
			else if (is_dir(ROOTPATH.'/www-docs'.$this->httpRequest->SERVER['SCRIPT_NAME']))
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
		$logLine =
			$this->ip.' - - ['.date('d/M/Y:H:i:s O').'] '.
			'"'.$this->httpRequest->SERVER['REQUEST_METHOD'].' '.$this->httpRequest->SERVER['REQUEST_URI'].' '.$this->httpRequest->SERVER['SERVER_PROTOCOL'].'" '.
			$r->getResponseCode().' '.
			(($r->getHeader('Content-Length')) ? $r->getHeader('Content-Length') : 0).' '.
			'"'.((isset($this->httpRequest->SERVER['HTTP_REFERER'])) ? $this->httpRequest->SERVER['HTTP_REFERER'] : '').'" '.
			'"'.$this->httpRequest->SERVER['HTTP_USER_AGENT'].'" '.
			'"-"';
		console($logLine);
		file_put_contents(ROOTPATH.'/logs/http.log', $logLine."\r\n", FILE_APPEND);
		
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
		$scriptnameHash = md5(ROOTPATH.'/www-docs'.$this->httpRequest->SERVER['SCRIPT_NAME']);
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
					$scriptMTime = filemtime(ROOTPATH.'/www-docs'.$this->httpRequest->SERVER['SCRIPT_NAME']);
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
						!isset($cacheControl['no-cache']) &&
						!isset($pragma['no-cache']) &&
						isset($this->http->cache[$scriptnameHash]))
			{
				$scriptMTime = filemtime(ROOTPATH.'/www-docs'.$this->httpRequest->SERVER['SCRIPT_NAME']);
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
			$scriptMTime = filemtime(ROOTPATH.'/www-docs'.$this->httpRequest->SERVER['SCRIPT_NAME']);

			$r->addHeader('Content-Type: '.$this->getMimeType());
			$r->addHeader('Last-Modified: '.date('r', $scriptMTime));
			
			if (isset($this->httpRequest->SERVER['HTTP_RANGE']))
			{
				console('HTTP_RANGE HEADER : '.$this->httpRequest->SERVER['HTTP_RANGE']);
			    $exp = explode('=', $this->httpRequest->SERVER['HTTP_RANGE']);
			    $startByte = (int) substr($exp[1], 0, -1);

				$r->addHeader('Content-Length: '.(filesize(ROOTPATH.'/www-docs'.$this->httpRequest->SERVER['SCRIPT_NAME']) - $startByte));
				$this->write($r->getHeaders());
				$this->writeFile(ROOTPATH.'/www-docs'.$this->httpRequest->SERVER['SCRIPT_NAME'], $startByte);
			}
			else
			{
				$r->addHeader('Content-Length: '.filesize(ROOTPATH.'/www-docs'.$this->httpRequest->SERVER['SCRIPT_NAME']));
				$this->write($r->getHeaders());
				$this->writeFile(ROOTPATH.'/www-docs'.$this->httpRequest->SERVER['SCRIPT_NAME']);
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
}

class HttpRequest
{
	private $rawInput		= '';
	
	public $isReceiving		= false;
	public $hasRequestUri	= false;
	public $hasHeaders		= false;

	public $errNo			= 0;
	public $errStr			= '';
	
	public $headers			= array();		// This will hold all of the request headers from the clients browser.

	public $SERVER			= array();
	public $GET				= array();		// With these arrays we try to recreate php's global vars a bit.
	public $POST			= array();
	public $COOKIE			= array();
	
	public function __construct()
	{

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
				return false;				// returns false is something went wrong (bad headers)
		}
		
		// If we have headers then we can now figure out if we have received all there is,
		// or if there is more to come. If there is, just return true and wait for more.
		if ($this->hasHeaders)
		{
			// With a GET there will be no extra data
			if ($this->SERVER['REQUEST_METHOD'] == 'POST')
			{
				// Check if we have enough and proper data to read the POST
				if (!isset($this->headers['Content-Length']))
				{
					$this->errNo = 411;
					return false;
				}
				if (!isset($this->headers['Content-Type']) || $this->headers['Content-Type'] != 'application/x-www-form-urlencoded')
				{
					$this->errNo = 415;
					$this->errStr = 'No Content-Type was provided that I can handle. At the moment I only like application/x-www-form-urlencoded.';
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
				$this->parsePOST(substr($this->rawInput, 0, $this->headers['Content-Length']));
				
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
			// pass the values of this object to the html generation / admin class.
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
						$this->errNo = 400;
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
					$this->errNo = 400;
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
	
	public function parseHeaderValue(&$header)
	{
//		image/png,
//		image/*;
//			q=
//				0.8,
//		*/*;q=0.5
		
		$parsed = array();
		
		// Split by ,
		$items = explode(',', $header);
		foreach ($items as $k => $item)
		{
			if (strpos($item, ';') !== false)
			{
				// Split by ;
				$exp2 = explode(';', $v);
				$parsed[$exp2[0]] = $exp2[1];
			}
			else if (strpos($item, '=') !== false)
			{
				// Split by =
				$exp2 = explode('=', $item);
				$parsed[$exp2[0]] = $exp2[1];
			}
			else
			{
				// Nothing to splut further
				$parsed[$item] = '';
			}
		}
		
		return $parsed;
	}
	
	private function parseRequestLine($line)
	{
		$exp = explode(' ', $line);
		if (count($exp) != 3)
			return false;
		
		// check the request command
		if ($exp[0] != 'GET' && $exp[0] != 'POST' && $exp[0] != 'HEAD')
			return false;
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
	
	private function parsePOST($raw)
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
			401 => 'Unauthorised',
			403 => 'Forbidden',
			404 => 'File Not Found',
			405 => 'Method Not Allowed',
			408 => 'Request Timeout',
			411 => 'Length Required',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			444 => 'Request Rejected',
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
		$this->responseCode = $code;
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
		
		// Store the header and reformat cases (cONtenT-TypE -> Content-Type)
		$this->headers[ucwordsByChar(strtolower($exp[0]), '-')] = $exp[1];
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