<?php

/**
 * ScreenObject is the base class for all screen components
 * (ScreenContainer, TextLine, TextArea, etc)
*/
abstract class ScreenObject
{
	abstract public function draw($parentX, $parentY);

	protected $id			= '';
	protected $x			= 0;
	protected $y			= 0;
	protected $cols			= 1;			// width
	protected $lines		= 1;			// height
	
	// object styles must go here
	// ...

	public function setId($id)
	{
		$this->id = $id;
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	public function setLocation($x, $y)
	{
		$x = (int) $x;
		if ($x < 1)
			$x = 1;
		$y = (int) $y;
		if ($y < 1)
			$y = 1;
		
		$this->x			= $x;
		$this->y			= $y;
	}
	
	public function getX()
	{
		return $this->x;
	}
	
	public function getY()
	{
		return $this->y;
	}
	
	public function setSize($cols, $lines)
	{
		$this->cols			= $cols;
		$this->lines		= $lines;
	}
	
	public function setWidth($cols)
	{
		$this->cols			= $cols;
	}

	public function setHeight($lines)
	{
		$this->lines		= $lines;
	}
}

/**
 * ScreenContainer is a base class that can contain other screen objects.
*/
abstract class ScreenContainer extends ScreenObject
{
	protected $screenObjects		= array();

	protected function add(ScreenObject $object)
	{
		if (!in_array($object, $this->screenObjects))
			$this->screenObjects[] = $object;
	}
	
	protected function getObjectById($objectId)
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
	
	protected function remove(ScreenObject $object)
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

	protected function removeById($objectId)
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

	public function draw($parentX, $parentY)
	{
		$screenBuf = '';
		$xOffset = $this->x + $parentX;
		$yOffset = $this->y + $parentY;

		// Draw own style (backgroud? border?)
		// ...
			
		foreach ($this->screenObjects as $object)
		{
			// Draw the object
			$screenBuf .= KEY_ESCAPE.'['.($object->getY() + $xOffset).';'.($object->getX() + $yOffset).'H';
			$screenBuf .= $object->draw($xOffset, $yOffset);
		}
		
		return $screenBuf;
	}
}

/**
 * The TelnetScreen class is the Parent container that holds all visual components
*/
abstract class TelnetScreen extends ScreenContainer
{
	abstract protected function write($data, $sendQPacket = FALSE);
	
	protected $ttype				= '';
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
		if (!$firstTime)
			$this->redraw();
	}
		
	protected function setCursorProperties($properties = 0)
	{
		$this->cursorProperties = $properties;
		
		if (strpos($this->ttype, 'XTERM') !== false)
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
		$this->screenBuf .= $this->draw($this->x, $this->y);
		
		// Park cursor?
		if (($this->modeState & TELNET_MODE_LINEEDIT) == 0 && strpos($this->ttype, 'XTERM') === false)
			$this->screenBuf .= KEY_ESCAPE.'['.$this->winSize[1].';'.$this->winSize[0].'H';
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
	private $text		= '';
	
	public function __construct($x = 0, $y = 0, $cols = 20, $lines = 1)
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
		$this->text		= $text;
	}
	
	public function draw($parentX, $parentY)
	{
		$screenBuf = '';
		
		// Draw own style (backgroud? border?)
		// ...

		// Draw content (text)
		$len = 0;
		$line = 0;
		$words = $this->prepareTags(explode(' ', $this->text));
		foreach ($words as $word)
		{
			$wlen = strlen($word[0]);

			// If regular word, check for line wrapping and such
			if ($word[1] == 0)
			{
				// Space between words
				if ($len > 0 && $len < $this->cols)
				{
					$screenBuf .= ' ';
					$len++;
				}
	
				// Line wrap?
				if ($len + $wlen > $this->cols)
				{
					// Padding until the end of cols
					while ($len < $this->cols)
					{
						$screenBuf .= ' ';
						$len++;
					}
					
					// Stop if we've ran out of space
					if (++$line == $this->lines)
						break;
					
					// Line wrap
					$screenBuf .= KEY_ESCAPE.'['.$len.'D'.KEY_ESCAPE.'[1B';
					$len = 0;
				}
				$len += $wlen;
			}
			$screenBuf .= $word[0];
		}

		// Padding until the end of cols
		for ($a=$len; $a<$this->cols; $a++)
			$screenBuf .= ' ';
		
		return $screenBuf;
	}
	
	// Split style tags into their own entry in $words and mark them as such
	private function prepareTags(array $words)
	{
		$out = array();
		
		foreach ($words as $word)
		{
			$matches = array();
			if (preg_match_all('/'.KEY_ESCAPE.'\[(\d*)m/', $word, $matches, PREG_OFFSET_CAPTURE))
			{
				$cutOffset = 0;
				foreach ($matches[0] as $match)
				{
					
					// Do we have chars BEFORE this tag? (that means regular chars
					if ($match[1] - $cutOffset > 0)
					{
						// Split those regular chars into its own array entry
						$out[] = array(substr($word, 0, ($match[1] - $cutOffset)), 0);
						
						// Then the tag
						$out[] = array($match[0], 1);

						$word = substr($word, ($match[1] - $cutOffset) + strlen($match[0]));
						$cutOffset += ($match[1] - $cutOffset) + strlen($match[0]);
					}
					else
					{
						$out[] = array($match[0], 1);
						$word = substr($word, strlen($match[0]));
						$cutOffset += strlen($match[0]);
					}
				}
				if ($word != '')
					$out[] = array($word, 0);
			}
			else
			{
				$out[] = array($word, 0);
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

//class TSTextArea
//{
//	private $scrHandle	= null;
//	private $x			= 0;
//	private $y			= 0;
//	private $cols		= 0;
//	private $lines		= 0;
//	
//	private $text		= '';
//	
//	public function __construct($scrHandle, $x = 0, $y = 0, $cols = 20, $lines = 1)
//	{
//		$this->scrHandle = $scrHandle;
//		$this->setLocation($x, $y);
//		$this->setSize($cols, $lines);
//	}
//	
//	public function setLocation($x, $y)
//	{
//		$this->x		= $x;
//		$this->y		= $y;
//	}
//	
//	public function setSize($cols, $lines)
//	{
//		$this->cols		= $cols;
//		$this->lines	= $lines;
//	}
//	
//	public function setText($text)
//	{
//		$this->text		= $text;
//	}
//}

?>