<?php

/**
 * ScreenObject is the base class for all screen components
 * (ScreenContainer, TextLine, TextArea, etc)
*/
abstract class ScreenObject
{
	abstract public function draw($parentX, $parentY, $parentWidth, $parentHeight);

	private $id				= '';
	private $x				= 0;
	private $y				= 0;
	private $absolute		= false;			// Absolute position or relative to parent
	private $cols			= 0;				// width
	protected $realWidth	= 0;
	private $lines			= 0;				// height
	protected $realLines	= 0;
	
	private $ttype			= '';
	private $border			= TS_BORDER_NONE;	// border type
	private $margin			= 0;				// border margin
	private $caption		= '';
	
	protected $screenCache	= '';				// object contents cache
	
	public function setId($id)
	{
		$this->id = $id;
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	public function setTType($ttype)
	{
		$this->ttype = $ttype;
	}
	
	public function getTType()
	{
		return $this->ttype;
	}
	
	public function setLocation($x, $y)
	{
		$this->setX($x);
		$this->setY($y);
	}
	
	public function setX($x)
	{
		$x = (int) $x;
		if ($x < 0)
			$x = 0;
		$this->x		= $x;
	}
	
	public function setY($y)
	{
		$y = (int) $y;
		if ($y < 0)
			$y = 0;
		$this->y		= $y;
	}
	
	public function getLocation()
	{
		return array($this->x, $this->y);
	}
	
	public function getX()
	{
		return $this->x;
	}
	
	public function getY()
	{
		return $this->y;
	}
	
	public function setAbsolute($absolute)
	{
		$this->absolute = $absolute;
	}
	
	public function getAbsolute()
	{
		return $this->absolute;
	}
	
	public function setSize($cols, $lines)
	{
		$this->setWidth($cols);
		$this->setHeight($lines);
		$this->screenCache	= '';
	}
	
	public function setWidth($cols)
	{
		$cols = (int) $cols;
		if ($cols < 0)
			$cols = 0;
		$this->cols			= $cols;
		$this->screenCache	= '';
	}

	public function setHeight($lines)
	{
		$lines = (int) $lines;
		if ($lines < 0)
			$lines = 0;
		$this->lines		= $lines;
		$this->screenCache	= '';
	}

	public function getSize()
	{
		return array($this->cols, $this->lines);
	}

	public function getWidth()
	{
		return $this->cols;
	}

	public function getHeight()
	{
		return $this->lines;
	}
	
	public function setBorder($border)
	{
		$border = (int) $border;
		if ($border < 0 || $border > TS_BORDER_NUMTYPES)
			$border = 0;
		$this->border = $border;
	}
	
	public function getBorder()
	{
		return $this->border;
	}

	public function setMargin($margin)
	{
		$margin = (int) $margin;
		if ($margin < 0)
			$margin = 0;
		$this->margin = $margin;
	}
	
	public function getMargin()
	{
		return $this->margin;
	}

	public function getCaption()
	{
		return $this->caption;
	}
	
	public function setCaption($caption)
	{
		$this->caption		= $caption;
		$this->screenCache	= '';
	}
	
	public function clearCache()
	{
		$this->screenCache = '';
	}
	
	protected function drawBorder(&$screenMargin)
	{
		$screenBuf = '';
		
		// Draw own style (backgroud? border?)
		if ($this->getBorder() > TS_BORDER_NONE)
		{
			// Initialise border helper object
			$bHelp = new ScreenBorderHelper($this->getTType());
			$screenBuf .= $bHelp->start();

			// Draw border stuff
			$line = 0;
			while ($line < $this->getRealHeight())
			{
				// Move to new line (if not on line 0)
				if ($line > 0)
				{
					$screenBuf .= KEY_ESCAPE.'['.$this->realWidth.'D'.KEY_ESCAPE.'[1B';
				}

				// First and last line
				if ($line == 0 || $line == $this->getRealHeight() - 1)
				{
					$pos = 0;
					while ($pos < $this->realWidth)
					{
						if ($line == 0 && $pos == 0)
							$screenBuf .= $bHelp->getChar(TC_BORDER_TOPLEFT);
						else if ($line == 0 && $pos == $this->realWidth-1)
							$screenBuf .= $bHelp->getChar(TC_BORDER_TOPRIGHT);
						else if ($line == $this->getRealHeight() - 1 && $pos == 0)
							$screenBuf .= $bHelp->getChar(TC_BORDER_BOTTOMLEFT);
						else if ($line == $this->getRealHeight() - 1 && $pos == $this->realWidth-1)
							$screenBuf .= $bHelp->getChar(TC_BORDER_BOTTOMRIGHT);
						else
							$screenBuf .= $bHelp->getChar(TC_BORDER_HORILINE);
						$pos++;
					}

					// Caption on border?
					if ($line == 0 && $this->getCaption() != '')
					{
						$screenBuf .= $bHelp->end();

						$cLen = strlen($this->getCaption());
						$captionX = floor(($this->realWidth - $cLen) / 2);

						$screenBuf .= KEY_ESCAPE.'['.($this->realWidth - $captionX).'D';
						$screenBuf .= $this->getCaption();
						$screenBuf .= KEY_ESCAPE.'['.($this->realWidth - ($cLen + $captionX)).'C';

						$screenBuf .= $bHelp->start();
					}
				}
				else
				{
					// Place border only on first and last char
					$screenBuf .= $bHelp->getChar(TC_BORDER_VERTLINE);
					$screenBuf .= KEY_ESCAPE.'['.($this->realWidth - 2).'C';
					$screenBuf .= $bHelp->getChar(TC_BORDER_VERTLINE);
				}
				
				$line++;
			}

			// Always end border helper (because we may have to reset charset).
			$screenBuf .= $bHelp->end();
			unset($bHelp);
			
			// Move cursor to correct line
//			$screenBuf .= KEY_ESCAPE.'['.$this->realWidth.'D'.KEY_ESCAPE.'['.($this->getHeight() - ($screenMargin + 1)).'A';
		}
		else
		// Caption without border?
		if ($this->getCaption() != '')
		{
			$cLen = strlen($this->getCaption());
			$captionX = floor(($this->realWidth - $cLen) / 2);
			$screenBuf .= str_pad('', $captionX, ' ');
			$screenBuf .= str_pad($this->getCaption(), $this->realWidth - $captionX, ' ');

			// Move cursor to correct line
//			$screenBuf .= KEY_ESCAPE.'['.$this->realWidth.'D'.KEY_ESCAPE.'[1B';
		}
		
		return $screenBuf;
	}
	
	public function getRealHeight()
	{
		return $this->realLines;
	}
}

/**
 * ScreenContainer is a base class that can contain other screen objects.
*/
abstract class ScreenContainer extends ScreenObject
{
	protected $screenObjects		= array();
	
