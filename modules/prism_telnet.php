<?php

define('TELNET_NOT_LOGGED_IN', 0);
define('TELNET_ASKED_USERNAME', 1);
define('TELNET_ASKED_PASSWORD', 2);
define('TELNET_LOGGED_IN', 3);

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

	public function __destruct()
	{
		$this->close(true);
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
				//stream_set_blocking ($sock, 0);
				
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

			$this->clients[$k]->addInputToBuffer($data);

			// Handle login / input
			$result = $this->clients[$k]->processInput();
			if ($result === false)
			{
				if ($this->clients[$k]->getMustClose())
				{
					console('Closed telnet client (client ctrl-c) '.$this->clients[$k]->getRemoteIP().':'.$this->clients[$k]->getRemotePort());
					array_splice ($this->clients, $k, 1);
					$k--;
					$this->numClients--;
				}
			}
		}
		
		return $activity;
	}
}

require_once(ROOTPATH . '/modules/prism_telnet_defines.php');

class TelnetClient
{
	private $socket			= null;
	private $ip				= '';
	private $port			= 0;
	
	private $lineBuffer		= array();
	private $lineBufferPtr	= 0;
	private $inputBuffer	= '';
	private $inputBufferLen	= 0;
	
	// send queue used for backlog, in case we can't send a reply in one go
	private $sendQ			= '';
	private $sendQLen		= 0;

	private $sendWindow		= STREAM_WRITE_BYTES;	// dynamic window size
	
	private $lastActivity	= 0;
	private $mustClose		= false;
	
	// If filled in, the user is logged in (or half-way logging in).
	private $username		= '';
	
	// We need these so we know the state of the login process.
	private $loginState		= 0;
	
	// Editing related
	private $modeState		= 0;
	private $winSize		= array();
	private $inputCallback	= null;
	
	private $charMap		= array();
	private $term			= '';
	
	public function __construct(&$sock, &$ip, &$port)
	{
		$this->socket		= $sock;
		$this->ip			= $ip;
		$this->port			= $port;
		
		$this->lastActivity	= time();
		
		$this->setOption(TELNET_ACTION_DO, TELNET_OPT_BINARY);
		$this->setOption(TELNET_ACTION_WILL, TELNET_OPT_ECHO);
		$this->setOption(TELNET_ACTION_DO, TELNET_OPT_SGA);
		$this->setOption(TELNET_ACTION_DO, TELNET_OPT_LINEMODE);
		$this->setOption(TELNET_ACTION_DO, TELNET_OPT_NAWS);
		$this->setOption(TELNET_ACTION_DO, TELNET_OPT_TTYPE);
		
		// Set terminal state and clear screen
		$msg = VT100_ED2.VT100_CURSORHOME;
		
		// Send welcome message and ask for username
		$msg .= "Welcome to the ".VT100_STYLE_BOLD."Prism v".PHPInSimMod::VERSION.VT100_STYLE_RESET." remote console.\r\n";
		$msg .= "Please login with your Prism account details.\r\n";

//		$msg .= VT100_SGR0.VT100_USG1_LINE;
//		for ($a=106; $a<110; $a++)
//			$msg .= chr($a);
//		$msg .= VT100_SGR0.VT100_USG0;

		$msg .= "\r\n";
		$msg .= "Username : ";
		
		$this->write($msg);
		$this->loginState = TELNET_ASKED_USERNAME;
		
		$this->modeState |= TELNET_MODE_INSERT;

		$this->registerInputCallback($this, 'doLogin', TELNET_MODE_LINEEDIT);
	}
	
