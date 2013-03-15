<?php

namespace PRISM\Module\Http;

class Response
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
        
		if (count($exp) != 2) {
			return false;
		}
		
		$exp[0] = trim($exp[0]);
		$exp[1] = trim($exp[1]);
        
		// Check for duplicate (can't do that the easy way because i want to do a case insensitive check)
		foreach ($this->headers as $k => $v) {
			if (strtolower($exp[0]) == strtolower($k)) {
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
        
		foreach ($this->headers as $k => $v) {
			$headers .= $k.': '.$v."\r\n";
		}

		foreach ($this->cookies as $k => $v) {
			$headers .= 'Set-Cookie: '.urlencode($k).'='.urlencode($v[0]).'; expires='.date('l, d-M-y H:i:s T', (int) $v[1]).'; path='.$v[2].'; domain='.$v[3].(($v[4]) ? '; secure' : '')."\r\n";
		}

		return $headers."\r\n";
	}
	
	private function finaliseHeaders()
	{
		// Adjust the response code for a redirect?
		if (isset($this->headers['Location'])) {
			$this->responseCode = 302;
		}

		// Set server-side headers
		$this->headers['Date']					= date('r');
		$this->headers['Accept-Ranges']			= 'bytes';
		
		if (!isset($this->headers['Content-Length']) && $this->responseCode != 304) {
			$this->headers['Content-Length']	= $this->bodyLen;
		}
		
		if ($this->responseCode == 200 || $this->responseCode == 302 || $this->responseCode == 404) {
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
        
		if ($path[0] != '/') {
			$path = '/'.$path;
		}
		
		$this->cookies[$name] = array($value, $expire, $path, $domain, $secure, $httponly);
	}
}