	public function add(ScreenObject $object)
	{
		$this->screenObjects[] = $object;
	}
	
	public function getObjectById($objectId)
	{
		foreach ($this->screenObjects as $index => $ob)
		{
			if ($objectId == $ob->getId())
			{
				return $ob;
			}
		}
		return null;
	}
	
	public function remove(ScreenObject $object)
	{
		foreach ($this->screenObjects as $index => $ob)
		{
			if ($object === $ob)
			{
				unset($this->screenObjects[$index]);
				break;
			}
		}
	}

	public function removeById($objectId)
	{
		foreach ($this->screenObjects as $index => $ob)
		{
			if ($objectId == $ob->getId())
			{
				unset($this->screenObjects[$index]);
				break;
			}
		}
	}

	public function draw($parentX, $parentY, $parentWidth, $parentHeight)
	{
		$this->realWidth =
			($this->getWidth() > 0 && $this->getWidth() < $parentWidth) ? 
			$this->getWidth() : 
			$parentWidth;

		$screenMargin = $this->getMargin();
		if ($this->getBorder() || $this->getCaption())
			$screenMargin++;
		
		$screenBuf = '';
		$this->realLines = 0;
		
		$xOffset = $this->getX() + $parentX + $screenMargin;
		$yOffset = $this->getY() + $parentY + $screenMargin;
		foreach ($this->screenObjects as $object)
		{
			// Set the cursor at the position of the next object
			if ($object->getAbsolute())
			{
				$screenBuf .= KEY_ESCAPE.'['.$object->getY().';'.$object->getX().'H';

				// Draw the object
				$screenBuf .= $object->draw($xOffset, $yOffset);
			}
			else
			{
				$screenBuf .= KEY_ESCAPE.'['.($object->getY() + $yOffset).';'.( $object->getX() + $xOffset).'H';

				// Draw the object
				$screenBuf .= $object->draw($xOffset, $yOffset, ($this->getWidth() - $screenMargin*2), ($this->getHeight() - $screenMargin*2));
				$yOffset += $object->getY() + $object->getRealHeight();
				
				$this->realLines += $object->getY() + $object->getRealHeight();
//				console('REAL : '.$object->getRealHeight());
			}
		}
		
		if ($this->getBorder())
			$this->realLines += 2;
		else if ($this->getCaption())
			$this->realLines += 1;
		
		$screenBuf .= KEY_ESCAPE.'['.($this->getY() + $parentY).';'.($this->getX() + $parentX).'H';
		$screenBuf .= $this->drawBorder($screenMargin);

		return $screenBuf;
	}
	
