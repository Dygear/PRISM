<?php
/**
 * PHPInSimMod - Telnet Module
 * @package PRISM
 * @subpackage Telnet
*/

require_once(ROOTPATH . '/modules/prism_telnet_screen.php');

/**
 * The TelnetServer class does all connection handling and terminal negotiations and input handling.
 * Any telnet input is then passed to the registered callback function.
*/
class TelnetServer extends TelnetScreen
{
	private $socket			= null;
	private $ip				= '';
	private $port			= 0;
	
	private $lineBuffer		= array();
	private $lineBufferPtr	= 0;
	private $inputBuffer	= '';
	private $inputBufferLen	= 0;
	private $inputBufferMaxLen	= 23;
	
	// send queue used for backlog, in case we can't send a reply in one go
	private $sendQ			= '';
	private $sendQLen		= 0;

	private $sendWindow		= STREAM_WRITE_BYTES;	// dynamic window size
	
	private $lastActivity	= 0;
	private $mustClose		= false;
	
	// Editing related
	private $echoChar		= null;
	private $inputCallback	= null;
	
	private $charMap		= array();
	
	public function __construct(&$sock, &$ip, &$port)
	{
		$this->socket		= $sock;
		$this->ip			= $ip;
		$this->port			= $port;
		
		$this->lastActivity	= time();
		
		// Start terminal state negotiation
		$this->setTelnetOption(TELNET_ACTION_DO, TELNET_OPT_BINARY);
		$this->setTelnetOption(TELNET_ACTION_WILL, TELNET_OPT_ECHO);
		$this->setTelnetOption(TELNET_ACTION_DO, TELNET_OPT_SGA);
		$this->setTelnetOption(TELNET_ACTION_DO, TELNET_OPT_LINEMODE);
		$this->setTelnetOption(TELNET_ACTION_DO, TELNET_OPT_NAWS);
		$this->setTelnetOption(TELNET_ACTION_DO, TELNET_OPT_TTYPE);

		$this->modeState |= TELNET_MODE_INSERT;
	}
	
