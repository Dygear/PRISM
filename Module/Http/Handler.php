<?php

namespace PRISM\Module;

use PRISM\Module\SectionHandler;
use PRISM\Module\PhpParser;

define('HTTP_AUTH_REALM', 'Prism administration');	// Token used for http auth & digest
define('HTTP_KEEP_ALIVE', 10);						// Keep-alive timeout in seconds
define('HTTP_MAX_REQUEST_SIZE', 2097152);			// Max http request size in bytes (headers + data)
define('HTTP_MAX_URI_LENGTH', 4096);				// Max length of the uri in the first http header
define('HTTP_MAX_CONN', 1024);						// Max number of simultaneous http connections
													// Experimentation showed it's best to keep this pretty high.
													// FD_SETSIZE is usually 1024; the max connections allowed on a socket.

class Handler extends \PRISM\Module\SectionHandler; # I may be doing this wrong... - zenware
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
		foreach ($this->httpClients as $k => $v) {
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
		if (!isset($this->nonceCache[$nonce])) {
			return false;
		}
		
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
		if (!isset($this->nonceCache[$nonce])) {
			return false;
		}
        
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
		if (is_resource($this->httpSock)) {
			fclose($this->httpSock);
		}
        
		if (!$all) {
			return;
		}
		
		for ($k=0; $k<$this->httpNumClients; $k++) {
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
		
		if ($this->loadIniFile($this->httpVars, false)) {
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE) {
				console('Loaded '.$this->iniFile);
			}
		} else {
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
			if ($this->createIniFile('HTTP Configuration (web admin)', array('http' => &$this->httpVars), $extraInfo)) {
				console('Generated config/'.$this->iniFile);
			}
		}

		// Set docRoot
		if (!$this->setDocRoot()) {
			return false;
		}
		
		// Set logFile
		if (!$this->setLogFile()) {
			return false;
		}
		
		// Setup http socket to listen on
		if (!$this->setupListenSocket()) {
			return false;
		}
        
		// Setup site domain
		$this->setupSiteDomain();
		
		// Validate httpAuthPath
		if (!$this->validateAuthPath()){
			return false;
		}
		
		// Validate httpAuthType
		if ($this->httpVars['httpAuthType'] != 'Digest' & $this->httpVars['httpAuthType'] != 'Basic') {
			console('Invalid httpAuthType in '.$this->iniFile);
			return false;
		}
		
		return true;
	}
	
	private function setupListenSocket()
	{
		$this->close(false);
		
		if ($this->httpVars['ip'] != '' && $this->httpVars['port'] > 0) {
			$this->httpSock = @stream_socket_server('tcp://'.$this->httpVars['ip'].':'.$this->httpVars['port'], $httpErrNo, $httpErrStr);
            
			if (!is_resource($this->httpSock) || $this->httpSock === FALSE || $httpErrNo) {
				console('Error opening http socket : '.$httpErrStr.' ('.$httpErrNo.')');
				return false;
			} else {
				console('Listening for http requests on '.$this->httpVars['ip'].':'.$this->httpVars['port']);
			}
		}
        
		return true;
	}
	
	private function setDocRoot()
	{
		// Strip trailing slashes
		$this->httpVars['path'] = preg_replace('/(.*)([\/\\\]*)$/U', '\\1', $this->httpVars['path']);
		
		if ($this->httpVars['path'] == '') {
			$this->httpVars['path'] = 'www-docs';
		}
		
		// Store in docRoot
		$this->docRoot = 
			($this->httpVars['path'][0] == '/' || (isset($this->httpVars['path'][1]) && $this->httpVars['path'][1] == ':')) ? 
			$this->httpVars['path'] : 
			ROOTPATH.'/'.$this->httpVars['path'];
		
		// Check if it's valid
		if (!file_exists($this->docRoot)) {
			console('The path to your web-root does not exist : '.$this->httpVars['path']);
			return false;
		}
		
		return true;
	}

	private function setLogFile()
	{
		// Strip trailing slashes
		$this->httpVars['logFile'] = preg_replace('/(.*)([\/\\\]*)$/U', '\\1', $this->httpVars['logFile']);
		
		if ($this->httpVars['logFile'] == '') {
			$this->httpVars['logFile'] = 'logs/http.log';
		}
		
		// Store in logFile
		$this->logFile = 
			($this->httpVars['logFile'][0] == '/' || (isset($this->httpVars['logFile'][1]) && $this->httpVars['logFile'][1] == ':')) ? 
			$this->httpVars['logFile'] : 
			ROOTPATH.'/'.$this->httpVars['logFile'];
		
		// Check if its path is valid
		$logPath = pathinfo($this->logFile);
        
		if (!isset($logPath['filename']) || $logPath['filename'] == '' || !file_exists($logPath['dirname'])) {
			console('The path to your log folder does not exist : '.$logPath);
			return false;
		} else if (is_dir($this->logFile)) {
			console('The path to your http log folder is a folder itself : '.$this->logFile);
			return false;
		}
		
		return true;
	}

	private function setupSiteDomain()
	{
		$this->siteDomain = '';
		
		// Ignore site domain? (accept any incoming request, no matter what host the request contains)
		if ($this->httpVars['siteDomain'] == '') {
			return;
		}
        
		if (!getIP($this->httpVars['siteDomain'])) {
			console('Invalid siteDomain provided in '.$this->iniFile.' (it does not resolve). Ignoring this setting.');
			return;
		}
		
		$this->siteDomain = $this->httpVars['siteDomain'];
	}
	
    # What the fuck? Should this be IF/ELIF or SWITCH/CASE?
	private function validateAuthPath()
	{
		if ($this->httpVars['httpAuthPath'] == '') {
			return true;
		}
        
		if ($this->httpVars['httpAuthPath'] == '/') {
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
		if (!file_exists($this->httpVars['httpAuthPath'])) {
			console('httpAuthPath path does not exist : '.$this->httpVars['httpAuthPath']);
			return false;
		}
		
		return true;
	}
	
	public function getSelectableSockets(array &$sockReads, array &$sockWrites)
	{
		// Add http sockets to sockReads
		if (is_resource($this->httpSock)) {
			$sockReads[] = $this->httpSock;
		}

		for ($k=0; $k<$this->httpNumClients; $k++) {
			if (is_resource($this->httpClients[$k]->getSocket())) {
				$sockReads[] = $this->httpClients[$k]->getSocket();
				
				// If write buffer was full, we must check to see when we can write again
				if ($this->httpClients[$k]->getSendQLen() > 0 || $this->httpClients[$k]->getSendFilePntr() > -1) {
					$sockWrites[] = $this->httpClients[$k]->getSocket();
				}
			}
		}
	}

	public function checkTraffic(array &$sockReads, array &$sockWrites)
	{
		$activity = 0;

		// httpSock input (incoming http connection)
		if (in_array ($this->httpSock, $sockReads)) {
			$activity++;
			
			// Accept the new connection
			$peerInfo = '';
			$sock = @stream_socket_accept ($this->httpSock, NULL, $peerInfo);
            
			if (is_resource($sock)) {
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
			if (($this->httpClients[$k]->getSendQLen() > 0  || $this->httpClients[$k]->getSendFilePntr() > -1) && in_array($this->httpClients[$k]->getSocket(), $sockWrites)) {
				$activity++;
				
				// Flush the sendQ (bit by bit, not all at once - that could block the whole app)
				if ($this->httpClients[$k]->getSendQLen() > 0) {
					$this->httpClients[$k]->flushSendQ();
				} else {
					$this->httpClients[$k]->writeFile();
				}
			}
			
			// Did we receive something from a httpClient?
			if (!in_array($this->httpClients[$k]->getSocket(), $sockReads)) {
				continue;
			}

			$activity++;
			
			$data = $this->httpClients[$k]->read();
			
			// Did the client hang up?
			if ($data == '') {
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
		for ($k=0; $k<$this->httpNumClients; $k++) {
			if ($this->httpClients[$k]->getLastActivity() < time() - HTTP_KEEP_ALIVE) {
				console('Closed httpClient (keep alive) '.$this->httpClients[$k]->getRemoteIP().':'.$this->httpClients[$k]->getRemotePort());
				array_splice ($this->httpClients, $k, 1);
				$k--;
				$this->httpNumClients--;
			}
		}
	}
}