	public function updateTTypes($ttype)
	{
		foreach ($this->screenObjects as $object)
		{
			$object->setTType($ttype);
			if (is_subclass_of($object, 'ScreenContainer'))
				$object->updateTTypes($ttype);
			$object->clearCache();
		}
	}
}

/**
 * The TelnetScreen class is the Parent container that holds all visual components
*/
abstract class TelnetScreen extends ScreenContainer
{
	abstract protected function write($data, $sendQPacket = FALSE);
	
	protected $winSize				= null;
	protected $modeState			= 0;
	
	protected $screenBuf			= '';
	protected $cursorProperties		= 0;
	
	protected function writeBuf($string)
	{
		$this->screenBuf .= $string;
	}
	
	protected function writeLine($line, $crlf = true)
	{
		$this->screenBuf .= $line.(($crlf) ? "\r\n" : '');
	}
	
	protected function writeAt($string, $x, $y)
	{
		$this->screenBuf .= KEY_ESCAPE.'['.$y.';'.$x.'H';
		$this->screenBuf .= $string;
	}
	
	protected function setWinSize($width, $height)
	{
		$firstTime = ($this->winSize === null) ? true : false;
		$this->winSize = array($width, $height);
		$this->setSize($width, $height);
		if (!$firstTime)
			$this->redraw();
	}
		
	protected function setCursorProperties($properties = 0)
	{
		$this->cursorProperties = $properties;
		
		if ($this->ttype == TELNET_TTYPE_XTERM)
		{
			if ($this->cursorProperties & TELNET_CURSOR_HIDE)
				$this->screenBuf .= KEY_ESCAPE.'[?25l';
			else
				$this->screenBuf .= KEY_ESCAPE.'[?25h';
		}
	}

	protected function screenClear($goHome = false)
	{
		$this->screenBuf .= VT100_ED2;
		if ($goHome)
			$this->screenBuf .= VT100_CURSORHOME;
	}
	
	protected function flush()
	{
		if ($this->screenBuf)
			$this->write($this->screenBuf);
		$this->screenBuf = '';
	}
	
	protected function redraw()
	{
		// Clear Screen
		$this->screenBuf .= VT100_ED2;
		
		// Draw components
		$this->screenBuf .= $this->draw(1, 1, $this->getWidth(), $this->getHeight());
		
		// Park cursor?
		if (($this->modeState & TELNET_MODE_LINEEDIT) == 0 && $this->ttype != TELNET_TTYPE_XTERM)
			$this->screenBuf .= KEY_ESCAPE.'['.$this->winSize[1].';'.$this->winSize[0].'H';
		
		// Flush buffer to client
		$this->flush();
	}
	
	protected function clearObjects($clearScreen = false)
	{
		$this->screenObjects = array();
		if ($clearScreen)
			$this->screenClear(true);
	}
}

class TSTextArea extends ScreenObject
{
	protected $text		= '';
	
	public function __construct($x = 0, $y = 0, $cols = 0, $lines = 0)
	{
		$this->setLocation($x, $y);
		$this->setSize($cols, $lines);
	}
	
