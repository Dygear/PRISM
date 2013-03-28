<?php
/**
 * PHPInSimMod - Telnet Module
 * @package PRISM
 * @subpackage Telnet
*/

// Screen object options
define('TS_OPT_ISSELECTABLE', 1);
define('TS_OPT_ISSELECTED', 2);
define('TS_OPT_ISEDITABLE', 4);
define('TS_OPT_HASBACKGROUND', 8);
define('TS_OPT_BOLD', 16);

/**
 * ScreenObject is the base class for all screen components
 * (ScreenContainer, TextLine, TextArea, etc)
*/
abstract class ScreenObject
{
	abstract public function draw();

	private $id				= '';
	private $x				= 0;
	private $y				= 0;
	private $absolute		= false;			// Absolute position or relative to parent
	private $cols			= 0;				// width
	protected $realWidth	= 0;
	private $lines			= 0;				// height
	protected $realHeight	= 0;
	
	private $ttype			= 0;
	private $visible		= true;
	private $border			= TS_BORDER_NONE;	// border type
	private $margin			= 0;				// border margin
	private $caption		= '';
	private $options		= 0;				// Selectable, selected, has background, editable, etc
	
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
	
	public function setVisible($visible)
	{
		$this->visible = $visible;
	}
	
	public function isVisible()
	{
		return $this->visible;
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
	
	public function getRealWidth()
	{
		return $this->realWidth;
	}

	public function getRealHeight()
	{
		return $this->realHeight;
	}
	
	public function setBorder($border)
	{
		$border = (int) $border;
		if ($border < 0 || $border > TS_BORDER_NUMTYPES)
			$border = 0;
		$this->border = $border;
		$this->screenCache	= '';
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
		$this->screenCache	= '';
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
	
	public function setOptions($options)
	{
		$this->options = $options;
		$this->screenCache = '';
	}
	
	public function getOptions()
	{
		return $this->options;
	}
	
	public function toggleSelected()
	{
		if ($this->options & TS_OPT_ISSELECTABLE)
		{
			if ($this->options & TS_OPT_ISSELECTED)
				$this->options &= ~TS_OPT_ISSELECTED;
			else
				$this->options |= TS_OPT_ISSELECTED;
			$this->screenCache = '';
		}
	}
	
	public function setSelected($selected)
	{
		if ($selected)
			$this->options |= TS_OPT_ISSELECTED;
		else
			$this->options &= ~TS_OPT_ISSELECTED;
		$this->screenCache = '';
	}
	
	public function setBold($bold)
	{
		if ($bold)
			$this->options |= TS_OPT_BOLD;
		else
			$this->options &= ~TS_OPT_BOLD;
	}
	
	public function clearCache()
	{
		$this->screenCache = '';
	}
	
	protected function drawBorder()
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
		}
		else
		// Caption without border?
		if ($this->getCaption() != '')
		{
			$cLen = strlen($this->getCaption());
			$captionX = floor(($this->realWidth - $cLen) / 2);
			$screenBuf .= str_pad('', $captionX, ' ');
			$screenBuf .= str_pad($this->getCaption(), $this->realWidth - $captionX, ' ');
		}
		
		return $screenBuf;
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

	public function removeAll()
	{
		$this->screenObjects = array();
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
	
	public function getObjectByIndex($index)
	{
		if (isset($this->screenObjects[$index]))
			return $this->screenObjects[$index];
		return null;
	}
	
	public function getNumObjects()
	{
		return count($this->screenObjects);
	}
	
	public function draw()
	{
		$screenBuf = '';
		$this->realWidth = $this->getWidth();
		$this->realHeight = $this->getHeight();
		
		foreach ($this->screenObjects as $object)
		{
			if (!$object->isVisible())
				continue;
			
			// Draw the object and place it on its x and y
			$screenBuf .= KEY_ESCAPE.'['.$object->getY().';'.$object->getX().'H';
			$screenBuf .= $object->draw();
		}
		
		if ($this->getBorder())
		{
			$screenBuf .= KEY_ESCAPE.'['.$this->getY().';'.$this->getX().'H';
			$screenBuf .= $this->drawBorder();
		}

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
	
	private $postCurPos				= null;
	
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
		
		if ($this->getTType() == TELNET_TTYPE_XTERM)
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
	
	public function setPostCurPos(array $curPos)
	{
		if (!isset($curPos[0]))
			$this->postCurPos = null;
		else
			$this->postCurPos = $curPos;
	}
	
	protected function redraw()
	{
		// Clear Screen
		$this->screenBuf .= VT100_ED2;
		
		// Draw components
		$this->screenBuf .= $this->draw();
		
		// Park cursor?
		if ($this->postCurPos !== null)
		{
			$this->screenBuf .= KEY_ESCAPE.'['.$this->postCurPos[1].';'.$this->postCurPos[0].'H';
		}
		else
		{
			if (($this->modeState & TELNET_MODE_LINEEDIT) == 0 && $this->getTType() != TELNET_TTYPE_XTERM)
				$this->screenBuf .= KEY_ESCAPE.'[0;'.($this->getWidth() - 1).'H';
//				$this->screenBuf .= KEY_ESCAPE.'['.$this->winSize[1].';'.$this->winSize[0].'H';
		}
		
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
	
	public function draw()
	{
		if ($this->screenCache != '')
			return $this->screenCache;
		
		$screenBuf = '';
		$screenMargin = 0;
		$pos = 0;
		$this->realWidth = 0;
		$this->realHeight = 0;

		if ($this->getBorder() || $this->getCaption())
		{
			// Increase screenMargin by one, to indicate this object will be surrounded by one 'pixel'
			$screenMargin++;
			
			// move the cursor down a line, for content. We'll draw the border after that.
			$screenBuf .= KEY_ESCAPE.'[1B';
			
			// Count this top line
			$this->realHeight++;
		}

		$style = '';
		if (($this->getOptions() & TS_OPT_ISEDITABLE) == 0 && ($this->getOptions() & TS_OPT_HASBACKGROUND || $this->getOptions() & TS_OPT_ISSELECTED))
			$style .= VT100_STYLE_REVERSE;
		if ($this->getOptions() & TS_OPT_BOLD)
			$style .= VT100_STYLE_BOLD;

		$screenBuf .= $style;

		// Draw content (text)
		foreach ($this->prepareTags() as $word)
		{
			$wLen = strlen($word[0]);
			
			// If regular word, check for line wrapping and such
			if ($word[1] == 0)
			{
				// Skip space at start of line (after line wrap)?
				if ($pos <= $screenMargin && $word[0] == '')
					continue;
	
				// Line wrap?
				if ($pos + $wLen > $this->getWidth() - $screenMargin || $word[0] == KEY_ENTER)
				{
					// Padding until the end of cols
					while ($pos < $this->getWidth() - $screenMargin)
					{
						$screenBuf .= ' ';
						$pos++;
						if ($pos > $this->realWidth)
							$this->realWidth = $pos;
					}
					
					// Stop if we've ran out of space (include screenMargin to check for bottom border
					if (++$this->realHeight == $this->getHeight())
						break;
					
					// Line wrap
					$screenBuf .= KEY_ESCAPE.'['.$pos.'D'.KEY_ESCAPE.'[1B';
					$pos = 0;
					
					if ($word[0] == KEY_ENTER || $word[0] == ' ')
						continue;
				}

				// compensate for (left)margin?
				if ($pos == 0 && $screenMargin > 0) {
					$screenBuf .= KEY_ESCAPE.'['.$screenMargin.'C';
					$pos += $screenMargin;
					if ($pos > $this->realWidth)
						$this->realWidth = $pos;
				}
				
				$pos += $wLen;
				$screenBuf .= $word[0];

				if ($pos > $this->realWidth)
					$this->realWidth = $pos;
			}
			else
			{
				// Add style tag (not a word)
				$screenBuf .= $word[0];

				// Reactivate background after a style reset?
				if ($word[0] == VT100_STYLE_RESET)
					$screenBuf .= $style;
			}
		}
		
		// Padding until the end of cols
		while ($pos < $this->getWidth() - $screenMargin)
		{
			$screenBuf .= ' ';
			$pos++;
			if ($pos > $this->realWidth)
				$this->realWidth = $pos;
		}

		// Turn off background?
		if ($style != '')
			$screenBuf .= VT100_STYLE_RESET;

		// Still have to count the last line we drew
		$this->realHeight++;

		// If there's a border, increase realWidth by one (to include right border)
		if ($this->getBorder())
		{
			$this->realWidth++;
		}

		// If we have to draw a border or caption, do so here
		if ($this->getBorder() || $this->getCaption())
		{
			// Compesate realHeight
			if ($this->getBorder())
				$this->realHeight += 1;

			$screenBuf .= KEY_ESCAPE.'['.($this->realWidth - 1).'D'.KEY_ESCAPE.'['.($this->realHeight - 2).'A';
			$screenBuf .= $this->drawBorder();
			
		}

		//console('object width : '.$this->realWidth.' | object height : '.$this->realHeight);

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
			
			// Add the space between words to $out (it was stripped in the explode())
			$out[] = array(' ', 0);
		}
		array_pop($out);
		
		return $out;
	}
}

class TSTextInput extends TSTextArea
{
	private $maxLength	= 24;
	
	public function setMaxLength($maxLength)
	{
		$this->maxLength = (int) $maxLength;
	}
	
	public function getMaxLength()
	{
		return $this->maxLength;
	}
}

class TSHLine extends ScreenObject
{
	public function __construct($x, $y, $width)
	{
		$this->setLocation($x, $y);
		$this->setWidth($width);
	}
	
	public function draw()
	{
		$bHelp = new ScreenBorderHelper($this->getTType());
		
		$screenBuf = $bHelp->start();
		for ($a=0; $a<$this->getWidth(); $a++)
			$screenBuf .= $bHelp->getChar(TC_BORDER_HORILINE);
		$screenBuf .= $bHelp->end();
		
		return $screenBuf;
	}
}

class TSVLine extends ScreenObject
{
	public function __construct($x, $y, $height)
	{
		$this->setLocation($x, $y);
		$this->setHeight($height);
	}
	
	public function draw()
	{
		$bHelp = new ScreenBorderHelper($this->getTType());
		
		$screenBuf = $bHelp->start();
		for ($a=0; $a<$this->getHeight(); $a++)
		{
			$screenBuf .= $bHelp->getChar(TC_BORDER_VERTLINE);
			$screenBuf .= KEY_ESCAPE.'[B'.KEY_ESCAPE.'[D';
		}
		$screenBuf .= $bHelp->end();
		
		return $screenBuf;
	}
}

abstract class TSSection extends ScreenContainer
{
	abstract public function handleKey($key);
	abstract protected function selectItem();
	abstract protected function setInputMode();
	
	// Section info
	private $active			= false;		// Whether this section has KB focus
	private $curItem		= -1;			// pointer to selected item
	protected $subSection	= null;			// This holds the currently selected subsection object (another TSSection)
	
	protected $parentSection	= null;			// Parent section object, so we can recursively go down AND up
	
	public function __construct(ScreenContainer $parentSection)
	{
		$this->parentSection = $parentSection;
	}
	
	public function __destruct()
	{
		$this->subSection = null;
		$this->parentSection = null;
	}
	
	protected function resetSection($hard = false)
	{
		$this->curItem = -1;
		
		if ($hard)
		{
			
		}
	}
	
	protected function setInputCallback($class, $func = null, $editMode = 0, array $curPos = array(0, 0), $defaultText = '', $maxLength = 23)
	{
		if (get_class($this->parentSection) == 'PrismTelnet')
		{
			if ($class === null)
			{
				$this->parentSection->registerInputCallback($this->parentSection, 'handleKey');
				$this->parentSection->setPostCurPos(array());
			}
			else
			{
				$this->parentSection->registerInputCallback($class, $func, $editMode);
				$this->parentSection->setLineBuffer($defaultText);
				$this->parentSection->setInputBufferMaxLen($maxLength);
				if ($editMode)
					$this->parentSection->setPostCurPos($curPos);
				else
					$this->parentSection->setPostCurPos(array());
			}
//			console('Recursive : found final parent');
		}
		else
		{
			$this->parentSection->setInputCallback($class, $func, $editMode, $curPos, $defaultText, $maxLength);
//			console('Recursive : continuing up');
		}
	}
	
	protected function getLine()
	{
		if (get_class($this->parentSection) == 'PrismTelnet')
		{
			return $this->parentSection->getLine(true);
		}
		else
		{
			return $this->parentSection->getLine();
		}
	}
	
	public function setActive($active)
	{
		$this->active = (boolean) $active;
		if ($this->getCurObject() === null)
			return;
		
		if ($active)
		{
			$this->getCurObject()->setSelected(true);
//			console('ACTIVATING '.$this->getCurObject()->getId());
			$this->setInputMode();
		}
		else
		{
			$this->getCurObject()->setSelected(false);
			$this->setInputCallback(null);
//			console('DE-ACTIVATING'.$this->getCurObject()->getId());
		}
	}
	
	public function getActive()
	{
		return $this->active;
	}
	
	protected function getCurObject()
	{
		if ($this->curItem == -1)
			return $this->nextItem(true);
		return $this->getObjectByIndex($this->curItem);
	}
	
	protected function nextItem($first = false)
	{
		// find selected object
		$old = null;
		$a = ($this->curItem < 0) ? 0 : $this->curItem;
		while ($object = $this->getObjectByIndex($a))
		{
			if ($old === null)
			{
				if ($first && $object->getOptions() & TS_OPT_ISSELECTABLE)
				{
					return $object;
				}
				
				if ($object->getOptions() & TS_OPT_ISSELECTED)
				{
					$old = $object;
				}
			}
			else
			{
				if ($object->getOptions() & TS_OPT_ISSELECTABLE)
				{
					// Input TextArea lost focus 'the good way' - we need to grab linebufer and store it in old object
					if ($old->getOptions() & TS_OPT_ISEDITABLE)
					{
						$old->setText($this->getLine());
					}
					$old->toggleSelected();
					$object->toggleSelected();
					$this->curItem = $a;
					return $object;
				}
			}
			
			$a++;
		}
		
		return null;
	}
	
	protected function previousItem()
	{
		$old = null;
		$a = ($this->curItem < 0) ? ($this->getNumObjects() -1) : $this->curItem;
		while ($object = $this->getObjectByIndex($a))
		{
			if ($old === null)
			{
				if ($object->getOptions() & TS_OPT_ISSELECTED)
				{
					$old = $object;
				}
			}
			else
			{
				if ($object->getOptions() & TS_OPT_ISSELECTABLE)
				{
					// Input TextArea lost focus 'the good way' - we need to grab linebufer and store it in old object
					if ($old->getOptions() & TS_OPT_ISEDITABLE)
					{
						$old->setText($this->getLine());
					}
					$old->toggleSelected();
					$object->toggleSelected();
					$this->curItem = $a;
					return $object;
				}
			}

			$a--;
		}

		return null;
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