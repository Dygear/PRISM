<?php

namespace PRISM\Module\Http;

class Request
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
		foreach ($this->tmpFiles as $v) {
			unlink($v);
		}
	}

	public function handleInput(&$data)
	{
		// We need to buffer the input - no idea how much data will 
		// be coming in until we have received all the headers.
		// Normally though all headers should come in unfragmented, but don't rely on that.
		$this->rawInput .= $data;

		if (strlen($this->rawInput) > HTTP_MAX_REQUEST_SIZE) {
			$this->errNo = 413;
			$this->errStr = 'You tried to send more than '.HTTP_MAX_REQUEST_SIZE.' bytes to the server, which it doesn\'t like.';
			return false;
		}

		// Check if we have header lines in the buffer, for as long as !$this->hasHeaders
		if (!$this->hasHeaders) {
			if (!$this->parseHeaders()) {
				if ($this->errNo == 0) {
					$this->errNo = 400;
				}
                
				return false;				// returns false is something went wrong (bad headers)
			}
		}
		
		// If we have headers then we can now figure out if we have received all there is,
		// or if there is more to come. If there is, just return true and wait for more.
		if ($this->hasHeaders) {
			// With a GET there will be no extra data. With a POST however ...
			if ($this->SERVER['REQUEST_METHOD'] == 'POST') {
				// Check if we have enough and proper data to read the POST
				if (!isset($this->headers['Content-Length'])) {
					$this->errNo = 411;
					return false;
				}
                
				$contentType = isset($this->headers['Content-Type']) ? $this->parseContentType($this->headers['Content-Type']) : '';
				
                if (!$contentType || ($contentType['mediaType'] != 'application/x-www-form-urlencoded' && $contentType['mediaType'] != 'multipart/form-data')) {
					$this->errNo = 415;
					$this->errStr = 'No Content-Type was provided that I can handle. At the moment I only like application/x-www-form-urlencoded and multipart/form-data.';
					return false;
				}
				
				// Should we expect more data to come in?
				if ((int) $this->headers['Content-Length'] > strlen($this->rawInput)) {
					// We have not yet received all the POST data, so I'll return and wait.
					$this->isReceiving = true;
					return true;
				}
				
				// At this point we have the whole POST body
				$this->isReceiving = false;
				
				// Parse POST variables
				if ($contentType['mediaType'] == 'application/x-www-form-urlencoded') {
					$this->parsePOSTurlenc(substr($this->rawInput, 0, $this->headers['Content-Length']));
				} else if ($contentType['mediaType'] == 'multipart/form-data') {
					if (!$this->parsePOSTformdata($this->rawInput, $contentType['boundary'][1])) {
						$this->errNo = 400;
						$this->errStr = 'Bad Request - Problems parsing body data';
						return false;
					}
				}

				// Cleanup rawInput
				$this->rawInput = substr($this->rawInput, $this->headers['Content-Length']);
			} else {
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
		do {
			// Do we have a header line?
			$pos = strpos($this->rawInput, "\r\n");
            
			if ($pos === false) {
				// Extra (garbage) input error checking here
				if (!$this->hasRequestUri) {
					$len = strlen($this->rawInput);
                    
					if ($len > HTTP_MAX_URI_LENGTH) {
						$this->errNo = 414;
						return false;
					} else if ($len > 3 && !preg_match('/^(GET|POST|HEAD).*$/', $this->rawInput)) {
						$this->errNo = 444;
						return false;
					}
				}
				
				// Otherwise just return and wait for more data
				return true;
			} else if ($pos === 0) {
				// This cannot possibly be the end of headers, if we don't even have a request uri (or host header)
				if (!$this->hasRequestUri || !isset($this->headers['Host'])) {
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
			if (!$this->hasRequestUri) {
				// Read the first header (the request line)
				if (!$this->parseRequestLine($header)) {
					if ($this->errNo == 0) {
						$this->errNo = 400;
					}
                    
					return false;
				}
                
				$this->hasRequestUri = true;
			} else if (!$this->hasHeaders) {
				if (strpos($header, ':') === false) {
					$this->errNo = 400;
					return false;
				}
				
				// Parse regular header line
				$exp = explode(':', $header, 2);
                
				if (count($exp) == 2) {
					$this->headers[trim($exp[0])] = trim($exp[1]);
				}
			}
		} while (true);
        
		return true;
	}
	
	private function parseSubHeaders(&$headers)
	{
		$parsed = array();
		
		// Split header lines
		$lines = explode("\r\n", $headers);
		
		foreach ($lines as $header) {
			$exp = explode(':', $header, 2);
            
			if (count($exp) == 2) {
				$parsed[trim($exp[0])] = $this->parseHeaderValue(trim($exp[1]));
			}
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
		switch ($level) {
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
		
		if ($level == 2) {
			if (count($items) == 1) {
				return $header;
			} else {
				return array(trim($items[0]) => $items[1]);
			}
		}
        
		if (count($items) == 1) {
			return $this->parseHeaderValue($header, $level + 1);
		}

		$parsed = array();
		
		foreach ($items as $k => $v) {
			$parsed[$k] = $this->parseHeaderValue($v, $level + 1);
		}
		
		return $parsed;
	}

	public function parseContentType(&$header)
	{
		if ($header == '') {
			return false;
		}
		
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
		if (count($exp) != 3) {
			$this->errNo = 444;
			return false;
		}
		
		// check the request command
		if ($exp[0] != 'GET' && $exp[0] != 'POST' && $exp[0] != 'HEAD') {
			$this->errNo = 444;
			return false;
		}
        
		$this->SERVER['REQUEST_METHOD'] = $exp[0];
		
		// Check the request uri
		$this->SERVER['REQUEST_URI'] = $exp[1];
        
		if (($uri = parse_url($this->SERVER['REQUEST_URI'])) === false) {
			return false;
		}
			
		// Path sanitation
		$uri['path'] = filter_var(trim($uri['path']), FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
        
		if (!isset($uri['path'][0]) || $uri['path'][0] != '/') {
			return false;
		}
        
		$this->SERVER['SCRIPT_NAME'] = $uri['path'];
		
		// Set the query string - all chars allowed in there
		$this->SERVER['QUERY_STRING'] = isset($uri['query']) ? $uri['query'] : '';
		
		// Check for user trying to go below webroot
		$exp2 = explode('/', $this->SERVER['SCRIPT_NAME']);
        
		foreach ($exp2 as $v) {
			if (trim($v) == '..') {
				// Ooops the user probably tried something nasty (reach a file outside of our www folder)
				return false;
			}
		}
		
		// Check the HTTP protocol version
		$this->SERVER['SERVER_PROTOCOL'] = $exp[2];
		$httpexp = explode('/', $exp[2]);
        
		if ($httpexp[0] != 'HTTP' || ($httpexp[1] != '1.0' && $httpexp[1] != '1.1')) {
			return false;
		}
            
		$this->SERVER['httpVersion'] = $httpexp[1];

		return true;
	}
	
	private function parseGET()
	{
		$exp = explode('&', $this->SERVER['QUERY_STRING']);
        
		foreach ($exp as $v) {
			if ($v == '') {
				continue;
			}
            
			$exp2 = explode('=', $v, 2);
			$this->GET[urldecode($exp2[0])] = isset($exp2[1]) ? urldecode($exp2[1]) : '';
		}
	}
	
	private function parsePOSTurlenc($raw)
	{
		$exp = explode('&', $raw);
        
		foreach ($exp as $v) {
			$exp2 = explode('=', $v);
			$key = urldecode($exp2[0]);
			$value = urldecode($exp2[1]);
			
			if (preg_match('/^(.*)\[(.*)\]$/', $key, $matches)) {
				if (!isset($this->POST[$matches[1]])) {
					$this->POST[$matches[1]] = array();
				}

				if ($matches[2] == '') {
					$this->POST[$matches[1]][] = $value;
				} else {
					$this->POST[$matches[1]][$matches[2]] = $value;
				}
			} else {
				$this->POST[$key] = $value;
			}
		}
	}
	
	private function parsePOSTformdata($raw, $boundary)
	{
		// Check if the raw data at least begins and ends with the boundary
		$bLen = strlen($boundary);
        
		if (substr($raw, 0, ($bLen + 2)) != '--'.$boundary || trim(substr($raw, -($bLen + 2))) != substr($boundary, 2).'--') {
			return false;
		}

		// Split into separate parts
		$parts = explode('--'.$boundary, $raw);
		
		// Always remove the first and last entries, as they are bogus
		array_shift($parts);
		array_pop($parts);
		
		foreach ($parts as $part) {
			// Split part headers & data
			$exp = explode("\r\n\r\n", substr($part, 2, -2), 2);
			$headers = $this->parseSubHeaders($exp[0]);

			$key = preg_replace('/^"(.*)"$/', '\\1', $headers['Content-Disposition'][1]['name']);
			$value = $exp[1];

			$contentType = '';
            
			if (isset($headers['Content-Disposition'][2]['filename'])) {
				$fileName = preg_replace('/^"(.*)"$/', '\\1', $headers['Content-Disposition'][2]['filename']);
			}
            
			if (isset($fileName) && isset($headers['Content-Type'])) {
				$contentType = $headers['Content-Type'];
			}
			
			if (isset($fileName)) {
				if (!$fileName) {
					continue;
				}
				
				$fileError = UPLOAD_ERR_OK;
				
				// Store the uploaded file in a temp place
				$tmpFileName = tempnam(sys_get_temp_dir(), 'Prism');
                
				if (!@file_put_contents($tmpFileName, $value)) {
					$fileError = UPLOAD_ERR_CANT_WRITE;
				} else {
					$this->tmpFiles[] = $tmpFileName;
				}
				
				// Fill $FILES with details on the file
				if (preg_match('/^(.*)\[(.*)\]$/', $key, $matches)) {
					// Create entry array if not yet exists
					if (!isset($this->FILES[$matches[1]])) {
						$this->FILES[$matches[1]] = array(
							'name'		=> array(),
							'tmp_name'	=> array(),
							'type'		=> array(),
							'size'		=> array(),
							'error'		=> array(),
						);
					}
					
					// Fill in the values
					if ($matches[2] == '') {
						$this->FILES[$matches[1]]['name'][]		= $fileName;
						$this->FILES[$matches[1]]['tmp_name'][]	= $tmpFileName;
						$this->FILES[$matches[1]]['type'][]		= $contentType;
						$this->FILES[$matches[1]]['size'][]		= strlen($value);
						$this->FILES[$matches[1]]['error'][]	= $fileError;
					} else {
						$this->FILES[$matches[1]]['name'][$matches[2]]		= $fileName;
						$this->FILES[$matches[1]]['tmp_name'][$matches[2]]	= $tmpFileName;
						$this->FILES[$matches[1]]['type'][$matches[2]]		= $contentType;
						$this->FILES[$matches[1]]['size'][$matches[2]]		= strlen($value);
						$this->FILES[$matches[1]]['error'][$matches[2]]		= $fileError;
					}
				} else {
					$this->FILES[$key] = array(
						'name'		=> $fileName,
						'tmp_name'	=> $tmpFileName,
						'type'		=> $contentType,
						'size'		=> strlen($value),
						'error'		=> $fileError,
					);
				}
			} else {
				if (preg_match('/^(.*)\[(.*)\]$/', $key, $matches)) {
					if (!isset($this->POST[$matches[1]])) {
						$this->POST[$matches[1]] = array();
					}
	
					if ($matches[2] == '') {
						$this->POST[$matches[1]][] = $value;
					} else {
						$this->POST[$matches[1]][$matches[2]] = $value;
					}
				} else {
					$this->POST[$key] = $value;
				}
			}
		}
		
		//var_dump($this->POST);
		return true;
	}
	
	private function parseCOOKIE()
	{
		if (!isset($this->headers['Cookie'])) {
			return;
		}
		
		$exp = explode(';', $this->headers['Cookie']);
        
		foreach ($exp as $v) {
			$exp2 = explode('=', $v);
			$this->COOKIE[urldecode(ltrim($exp2[0]))] = urldecode($exp2[1]);
		}
	}
}