	public function getText()
	{
		return $this->text;
	}
	
	public function setText($text)
	{
		$this->text			= $text;
		$this->screenCache	= '';
	}
	
	public function draw($parentX, $parentY, $parentWidth, $parentHeight)
	{
		if ($this->screenCache != '')
			return $this->screenCache;
		
		$this->realWidth =
			($this->getWidth() > 0 && $this->getWidth() < $parentWidth) ? 
			$this->getWidth() : 
			$parentWidth;
		
		$screenBuf = '';
		$screenMargin = $this->getMargin();
		if ($this->getBorder() || $this->getCaption())
		{
			$screenMargin++;
			$screenBuf .= KEY_ESCAPE.'[1B';
		}

		// Draw content (text)
		$len = 0;
		$this->realLines = $screenMargin;
		foreach ($this->prepareTags() as $word)
		{
			$wLen = strlen($word[0]);
			
			// If regular word, check for line wrapping and such
			if ($word[1] == 0)
			{
				// Space between words
				if ($len > $screenMargin && $len < $this->realWidth - $screenMargin)
				{
					$screenBuf .= ' ';
					$len++;
				}
	
				// Line wrap?
				if ($len + $wLen > $this->realWidth - $screenMargin || $word[0] == KEY_ENTER)
				{
					// Padding until the end of cols
					while ($len < $this->realWidth - $screenMargin)
					{
						$screenBuf .= ' ';
						$len++;
					}
					
					// Stop if we've ran out of space
					if (++$this->realLines == $this->realWidth - $screenMargin)
						break;
					
					// Line wrap
					$screenBuf .= KEY_ESCAPE.'['.$len.'D'.KEY_ESCAPE.'[1B';
					$len = 0;
					
					if ($word[0] == KEY_ENTER)
						continue;
				}

				// compensate for (left)margin?
				if ($len == 0 && $screenMargin > 0) {
					$screenBuf .= KEY_ESCAPE.'['.$screenMargin.'C';
					$len += $screenMargin;
				}
				
				$len += $wLen;
				$screenBuf .= $word[0];
			}
			else
			{
				// compensate for (left)margin?
				if ($len == 0 && $screenMargin > 0) {
					$screenBuf .= KEY_ESCAPE.'['.$screenMargin.'C';
					$len += $screenMargin;
				}
				
				$screenBuf .= $word[0];
			}
		}
		
		$this->realLines++;

		// Padding until the end of cols
		while ($len < $this->realWidth - $screenMargin)
		{
			$screenBuf .= ' ';
			$len++;
		}
		
		if ($this->getBorder() || $this->getCaption())
		{
			// Move cursor back to beginning
			$screenBuf .= KEY_ESCAPE.'['.($this->realWidth - 1).'D'.KEY_ESCAPE.'['.($this->realLines - $screenMargin).'A';
			
			$this->realLines += $screenMargin;
			if ($this->realLines < $this->getHeight())
				$this->realLines = $this->getHeight();

			$screenBuf .= $this->drawBorder($screenMargin);
		}
		else
		{
			if ($this->realLines < $this->getHeight())
				$this->realLines = $this->getHeight();
		}

		$this->screenCache = $screenBuf;
		return $screenBuf;
	}
	