	public function __destruct()
	{
		if ($this->sendQLen > 0)
			$this->sendQReset();

		if (is_resource($this->socket))
		{
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
	
	public function getMustClose()
	{
		return $this->mustClose;
	}
	
	/**
	 * Sets which character should be echoed when in server echo mode
	 * $echoChar = null			- echo what the user types
	 * $echoChar = ''			- echo an empty char == echo nothing at all
	 * $echoChar = '<somechar>'	- echo <somechar>
	*/
	protected function setEchoChar($echoChar)
	{
		$this->echoChar = $echoChar;
	}
	
	/*
	 * $func	  = function that will handle the user's keyboard input
	 * $editMode  = either 0 or anything else (TELNET_MODE_LINEEDIT)
	 * 				This indicates where the function expects a single char or a whole line
	*/
	public function registerInputCallback($class, $func = null, $editMode = 0)
	{
		if (!$class || !$func)
		{
			$this->inputCallback = null;
			$editMode = 0;
		}
		else
		{
			$this->inputCallback = array($class, $func);
//			console('SETTING CALLBACK FUNCTION : '.$func);
		}
		
		if ($editMode == 0)
		{
			$this->modeState &= ~TELNET_MODE_LINEEDIT;
			$this->setCursorProperties(TELNET_CURSOR_HIDE);
		}
		else
		{
			$this->modeState |= TELNET_MODE_LINEEDIT;
			$this->setCursorProperties(0);
		}
	}
	
	protected function shutdown()
	{
		$this->mustClose = true;
		$this->registerInputCallback(null);
	}

	private function setTelnetOption($action, $option)
	{
		$this->write(TELNET_IAC.$action.$option);
	}
	
	public function read(&$data)
	{
		$this->lastActivity	= time();
		return fread($this->socket, STREAM_READ_BYTES);
	}
	
	public function addInputToBuffer(&$raw)
	{
//		for ($a=0; $a<strlen($raw); $a++)
//			printf('%02x', ord($raw[$a]));
////			printf('%02x', ord($this->translateClientChar($raw[$a])));
//		echo "\n";
		
		// Add raw input to buffer
		$this->inputBuffer .= $raw;
		$this->inputBufferLen += strlen($raw);
	}
	
	public function processInput()
	{
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
								//console('client WILL BINARY');
								$this->modeState |= TELNET_MODE_BINARY;
								break;
							case TELNET_OPT_SGA :
								//console('client WILL SGA');
								$this->modeState |= TELNET_MODE_SGA;
								break;
							case TELNET_OPT_LINEMODE :
								//console('client WILL Linemode');
								$this->modeState |= TELNET_MODE_LINEMODE;
								break;
							case TELNET_OPT_NAWS :
								//console('client WILL NAWS');
								$this->modeState |= TELNET_MODE_NAWS;
								break;
							case TELNET_OPT_TERMINAL_SPEED :
								//console('client WILL terminal speed');
								$this->modeState |= TELNET_MODE_TERMINAL_SPEED;
								$this->setTelnetOption(TELNET_ACTION_DONT, TELNET_OPT_TERMINAL_SPEED);
								break;
							case TELNET_OPT_TTYPE :
								//console('client WILL TTYPE');
								$this->write(TELNET_IAC.TELNET_OPT_SB.TELNET_OPT_TTYPE.chr(1).TELNET_IAC.TELNET_OPT_SE);
								//$this->modeState |= TELNET_MODE_NAWS;
								break;
							case TELNET_OPT_NEW_ENVIRON :
								//console('client WILL NEW-ENVIRON');
								$this->modeState |= TELNET_MODE_NEW_ENVIRON;
								$this->setTelnetOption(TELNET_ACTION_DO, TELNET_OPT_NEW_ENVIRON);
								$this->write(TELNET_IAC.TELNET_OPT_SB.TELNET_OPT_NEW_ENVIRON.chr(1).TELNET_IAC.TELNET_OPT_SE);
								break;
						}
						$a++;
						break;
	
					case TELNET_ACTION_WONT :
						switch($this->inputBuffer[$a+1])
						{
							case TELNET_OPT_BINARY :
								//console('client WON\'T BINERY');
								$this->modeState &= ~TELNET_MODE_BINARY;
								break;
							case TELNET_OPT_SGA :
								//console('client WON\'T SGA');
								$this->modeState &= ~TELNET_MODE_SGA;
								break;
							case TELNET_OPT_LINEMODE :
								//console('client WON\'T Linemode');
								$this->modeState &= ~TELNET_MODE_LINEMODE;
								break;
							case TELNET_OPT_NAWS :
								//console('client WON\'T NAWS');
								$this->modeState &= ~TELNET_MODE_NAWS;
								break;
							case TELNET_OPT_TERMINAL_SPEED :
								//console('client WON\'T terminal speed');
								$this->modeState &= ~TELNET_MODE_TERMINAL_SPEED;
								break;
							case TELNET_OPT_TTYPE :
								//console('client WON\'T TTYPE');
								//$this->modeState &= ~TELNET_MODE_NAWS;
								break;
							case TELNET_OPT_NEW_ENVIRON :
								//console('client WON\'T NEW-ENVIRON');
								$this->modeState |= TELNET_MODE_NEW_ENVIRON;
								break;
						}
						$a++;
						break;
	
					case TELNET_ACTION_DO :
						switch($this->inputBuffer[$a+1])
						{
							case TELNET_OPT_ECHO :
								//console('Server DO echo');
								$this->modeState |= TELNET_MODE_ECHO;
								break;
							case TELNET_OPT_TTYPE :
								//console('Server DO ttype');
								//$this->modeState |= TELNET_MODE_ECHO;
								break;
						}
						$a++;
						break;
	
					case TELNET_ACTION_DONT :
						switch($this->inputBuffer[$a+1])
						{
							case TELNET_OPT_ECHO :
								//console('Server DONT echo');
								$this->modeState &= ~TELNET_MODE_ECHO;
								break;
							case TELNET_OPT_TTYPE :
								//console('Server DONT ttype');
								//$this->modeState &= ~TELNET_MODE_ECHO;
								break;
						}
						$a++;
						break;

					// AIC OPTION (2 bytes)
					case TELNET_OPT_NOP :
						break;
					
					case TELNET_OPT_DM :
						break;
					
					case TELNET_OPT_BRK :
						break;
					
					case TELNET_OPT_IP :
						$this->shutdown();
						return false;
					
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
										//console('SB LINEMODE MODE sub command');
										break;
									
									case LINEMODE_FORWARDMASK :
										//console('SB LINEMODE FORWARDMASK sub command');
										break;
									
									case LINEMODE_SLC :
										//console('SB LINEMODE SLC sub command ('.strlen($subVars).')');
										$this->writeCharMap(substr($subVars, 2));
										break;
								}
								break;
							case TELNET_OPT_NAWS :
								//console('SB NAWS sub command ('.strlen($subVars).')');
								$this->unescapeIAC($subVars);
								$screenInfo = unpack('Ctype/nwidth/nheight', $subVars);
								$this->setWinSize($screenInfo['width'], $screenInfo['height']);
								break;
							case TELNET_OPT_TTYPE :
								$this->unescapeIAC($subVars);
								$ttype = substr($subVars, 2);
								if (stripos($ttype, 'xterm') !== false)
									$this->setTType(TELNET_TTYPE_XTERM);
								else if (stripos($ttype, 'ansi') !== false)
									$this->setTType(TELNET_TTYPE_ANSI);
								else
									$this->setTType(TELNET_TTYPE_OTHER);
								
								//console('SB TTYPE sub command ('.$this->getTType().')');
								break;
							case TELNET_OPT_NEW_ENVIRON :
								$this->unescapeIAC($subVars);

								switch(ord($subVars[1]))
								{
									case 0 :		// IS
										$values = substr($subVars, 2);
										console('SB NEW_ENVIRON sub IS command ('.strlen($values).')');
										break;
									case 1 :		// SEND
										break;
									case 2 :		// INFO
										$values = substr($subVars, 2);
										console('SB NEW_ENVIRON sub INFO command ('.strlen($values).')');
										break;
								}
								
								//console('SB NEW_ENVIRON sub command ('.strlen($subVars).')');
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
				// Translate char (eg, 7f -> 08)
				$char = $this->translateClientChar($this->inputBuffer[$a]);
				
				// Check char for special meaning
				$special = false;
				if ($this->modeState & TELNET_MODE_LINEEDIT)
				{
					// LINE-EDIT PROCESSING
					switch ($char)
					{
						case KEY_IP :
							$special = true;
							$this->shutdown();
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
								{
									if ($this->echoChar !== null)
										$rewrite .= $this->echoChar;
									else
										$rewrite .= $this->lineBuffer[$x];
									$x++;
								}
								$cursorBack = KEY_ESCAPE.'['.(strlen($rewrite)+1).'D';
								$this->write(KEY_ESCAPE.'[D'.$rewrite.' '.$cursorBack);
							}
							break;
	
						case KEY_TAB :
							$special = true;
							$this->handleKey(KEY_TAB);
							break;
						
						case KEY_DELETE :
							$special = true;
							
							if ($this->getTType() == TELNET_TTYPE_XTERM &&
								($this->modeState & TELNET_MODE_LINEMODE) == 0)
							{
								// BACKSPACE
								if ($this->lineBufferPtr > 0)
								{
									$this->lineBufferPtr--;
									array_splice($this->lineBuffer, $this->lineBufferPtr, 1);
									
									// Update the client
									$rewrite = '';
									$x = $this->lineBufferPtr;
									while (isset($this->lineBuffer[$x]))
									{
										if ($this->echoChar !== null)
											$rewrite .= $this->echoChar;
										else
											$rewrite .= $this->lineBuffer[$x];
										$x++;
									}
									$cursorBack = KEY_ESCAPE.'['.(strlen($rewrite)+1).'D';
									$this->write(KEY_ESCAPE.'[D'.$rewrite.' '.$cursorBack);
								}
							}
							else
							{
								// DELETE
								// See if we're not at the end of the line buffer
								if (isset($this->lineBuffer[$this->lineBufferPtr]))
								{
									array_splice($this->lineBuffer, $this->lineBufferPtr, 1);
									
									// Update the client
									$rewrite = '';
									$x = $this->lineBufferPtr;
									while (isset($this->lineBuffer[$x]))
									{
										if ($this->echoChar !== null)
											$rewrite .= $this->echoChar;
										else
											$rewrite .= $this->lineBuffer[$x];
										$x++;
									}
									$cursorBack = KEY_ESCAPE.'['.(strlen($rewrite)+1).'D';
									$this->write($rewrite.' '.$cursorBack);
								}
							}
							
							break;
						
						case KEY_ESCAPE :
							// Always skip at least escape char from lineBuffer.
							// Below we further adjust the $a pointer where needed.
							$special = true;
	
							// Look ahead in inputBuffer to detect escape sequence
							if (!isset($this->inputBuffer[$a+1]) || 
								($this->inputBuffer[$a+1] != '[' && $this->inputBuffer[$a+1] != 'O'))
							{
								$this->handleKey(KEY_ESCAPE);
								break;
							}
							
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
								$this->handleKey(KEY_CURUP);
								//$this->write($matches[1]);
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[(\d?)B).*$/', $input, $matches))
							{
								// CURSOR DOWN
								$this->handleKey(KEY_CURDOWN);
								//$this->write($matches[1]);
							}

							// CTRL-Arrow keys
							else if (preg_match('/^('.KEY_ESCAPE.'\O(\d?)D).*$/', $input, $matches))
							{
								// CURSOR LEFT CTRL
								//$char = KEY_CURLEFT_CTRL;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\O(\d?)C).*$/', $input, $matches))
							{
								// CURSOR RIGHT CTRL
								//$char = KEY_CURRIGHT_CTRL;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\O(\d?)A).*$/', $input, $matches))
							{
								// CURSOR UP CTRL
								//$char = KEY_CURUP_CTRL;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\O(\d?)B).*$/', $input, $matches))
							{
								// CURSOR DOWN CTRL
								//$char = KEY_CURDOWN_CTRL;
							}
							
							// Other keys
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
									{
										if ($this->echoChar !== null)
											$rewrite .= $this->echoChar;
										else
											$rewrite .= $this->lineBuffer[$x];
										$x++;
									}
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
							else if (preg_match('/^('.KEY_ESCAPE.'\[Z).*$/', $input, $matches))
							{
								// SHIFT-TAB
								$this->handleKey(KEY_SHIFTTAB);
							}
	
							// Move inputBuffer pointer ahead to cover multibyte char?
							if (count($matches) > 1)
								$a += strlen($matches[1]) - 1;
							
							break;
					}

					// Regular characers.
					if ($special)
						continue;

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
				}
				else
				{
					// SINGLE KEY PROCESSING
					switch ($char)
					{
						case KEY_IP :
							$special = true;
							$this->shutdown();
							return false;

						case KEY_BS :
						case KEY_TAB :
						case KEY_DELETE :
							break;
						
						case KEY_ESCAPE :
							
							// Look ahead in inputBuffer to detect escape sequence
							if (!isset($this->inputBuffer[$a+1]) || 
								($this->inputBuffer[$a+1] != '[' && $this->inputBuffer[$a+1] != 'O'))
								break;
							
							$input = substr($this->inputBuffer, $a);
							$matches = array();
							
							// Arrow keys
							if (preg_match('/^('.KEY_ESCAPE.'\[(\d?)D).*$/', $input, $matches))
							{
								// CURSOR LEFT
								$char = KEY_CURLEFT;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[(\d?)C).*$/', $input, $matches))
							{
								// CURSOR RIGHT
								$char = KEY_CURRIGHT;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[(\d?)A).*$/', $input, $matches))
							{
								// CURSOR UP
								$char = KEY_CURUP;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[(\d?)B).*$/', $input, $matches))
							{
								// CURSOR DOWN
								$char = KEY_CURDOWN;
							}
							
							// CTRL-Arrow keys
							else if (preg_match('/^('.KEY_ESCAPE.'\O(\d?)D).*$/', $input, $matches))
							{
								// CURSOR LEFT
								$char = KEY_CURLEFT_CTRL;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\O(\d?)C).*$/', $input, $matches))
							{
								// CURSOR RIGHT
								$char = KEY_CURRIGHT_CTRL;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\O(\d?)A).*$/', $input, $matches))
							{
								// CURSOR UP
								$char = KEY_CURUP_CTRL;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\O(\d?)B).*$/', $input, $matches))
							{
								// CURSOR DOWN
								$char = KEY_CURDOWN_CTRL;
							}
							
							// Other special keys
							else if (preg_match('/^('.KEY_ESCAPE.'\[3~).*$/', $input, $matches))
							{
								// Alternate DEL keycode
								$char = KEY_DELETE;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[2~).*$/', $input, $matches))
							{
								// INSERT
								$char = KEY_INSERT;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[1~).*$/', $input, $matches))
							{
								// HOME
								$char = KEY_HOME;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[4~).*$/', $input, $matches))
							{
								// END
								$char = KEY_END;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[5~).*$/', $input, $matches))
							{
								// PgUp
								$char = KEY_PAGEUP;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[6~).*$/', $input, $matches))
							{
								// PgDn
								$char = KEY_PAGEDOWN;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'OP).*$/', $input, $matches))
							{
								// F1 (windows)
								$char = KEY_F1;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'OQ).*$/', $input, $matches))
							{
								// F2 (windows)
								$char = KEY_F2;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'OR).*$/', $input, $matches))
							{
								// F3 (windows)
								$char = KEY_F3;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'OS).*$/', $input, $matches))
							{
								// F4 (windows)
								$char = KEY_F4;
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[([0-9]{2})~).*$/', $input, $matches) &&
									$matches[2] > 10 && $matches[2] < 25 && $matches[2] != 16 && $matches[2] != 22)
							{
								// Fxx
								$char = chr(1).chr($matches[2]);
							}
							else if (preg_match('/^('.KEY_ESCAPE.'\[Z).*$/', $input, $matches))
							{
								// SHIFT-TAB
								$char = KEY_SHIFTTAB;
							}
							else
							{
								console(substr($input, 1));
							}
	
							// Move inputBuffer pointer ahead to cover multibyte char?
							if (count($matches) > 1)
								$a += strlen($matches[1]) - 1;
							
							break;
					}

					if ($special)
						continue;
					
					// Single key processing (if there is a callback at all)
					if ($this->inputCallback[0])
					{
						if ($this->isEnter($a) !== null)
							$char = KEY_ENTER;
						
						$method = $this->inputCallback[1];
						$this->inputCallback[0]->$method($char);
					}
				}
			}
		}

		$this->inputBuffer = substr($this->inputBuffer, $a + 1);
		$this->inputBufferLen = strlen($this->inputBuffer);

		return true;
	}
	
	// Get a whole line from input
	public function getLine($forceFlush = false)
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
		
		if ($haveLine || $forceFlush)
		{
			// Send return to client if in echo mode (and later on, if in simple mode)
//			if ($this->modeState & TELNET_MODE_ECHO)
//				$this->write("\r\n");
			
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
			if ($this->inputBuffer[$a] == "\r" && !isset($this->inputBuffer[$a+1]))
			{
				return "\r\n";
			}
			else if (isset($this->inputBuffer[$a+1]) && 
				$this->inputBuffer[$a].$this->inputBuffer[$a+1] == "\r\n")
			{
				$a++;
				return "\r\n";
			}
		}
		return null;
	}
	
	public function setLineBuffer($chars)
	{
		$this->lineBuffer = array();
		$this->lineBufferPtr = 0;
		for ($a=0; $a<strlen($chars); $a++)
			$this->lineBuffer[$this->lineBufferPtr++] = $chars[$a];
	}
	
	public function setInputBufferMaxLen($maxLength)
	{
		$this->inputBufferMaxLen = (int) $maxLength;
		if ($this->inputBufferMaxLen < 1)
			$this->inputBufferMaxLen = 1;
	}
	
	private function charToLineBuffer($char, $isEnter = false)
	{
		// If buffer 'full', just return and ignore the new char
		if (count($this->lineBuffer) == $this->inputBufferMaxLen)
			return;

		// Add the new char			
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

				if ($this->echoChar !== null)
					$this->write($this->echoChar.$rewrite.$cursorBack);
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
			
			//console(printf());
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