	public function __destruct()
	{
		if ($this->sendQLen > 0)
			$this->sendQReset();

		if (is_resource($this->socket))
		{
//			$this->write(VT100_SGR0.VT100_USG0);
			fclose($this->socket);
			
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
	
	public function setOption($action, $option)
	{
		$this->write(TELNET_IAC.$action.$option);
	}
	
	public function getLoginState()
	{
		return $this->loginState;
	}
	
	public function getMustClose()
	{
		return $this->mustClose;
	}
	
	private function doLogin($line)
	{
//		$line = $this->getLine();
//		if ($line === false)
//			return;
		
		switch($this->getLoginState())
		{
			case TELNET_NOT_LOGGED_IN :
				// Send error notice and ask for username
				$msg .= "Please login with your Prism account details.\r\n";
				$msg .= "Username : ";
				
				$this->write($msg);
				$this->loginState = TELNET_ASKED_USERNAME;
				
				break;
			
			case TELNET_ASKED_USERNAME :
				if ($line == '')
				{
					$this->write('Username : ');
					break;
				}
				$this->username = $line;
				$this->write("Password : ");
				$this->loginState = TELNET_ASKED_PASSWORD;
				
				break;
			
			case TELNET_ASKED_PASSWORD :
				if ($this->verifyLogin($line))
				{
					$this->loginState = TELNET_LOGGED_IN;
					$this->write("Login successful\r\n");
					$this->write("(nothing works so far from now on. ctrl-c to exit)\r\n");
					console('Successful telnet login from '.$this->username.' on '.date('r'));
					
					// Unregister doLogin as callback
					$this->registerInputCallback(null, null);
					
					// Now setup the screen
				}
				else
				{
					$msg = "Incorrect login. Please try again.\r\n";
					$msg .= "Username : ";
					$this->username = '';
					$this->write($msg);
					$this->loginState = TELNET_ASKED_USERNAME;
				}
				break;
		}
	}
	
	private function verifyLogin(&$password)
	{
		global $PRISM;

		return ($PRISM->admins->isPasswordCorrect($this->username, $password));
	}
	
	public function read(&$data)
	{
		$this->lastActivity	= time();
		return fread($this->socket, STREAM_READ_BYTES);
	}
	
	public function addInputToBuffer(&$raw)
	{
//		for ($a=0; $a<strlen($raw); $a++)
//			printf('%02x', ord($this->translateClientChar($raw[$a])));
//		echo "\n";
		
		// (Control) Character translation
		
		
		// Add raw input to buffer
		$this->inputBuffer .= $raw;
		$this->inputBufferLen += strlen($raw);
	}
	
	public function processInput()
	{
		$haveLine = false;
		
		// Here we first check if a telnet command came in.
		// Otherwise we just pass the input to the window handler
		for ($a=0; $a<$this->inputBufferLen; $a++)
		{
			// Check if next bytes in the buffer is a command
			if ($this->inputBuffer[$a] == TELNET_IAC)
			{
				$startIndex = $a;
				$a++;
				switch ($this->inputBuffer[$a])
				{
					// IAC ACTION OPTION (3 bytes)
					case TELNET_ACTION_WILL :
						switch($this->inputBuffer[$a+1])
						{
							case TELNET_OPT_BINARY :
								console('Binary TRUE');
								$this->modeState |= TELNET_MODE_BINARY;
								break;
							case TELNET_OPT_SGA :
								console('SGA TRUE');
								$this->modeState |= TELNET_MODE_SGA;
								break;
							case TELNET_OPT_LINEMODE :
								console('Linemode TRUE');
								$this->modeState |= TELNET_MODE_LINEMODE;
								break;
							case TELNET_OPT_NAWS :
								console('NAWS TRUE');
								$this->modeState |= TELNET_MODE_NAWS;
								break;
							case TELNET_OPT_TTYPE :
								console('client will send ttype list');
								$this->write(TELNET_IAC.TELNET_OPT_SB.TELNET_OPT_TTYPE.chr(1).TELNET_IAC.TELNET_OPT_SE);
								//$this->modeState |= TELNET_MODE_NAWS;
								break;
						}
						$a++;
						break;
	
					case TELNET_ACTION_WONT :
						switch($this->inputBuffer[$a+1])
						{
							case TELNET_OPT_BINARY :
								console('Binary FALSE');
								$this->modeState &= ~TELNET_MODE_BINARY;
								break;
							case TELNET_OPT_SGA :
								console('SGA FALSE');
								$this->modeState &= ~TELNET_MODE_SGA;
								break;
							case TELNET_OPT_LINEMODE :
								console('Linemode FALSE');
								$this->modeState &= ~TELNET_MODE_LINEMODE;
								break;
							case TELNET_OPT_NAWS :
								console('NAWS FALSE');
								$this->modeState &= ~TELNET_MODE_NAWS;
								break;
							case TELNET_OPT_TTYPE :
								console('client will not send ttype list');
								//$this->modeState &= ~TELNET_MODE_NAWS;
								break;
						}
						$a++;
						break;
	
					case TELNET_ACTION_DO :
						switch($this->inputBuffer[$a+1])
						{
							case TELNET_OPT_ECHO :
								console('Server DO echo');
								$this->modeState |= TELNET_MODE_ECHO;
								break;
							case TELNET_OPT_TTYPE :
								console('Server DO ttype');
								//$this->modeState |= TELNET_MODE_ECHO;
								break;
						}
						$a++;
						break;
	
					case TELNET_ACTION_DONT :
						switch($this->inputBuffer[$a+1])
						{
							case TELNET_OPT_ECHO :
								console('Server DONT echo');
								$this->modeState &= ~TELNET_MODE_ECHO;
								break;
							case TELNET_OPT_TTYPE :
								console('Server DONT ttype');
								//$this->modeState &= ~TELNET_MODE_ECHO;
								break;
						}
						$a++;
						break;
	
	//				case TELNET_OPT_BINARY :
	//					break;
	//				
	//				case TELNET_OPT_ECHO :
	//					break;
	//				
	//				case TELNET_OPT_SGA :
	//					break;
	//				
	//				case TELNET_OPT_TTYPE :
	//					break;
	//				
	//				case TELNET_OPT_NAWS :
	//					break;
	//				
	//				case TELNET_OPT_TOGGLE_FLOW_CONTROL :
	//					break;
	//				
	//				case TELNET_OPT_LINEMODE :
	//					break;
					
					// AIC OPTION (2 bytes)
					case TELNET_OPT_NOP :
						break;
					
					case TELNET_OPT_DM :
						break;
					
					case TELNET_OPT_BRK :
						break;
					
					case TELNET_OPT_IP :
						break;
					
					case TELNET_OPT_AO :
						break;
					
					case TELNET_OPT_AYT :
						break;
					
					case TELNET_OPT_EC :
						break;
					
					case TELNET_OPT_EL :
						break;
					
					case TELNET_OPT_GA :
						break;
					
					case TELNET_OPT_EOF :
						break;
					
					case TELNET_OPT_SUSP :
						break;
					
					case TELNET_OPT_ABORT :
						break;
					
					// Suboptions (variable length)
					case TELNET_OPT_SB :
						// Find the next IAC SE
						if (($pos = strpos($this->inputBuffer, TELNET_IAC.TELNET_OPT_SE, $a)) === false)
						{
							return true;		// we need more data.
						}
						
						$a++;
						$dist = $pos - $a;
						$subVars = substr($this->inputBuffer, $a, $dist);
						// Detect the command type
						switch ($subVars[0])
						{
							case TELNET_OPT_LINEMODE :
								switch ($subVars[1])
								{
									case LINEMODE_MODE :
										console('SB LINEMODE MODE sub command');
										break;
									
									case LINEMODE_FORWARDMASK :
										console('SB LINEMODE FORWARDMASK sub command');
										break;
									
									case LINEMODE_SLC :
										console('SB LINEMODE SLC sub command ('.strlen($subVars).')');
										$this->writeCharMap(substr($subVars, 2));
										break;
								}
								break;
							case TELNET_OPT_NAWS :
								console('SB NAWS sub command ('.strlen($subVars).')');
								$this->unescapeIAC($subVars);
								$screenInfo = unpack('Ctype/nwidth/nheight', $subVars);
								$this->winSize = array($screenInfo['width'], $screenInfo['height']);
								break;
							case TELNET_OPT_TTYPE :
								$this->unescapeIAC($subVars);
								$this->term = substr($subVars, 2);
								console('SB TTYPE sub command ('.$this->term.')');
								break;
						}
						$a += $dist + 1;
						break;
					
					case TELNET_OPT_SE :
						// Hmm not possible?
						break;
					
					// Command escape char
					case TELNET_IAC :			// Escaped AIC - treat as single 0xFF; send straight to linebuffer
						$this->charToLineBuffer($this->inputBuffer[$a]);
						break;
					
					default :
						console('UNKNOWN TELNET COMMAND ('.ord($this->inputBuffer[$a]).')');
						break;
					
				}
				
				// We have processed a full command - prune it from the buffer
				if ($startIndex == 0)
				{
					$this->inputBuffer = substr($this->inputBuffer, $a + 1);
					$this->inputBufferLen = strlen($this->inputBuffer);
					$a = -1;
				}
				else
				{
					$this->inputBuffer = substr($this->inputBuffer, 0, $startIndex).substr($this->inputBuffer, $a + 1);
					$this->inputBufferLen = strlen($this->inputBuffer);
				}
				//console('command');
			}
			else
			{
				// Translate char
				$char = $this->translateClientChar($this->inputBuffer[$a]);
				
				// Check char for special meaning
				$special = false;
				switch ($char)
				{
					case KEY_IP :
						$special = true;
						
						// Set close state and return false
						$this->mustClose = true;
						$this->registerInputCallback(null, null);
						return false;
					
					case KEY_BS :
						$special = true;
						
						// See if there are any characters to (backwards) delete at all
						if ($this->lineBufferPtr > 0)
						{
							$this->lineBufferPtr--;
							array_splice($this->lineBuffer, $this->lineBufferPtr, 1);
							
							// Update the client
							$rewrite = '';
							$x = $this->lineBufferPtr;
							while (isset($this->lineBuffer[$x]))
								$rewrite .= $this->lineBuffer[$x++];
							$cursorBack = KEY_ESCAPE.'['.(strlen($rewrite)+1).'D';
							$this->write($this->inputBuffer[$a].$rewrite.' '.$cursorBack);
						}
						break;

					case KEY_TAB :
						$special = true;
						$this->charToLineBuffer('    ');
						break;
					
					case KEY_DELETE :
						$special = true;
						
						// See if we're not at the end of the line buffer
						if (isset($this->lineBuffer[$this->lineBufferPtr]))
						{
							array_splice($this->lineBuffer, $this->lineBufferPtr, 1);
							
							// Update the client
							$rewrite = '';
							$x = $this->lineBufferPtr;
							while (isset($this->lineBuffer[$x]))
								$rewrite .= $this->lineBuffer[$x++];
							$cursorBack = KEY_ESCAPE.'['.(strlen($rewrite)+1).'D';
							$this->write($rewrite.' '.$cursorBack);
						}
						
						break;
					
					case KEY_ESCAPE :
						// Always skip at least escape char from lineBuffer.
						// Below we further adjust the $a pointer where needed.
						$special = true;

						// Look ahead in inputBuffer to detect escape sequence
						if (!isset($this->inputBuffer[$a+1]) || $this->inputBuffer[$a+1] != '[')
							break;
						
						$input = substr($this->inputBuffer, $a);
						$matches = array();
						if (preg_match('/^('.KEY_ESCAPE.'\[(\d?)D).*$/', $input, $matches))
						{
							// CURSOR LEFT
							if ($this->lineBufferPtr > 0)
							{
								$this->write($matches[1]);
								$a += strlen($matches[1]) - 1;
								$this->lineBufferPtr -= ((int) $matches[2] > 1) ? (int) $matches[2] : 1;
							}
						}
						else if (preg_match('/^('.KEY_ESCAPE.'\[(\d?)C).*$/', $input, $matches))
						{
							// CURSOR RIGHT
							if (isset($this->lineBuffer[$this->lineBufferPtr]))
							{
								$this->write($matches[1]);
								$a += strlen($matches[1]) - 1;
								$this->lineBufferPtr += ((int) $matches[2] > 1) ? (int) $matches[2] : 1;
							}
						}
						else if (preg_match('/^('.KEY_ESCAPE.'\[(\d?)A).*$/', $input, $matches))
						{
							// CURSOR UP
							//$this->write($matches[1]);
						}
						else if (preg_match('/^('.KEY_ESCAPE.'\[(\d?)B).*$/', $input, $matches))
						{
							// CURSOR DOWN
							//$this->write($matches[1]);
						}
						else if (preg_match('/^('.KEY_ESCAPE.'\[3~).*$/', $input, $matches))
						{
							// Alternate DEL keycode
							// See if we're not at the end of the line buffer
							if (isset($this->lineBuffer[$this->lineBufferPtr]))
							{
								array_splice($this->lineBuffer, $this->lineBufferPtr, 1);
								
								// Update the client
								$rewrite = '';
								$x = $this->lineBufferPtr;
								while (isset($this->lineBuffer[$x]))
									$rewrite .= $this->lineBuffer[$x++];
								$cursorBack = KEY_ESCAPE.'['.(strlen($rewrite)+1).'D';
								$this->write($rewrite.' '.$cursorBack);
							}
						}
						else if (preg_match('/^('.KEY_ESCAPE.'\[2~).*$/', $input, $matches))
						{
							// INSERT
							$this->modeState ^= TELNET_MODE_INSERT;
						}
						else if (preg_match('/^('.KEY_ESCAPE.'\[1~).*$/', $input, $matches))
						{
							// HOME
							// Move cursor to start of edit-line
							$diff = $this->lineBufferPtr;
							$this->lineBufferPtr = 0;
							$this->write(KEY_ESCAPE.'['.$diff.'D');
						}
						else if (preg_match('/^('.KEY_ESCAPE.'\[4~).*$/', $input, $matches))
						{
							// END
							// Move cursor to end of edit-line
							$bufLen = count($this->lineBuffer);
							$diff = $bufLen - $this->lineBufferPtr;
							$this->lineBufferPtr = $bufLen;
							$this->write(KEY_ESCAPE.'['.$diff.'C');
						}

						// Move inputBuffer pointer ahead to cover multibyte char?
						if (count($matches) > 1)
							$a += strlen($matches[1]) - 1;
						
						break;
				}
				
				// Regular characers. Process them via line-edit mode or single key mode
				if (!$special)
				{
					// We must detect the Enter key here
					$enterChar = $this->isEnter($a);
					
					if ($this->modeState & TELNET_MODE_LINEEDIT)
					{
						// Line processing
						if ($enterChar === null)
						{
							// Store char in linfe buffer
							$this->charToLineBuffer($this->inputBuffer[$a]);
						}
						else
						{
							// Detect whole lines when Enter encountered
							$this->charToLineBuffer($enterChar, true);
							do
							{
								$line = $this->getLine();
								if ($line === false)
									break;
									
								// Send line to the current input callback function (if there is one)
								$method = $this->inputCallback[1];
								$this->inputCallback[0]->$method($line);
							} while(true);
						}
					}
					else
					{
						// Single key processing (if there is a callback at all)
						if ($this->inputCallback[0])
						{
							if ($enterChar === null)
							{
								$method = $this->inputCallback[1];
								$this->inputCallback[0]->$method($this->inputBuffer[$a]);
							}
							else
							{
								$method = $this->inputCallback[1];
								$this->inputCallback[0]->$method($enterChar);
							}
						}
					}
				}
			}
		}

		$this->inputBuffer = substr($this->inputBuffer, $a + 1);
		$this->inputBufferLen = strlen($this->inputBuffer);

		return true;
	}
	
	/*
	 * $func	  = function that will handle the user's keyboard input
	 * $editMode  = either 0 or anything else (TELNET_MODE_LINEEDIT)
	 * 				This indicates where the function expects a single char or a whole line
	*/
	public function registerInputCallback($class, $func, $editMode = 0)
	{
		if (!$class || !$func)
		{
			$this->inputCallback = null;
			$editMode = 0;
//			console('UNREGISTERED FUNCTION');
		}
		else
		{
			$this->inputCallback = array($class, $func);
//			console('REGISTERED FUNCTION');
		}
		
		if ($editMode == 0)
			$this->modeState &= ~TELNET_MODE_LINEEDIT;
		else
			$this->modeState |= TELNET_MODE_LINEEDIT;
	}
	
	// Get a whole line from input
	private function getLine()
	{
		// Detect carriage return / line feed / whatever you want to call it
		$count = count($this->lineBuffer);
		if (!$count)
			return false;
		
		$line = '';
		$haveLine = false;
		for ($a=0; $a<$count; $a++)
		{
			if ($this->modeState & TELNET_MODE_LINEMODE)
			{
				if ($this->lineBuffer[$a] == "\r")
				{
					$haveLine = true;
					break;				// break out of the main char by char loop
				}
			}
			else
			{
				if (isset($this->lineBuffer[$a+1]) && 
					$this->lineBuffer[$a].$this->lineBuffer[$a+1] == "\r\n")
				{
					$a++;
					$haveLine = true;
					break;				// break out of the main char by char loop
				}
			}
			$line .= $this->lineBuffer[$a];
		}
		
		if ($haveLine)
		{
			// Send return to client if in echo mode (and later on, if in simple mode)
			if ($this->modeState & TELNET_MODE_ECHO)
				$this->write("\r\n");
			
			// Splice line out of line buffer
			array_splice($this->lineBuffer, 0, $a+1);
			
			$this->lineBuffer = array();
			$this->lineBufferPtr = 0;
			return $line;
		}

		return false;
	}
	
	private function isEnter(&$a)
	{
		if ($this->modeState & TELNET_MODE_LINEMODE)
		{
			if ($this->inputBuffer[$a] == "\r")
				return "\r";
		}
		else
		{
			if (isset($this->inputBuffer[$a+1]) && 
				$this->inputBuffer[$a].$this->inputBuffer[$a+1] == "\r\n")
			{
				$a++;
				return "\r\n";
			}
		}
		return null;
	}
	
	private function charToLineBuffer($char, $isEnter = false)
	{
		if ($isEnter)
		{
			for ($a=0; $a<strlen($char); $a++)
				$this->lineBuffer[] = $char[$a];
		}
		else if ($this->modeState & TELNET_MODE_INSERT)
		{
			for ($a=0; $a<strlen($char); $a++)
				array_splice($this->lineBuffer, $this->lineBufferPtr++, 0, array($char[$a]));
		}
		else
		{
			for ($a=0; $a<strlen($char); $a++)
				$this->lineBuffer[$this->lineBufferPtr++] = $char[$a];
		}
		
		// Must we update the client?
		if ($this->modeState & TELNET_MODE_ECHO)
		{
			if ($char == KEY_TAB || ($char = filter_var($char, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH)) != '')
			{
				$rewrite = $cursorBack = '';

				// Are we in insert mode and do we have to move any chars?
				if ($this->modeState & TELNET_MODE_INSERT && isset($this->lineBuffer[$this->lineBufferPtr]))
				{
					// Write the remaining chars and return cursor to original pos
					$x = $this->lineBufferPtr;
					while (isset($this->lineBuffer[$x]))
						$rewrite .= $this->lineBuffer[$x++];
					$cursorBack = KEY_ESCAPE.'['.(strlen($rewrite)).'D';
				}

				if ($this->loginState == TELNET_ASKED_PASSWORD)
					$this->write('*'.$rewrite.$cursorBack);
				else
					$this->write($char.$rewrite.$cursorBack);
			}
		}
	}
	
	private function translateClientChar($char)
	{
		foreach ($this->charMap as $func => $data)
		{
			if ($data[0] == $char)
			{
				$tr = $this->getFunctionChar($func);
				if ($tr)
					return $tr;
			}
		}
		
		return $char;
	}
	
	private function translateServerChar($char)
	{
		$this->charMap;
	}
	
	private function writeCharMap($mapData)
	{
		// Unescape IACIAC
		$this->unescapeIAC($mapData);
		
		// We must have a number of octect triplets
		$len = strlen($mapData);
		if (($len % 3) != 0)
			return false;
		
		$a = 0;
		$this->charMap = array();
		while ($a<$len)
		{
			$func		= $mapData[$a++];
			$options	= $mapData[$a++];
			$ascii		= $mapData[$a++];
			
			$this->charMap[$func] = array($ascii, $options);
		}
		
		return true;
	}
	
	private function unescapeIAC(&$data)
	{
		$new = '';
		for ($a=0; $a<strlen($data); $a++)
		{
			if ($data[$a] == TELNET_IAC &&
				isset($data[$a+1]) &&
				$data[$a+1] == TELNET_IAC)
			{
				continue;
			}
			$new .= $data[$a];
		}
		$data = $new;
	}
	
	// Get the default ascii character that belongs to a certain SLC function
	private function getFunctionChar($func)
	{
		switch ($func)
		{
			case LINEMODE_SLC_SYNCH :
				break;
			
			case LINEMODE_SLC_BRK :
				break;
			
			case LINEMODE_SLC_IP :
				return KEY_IP;			// ctrl-c
			
			case LINEMODE_SLC_AO :
				break;
			
			case LINEMODE_SLC_AYT :
				break;
			
			case LINEMODE_SLC_EOR :
				break;
			
			case LINEMODE_SLC_ABORT :
				break;
			
			case LINEMODE_SLC_EOF :
				break;
			
			case LINEMODE_SLC_SUSP :
				break;
			
			case LINEMODE_SLC_EC :
				return KEY_BS;			// backspace
			
			case LINEMODE_SLC_EL :
				break;
			
			case LINEMODE_SLC_EW :
				break;
			
			case LINEMODE_SLC_RP :
				break;

			case LINEMODE_SLC_LNEXT :
				break;
			
			case LINEMODE_SLC_XON :
				break;
			
			case LINEMODE_SLC_XOFF :
				break;
			
			case LINEMODE_SLC_FORW1 :
				break;
			
			case LINEMODE_SLC_FORW2 :
				break;
			
			case LINEMODE_SLC_MCL :
				break;
			
			case LINEMODE_SLC_MCR :
				break;
			
			case LINEMODE_SLC_MCWL :
				break;
			
			case LINEMODE_SLC_MCWR :
				break;
			
			case LINEMODE_SLC_MCBOL :
				break;
			
			case LINEMODE_SLC_MCEOL :
				break;
			
			case LINEMODE_SLC_INSRT :
				break;
			
			case LINEMODE_SLC_OVER :
				break;
			
			case LINEMODE_SLC_ECR :
				break;
			
			case LINEMODE_SLC_EWR :
				break;
			
			case LINEMODE_SLC_EBOL :
				break;
			
			case LINEMODE_SLC_EEOL :
				break;
		}
		
		return null;
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
	
	private function addPacketToSendQ($data)
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
	
	private function sendQReset()
	{
		$this->sendQ			= '';
		$this->sendQLen			= 0;
		$this->lastActivity		= time();
	}
}

?>