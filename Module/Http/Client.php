<?php

namespace PRISM\Module\Http;

class Client
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
		if (is_resource($this->socket)) {
			fclose($this->socket);
		}
		
		if ($this->sendFile) {
			$this->writeFileReset();
		}
        
		if ($this->sendQLen > 0) {
			$this->sendQReset();
		}
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
        
		if ($dataLen == 0) {
			return 0;
		}
		
		if (!is_resource($this->socket)) {
			return $bytes;
		}
	
		if ($sendQPacket == TRUE) {
			// This packet came from the sendQ. We just try to send this and don't bother too much about error checking.
			// That's done from the sendQ flushing code.
			$bytes = @fwrite($this->socket, $data);
		} else {
			if ($this->sendQLen == 0) {
				// It's Ok to send packet
				$bytes = @fwrite($this->socket, $data);
				$this->lastActivity = time();
		
				if (!$bytes || $bytes != $dataLen) {
					// Could not send everything in one go - send the remainder to sendQ
					$this->addPacketToSendQ (substr($data, $bytes));
				}
			} else {
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
		} else if ($bytes > 0) {
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
		if ($fileName != '' && $this->sendFile == null) {
			$this->sendFile = fopen($fileName, 'rb');
            
			if (!$this->sendFile) {
				return false;
			}
            
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
		if ($bytes == $this->sendWindow) {
			$this->sendWindow += STREAM_WRITE_BYTES;
		} else {
			$this->sendWindow -= STREAM_WRITE_BYTES;
            
			if ($this->sendWindow < STREAM_WRITE_BYTES) {
				$this->sendWindow = STREAM_WRITE_BYTES;
			}
		}
		
		//console('BYTES : '.$bytes.' - PNTR : '.$this->sendFilePntr);
		// Done?
		if ($this->sendFilePntr >= $this->sendFileSize) {
			$this->writeFileReset();
		}
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
		
		if ($appendPadding) {
			for ($a=0; $a<6; $a++) {
				$out .= '<!-- a padding to disable MSIE and Chrome friendly error page -->'.$eol;
			}
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
		if ($this->sendFile) {
			$this->writeFileReset();
			$this->httpRequest = null;
		} else if ($this->sendQLen > 0) {
			$this->sendQReset();
			$this->httpRequest = null;
		}

		if (!$this->httpRequest) {
			$this->httpRequest = new HttpRequest();
		}

		// Pass the incoming data to the HttpRequest class, so it can handle it.		
		if (!$this->httpRequest->handleInput($data)) {
			// An error was encountered while receiving the requst.
			// Send reply (unless 444, a special 'direct reject' code) and return false to close this connection.
			if ($this->httpRequest->errNo != 444) {
				$r = new HttpResponse('1.1', $this->httpRequest->errNo);
				$r->addBody($this->createErrorPage($this->httpRequest->errNo, $this->httpRequest->errStr));
				
				if ($this->httpRequest->errNo == 405) {
					$r->addHeader('Allow: GET, POST, HEAD');
					$r->addHeader('Access-Control-Allow-Methods: GET, POST, HEAD');
				}
					
				$this->write($r->getHeaders());
				$this->write($r->getBody());

				$this->logRequest($r->getResponseCode(), (($r->getHeader('Content-Length')) ? $r->getHeader('Content-Length') : 0));
			} else {
				$this->logRequest(444, 0);
			}
            
			$errNo = $this->httpRequest->errNo;
			return false;
		}
		
		// If we have no headers, or we are busy with receiving.
		// Just return and wait for more data.
        # We're still receiving headers or the body of a request
		if (!$this->httpRequest->hasHeaders || $this->httpRequest->isReceiving) {
			return true;								// Return true to just wait and try again later
		}
		
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
		
        if (isset($this->httpRequest->headers['Referer'])) {
			$this->httpRequest->SERVER['HTTP_REFERER']		= $this->httpRequest->headers['Referer'];
		}
        
		if (isset($this->httpRequest->headers['Range'])) {
			$this->httpRequest->SERVER['HTTP_RANGE']		= $this->httpRequest->headers['Range'];
		}
        
		if (isset($this->httpRequest->headers['Cookie'])) {
			$this->httpRequest->SERVER['HTTP_COOKIE']= $this->httpRequest->headers['Cookie'];
		}
        
		if (isset($this->httpRequest->headers['Authorization'])) {
			$this->httpRequest->SERVER['HTTP_AUTHORIZATION']= $this->httpRequest->headers['Authorization'];
		}
        
		$this->httpRequest->SERVER['REQUEST_TIME']			= time();
		
		// Check if we have to match siteDomain
		if ($this->http->getSiteDomain() != '' && $this->http->getSiteDomain() != $this->httpRequest->SERVER['SERVER_NAME']) {
			$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 404);
			$r->addBody($this->createErrorPage(404));
			$this->write($r->getHeaders());
			$this->write($r->getBody());
			$errNo = 404;
			$this->logRequest($r->getResponseCode(), (($r->getHeader('Content-Length')) ? $r->getHeader('Content-Length') : 0));
			return false;
		}
		
		// HTTP Authorisation?
		if ($this->http->getHttpAuthPath() != '') {
			$scriptPath = pathinfo($this->httpRequest->SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
			
			// Check if path must be auth'd and if HTTP_AUTHORIZATION header exists and if so, validate it
			if (isDirInDir($this->http->getHttpAuthPath(), $this->http->getDocRoot().$scriptPath) && (!isset($this->httpRequest->SERVER['HTTP_AUTHORIZATION']) || !$this->validateAuthorization())) {
				// Not validated - send 401 Unauthorized
				do {
					$nonce = createRandomString(17, RAND_HEX);
                    
					if (!$this->http->getNonceInfo($nonce)) {
						break;
					}
				} while(true);
                
				$opaque = $this->http->addNewNonce($nonce);
				
				$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 401);
                
				if ($this->http->getHttpAuthType() == 'Digest') {
					$r->addHeader('WWW-Authenticate: Digest realm="'.HTTP_AUTH_REALM.'", qop="auth", nonce="'.$nonce.'", opaque="'.$opaque.'"');
				} else {
					$r->addHeader('WWW-Authenticate: Basic realm="'.HTTP_AUTH_REALM.'"');
				}
                
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
		
		if (file_exists($this->http->getDocRoot().$scriptName)) {
			// Should we serve a file or pass the request to PHPParser for page generation?
			if (preg_match('/^.*\.php$/', $scriptName)) {
				if ($this->httpRequest->SERVER['REQUEST_METHOD'] == 'HEAD') {
					$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 200);
					$this->write($r->getHeaders());
				} else {
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
			} else if (is_dir($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME'])) {
				// 403 - not allowed to view folder contents
				$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 403);
				$r->addBody($this->createErrorPage(403));
	
				$this->write($r->getHeaders());
				$this->write($r->getBody());
			} else {
				// Send a file
				if ($this->httpRequest->SERVER['REQUEST_METHOD'] == 'HEAD') {
					$r = new HttpResponse($this->httpRequest->SERVER['httpVersion'], 200);
					$this->write($r->getHeaders());
				} else {
					$r = $this->serveFile();
				}
			}
		} else {
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
        
		if (isset($this->httpRequest->headers['Cache-Control']) || isset($this->httpRequest->headers['Pragma'])) {
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
			if ($ifModifiedSince > 0) {
				if (isset($this->http->cache[$scriptnameHash])) {
					if ($this->http->cache[$scriptnameHash] == $ifModifiedSince) {
						// File has not been changed - tell the browser to use the cache (send a 304)
						$useCache = true;
					}
				} else {
					$scriptMTime = filemtime($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']);
					$this->http->cache[$scriptnameHash] = $scriptMTime;
                    
					if ($scriptMTime == $ifModifiedSince) {
						// File has not been changed - tell the browser to use the cache (send a 304)
						$useCache = true;
					}
				}
                # Otherwise detect 'Cache-Control' or 'Pragma' (strong) validators (http1.0/http1.1)
			} else if ((isset($cacheControl['max-age']) && $cacheControl['max-age'] == 0) && $cacheControl != 'no-cache' && $pragma != 'no-cache' && isset($this->http->cache[$scriptnameHash])) {
				$scriptMTime = filemtime($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']);
                
				if ($this->http->cache[$scriptnameHash] == $scriptMTime) {
					// File has not been changed - tell the browser to use the cache (send a 304)
					$useCache = true;
				} else {
					// File has been updated - store new mtime in cache
					$this->http->cache[$scriptnameHash] = $scriptMTime;
				}
                
				clearstatcache();
			}
		}

		if ($useCache) {				
			$r->setResponseCode(304);
			$this->write($r->getHeaders());
		} else {
			$scriptMTime = filemtime($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']);

			$r->addHeader('Content-Type: '.$this->getMimeType());
			$r->addHeader('Last-Modified: '.date('r', $scriptMTime));
			
			if (isset($this->httpRequest->SERVER['HTTP_RANGE'])) {
				console('HTTP_RANGE HEADER : '.$this->httpRequest->SERVER['HTTP_RANGE']);
			    $exp = explode('=', $this->httpRequest->SERVER['HTTP_RANGE']);
			    $startByte = (int) substr($exp[1], 0, -1);

				$r->addHeader('Content-Length: '.(filesize($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']) - $startByte));
				$this->write($r->getHeaders());
				$this->writeFile($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME'], $startByte);
			} else {
				$r->addHeader('Content-Length: '.filesize($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']));
				$this->write($r->getHeaders());
				$this->writeFile($this->http->getDocRoot().$this->httpRequest->SERVER['SCRIPT_NAME']);
			}
			
			// Store the filemtime in $cache
			if (!isset($this->http->cache[$scriptnameHash])) {
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
		if (preg_match('/^Digest (.*)$/', $this->httpRequest->SERVER['HTTP_AUTHORIZATION'], $matches)) {
			// Digest method
			$info = array();
			$infoTmp = $this->httpRequest->parseHeaderValue($matches[1]);
			foreach ($infoTmp as $a => $b) {
				foreach ($b as $k => $v) {
					$info[$k] = preg_replace('/"?(.*)"?/U', '\\1', $v);
				}
			}
			
			// Check that all values are provided
			if (!isset($info['username']) || !isset($info['realm']) || !isset($info['nonce']) || !isset($info['uri']) || !isset($info['response']) || !isset($info['opaque']) || !isset($info['qop']) ||	!isset($info['nc']) || !isset($info['cnonce'])) {
                return false;
			}

			//  Check that nonce exists and nc is not reused AND that opaque value matches
			if (!($nonceInfo = $this->http->getNonceInfo($info['nonce']))) {
				return false;
			}
            
			if ($nonceInfo[1] >= $info['nc']) {
				return false;
			}
            
			if (!isset($nonceInfo[2])) {
				return false;
			}
            
			if ($nonceInfo[2] != $info['opaque']) {
				return false;
			}
						
			// Do the digest check
			if (!($ha1 = $PRISM->admins->getRealmDigest($info['username']))) {
				return false;
			}
            
			$ha2 = md5($this->httpRequest->SERVER['REQUEST_METHOD'].':'.$info['uri']);
			$response = md5($ha1.':'.$info['nonce'].':'.$info['nc'].':'.$info['cnonce'].':'.$info['qop'].':'.$ha2);
			
            if ($response != $info['response']) {
				return false;
            }
			
			// Validated!
			$this->http->incNonceCounter($info['nonce'], $info['nc']);
			$this->httpRequest->SERVER['PHP_AUTH_USER']	= $info['username'];
		} else if (preg_match('/^Basic (.*)$/', $this->httpRequest->SERVER['HTTP_AUTHORIZATION'], $matches)) {
			// Basic method
			$auth = explode(':', base64_decode($matches[1]), 2);
            
			if (count($auth) != 2 || !$PRISM->admins->isPasswordCorrect($auth[0], $auth[1])) {
				return false;
			}
			
			// Validated!
			$this->httpRequest->SERVER['PHP_AUTH_USER']	= $auth[0];
			$this->httpRequest->SERVER['PHP_AUTH_PW']	= $auth[1];
		} else {
			// Unknown or no authorisation provided
			return false;
		}

		return true;
	}
	
	private function getMimeType()
	{
		$pathInfo = pathinfo($this->httpRequest->SERVER['SCRIPT_NAME']);
		
		$mimeType = 'application/octet-stream';
        
		switch(strtolower($pathInfo['extension'])) {
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
		if ($this->http->getLogFile() == '') {
			return;
		}
		
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