	// Split style tags into their own entry in $words and mark them as such
	private function prepareTags()
	{
		$words = explode(' ', $this->text);
		$out = array();

		foreach ($words as $word)
		{
			$matches = array();
			if (preg_match_all('/'.KEY_ESCAPE.'\[(\d*)m/', $word, $matches, PREG_OFFSET_CAPTURE))
			{
				$cutOffset = 0;
				foreach ($matches[0] as $match)
				{
					$mLen = strlen($match[0]);
					$match[1] -= $cutOffset;
					
					// Do we have chars BEFORE this tag? (that means regular chars)
					if ($match[1] > 0)
					{
						// Split those regular chars into its own array entry
						$exp = explode(KEY_ENTER, substr($word, 0, $match[1]));
						foreach ($exp as $i => $e)
						{
							if ($i > 0)
								$out[] = array(KEY_ENTER, 0);
							$out[] = array($e, 0);
						}
						
						// Then the tag
						$out[] = array($match[0], 1);

						$word = substr($word, $match[1] + $mLen);
						$cutOffset += $match[1] + $mLen;
					}
					else
					{
						$out[] = array($match[0], 1);
						$word = substr($word, $mLen);
						$cutOffset += $mLen;
					}
				}
				// Are there still regular chars after all the tags?
				if ($word != '')
				{
					$exp = explode(KEY_ENTER, $word);
					foreach ($exp as $i => $e)
					{
						if ($i > 0)
							$out[] = array(KEY_ENTER, 0);
						$out[] = array($e, 0);
					}
				}
			}
			else
			{
				// Regular word
				$exp = explode(KEY_ENTER, $word);
				foreach ($exp as $i => $e)
				{
					if ($i > 0)
						$out[] = array(KEY_ENTER, 0);
					$out[] = array($e, 0);
				}
			}
		}
		
		return $out;
	}
}

class TSTextInput extends TSTextArea
{
	private $submitCallback		= null;
	private $focus				= false;
	
	public function setSubmitCallback($class, $func = null)
	{
		if (!$class || !$func)
			$this->submitCallback = null;
		else
			$this->submitCallback = array($class, $func);
	}
	
	// handleSubmit will be the callback for the line edit mode listener
	public function handleSubmit($text)
	{
		
	}
	
	public function hasFocus()
	{
		return $this->focus;
	}
}

define('TS_BORDER_NONE',		0);
define('TS_BORDER_REGULAR',		1);
define('TS_BORDER_DOUBLE',		2);
define('TS_BORDER_NUMTYPES',	3);

define('TC_BORDER_TOPLEFT',		0);
define('TC_BORDER_TOPRIGHT',	1);
define('TC_BORDER_BOTTOMLEFT',	2);
define('TC_BORDER_BOTTOMRIGHT',	3);
define('TC_BORDER_HORILINE',	4);
define('TC_BORDER_VERTLINE',	5);

class ScreenBorderHelper
{
	private $ttype		= 0;
	
	public function __construct($ttype)
	{
		$this->ttype	= $ttype;
	}
	
	public function start()
	{
		if ($this->ttype == TELNET_TTYPE_XTERM)
			return VT100_STYLE_RESET.VT100_USG0_LINE;

		return '';
	}
	
	public function end()
	{
		if ($this->ttype == TELNET_TTYPE_XTERM)
			return VT100_STYLE_RESET.VT100_USG0;

		return '';
	}
	
	public function getChar($type)
	{
		switch($type)
		{
			case TC_BORDER_TOPLEFT :
				if ($this->ttype == TELNET_TTYPE_XTERM)
					return chr(108);
				else if ($this->ttype == TELNET_TTYPE_ANSI)
					return chr(218);
				else
					return '/';

			case TC_BORDER_TOPRIGHT :
				if ($this->ttype == TELNET_TTYPE_XTERM)
					return chr(107);
				else if ($this->ttype == TELNET_TTYPE_ANSI)
					return chr(191);
				else
					return '\\';

			case TC_BORDER_BOTTOMLEFT :
				if ($this->ttype == TELNET_TTYPE_XTERM)
					return chr(109);
				else if ($this->ttype == TELNET_TTYPE_ANSI)
					return chr(192);
				else
					return '\\';

			case TC_BORDER_BOTTOMRIGHT :
				if ($this->ttype == TELNET_TTYPE_XTERM)
					return chr(106);
				else if ($this->ttype == TELNET_TTYPE_ANSI)
					return chr(217);
				else
					return '/';

			case TC_BORDER_HORILINE :
				if ($this->ttype == TELNET_TTYPE_XTERM)
					return chr(113);
				else if ($this->ttype == TELNET_TTYPE_ANSI)
					return chr(196);
				else
					return '-';

			case TC_BORDER_VERTLINE :
				if ($this->ttype == TELNET_TTYPE_XTERM)
					return chr(120);
				else if ($this->ttype == TELNET_TTYPE_ANSI)
					return chr(179);
				else
					return '|';
		}
		
		return '*';
	}
}

?>