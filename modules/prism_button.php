<?php
/**
 * Overwriting some methods. Should probably be merged into ISP_BTN.
 */
class Button extends IS_BTN
{
	private $key;
	private $group;
	
	private $onClick;
	private $onText;
	
	public static $TO_ALL = 255;
	public static $TO_LOCAL = 0;	
	
	public function __construct($UCID = 0, $key = NULL, $group = NULL)
	{
		$this->key = $key;
		$this->group = $group;
		$this->UCID = $UCID;
		$this->ClickID = -1;
	}
	
	public function send($hostId = NULL)
	{
		$id = ButtonManager::registerButton($this, $hostId, $this->key, $this->group);
		
		if ($id !== false)
		{
			if (is_numeric($id))
			{
				$this->ReqI = $id + 1; // may not be zero -_-
				$this->ClickID = $id;
			}
			parent::send($hostId);
		}
	}
	
	public function registerOnClick(Plugins $plugin, $methodName, $params = NULL)
	{
		$this->onClick = array($plugin, $methodName, $params);
		$this->BStyle |= ISB_CLICK;
	}
	public function click(IS_BTC $BTC)
	{
		if (!is_array($this->onClick))
			return;

		switch (count($this->onClick))
		{
			case 3:
				call_user_func_array(array($this->onClick[0], $this->onClick[1]), $this->onClick[2]);
			break;
			case 2:
			default:
				call_user_func($this->onClick, $BTC, $this);
			break;
		}
	}
	public function registerOnText(Plugins $plugin, $methodName, $maxLength = 95)
	{
		if ($maxLength < 0 || $maxLength > 95) {
			$this->TypeIn = 95;
		}
		else {
			$this->TypeIn = $maxLength;
		}
		$this->onText = array($plugin, $methodName);
		$this->BStyle |= ISB_CLICK;
	}
	public function enterText(IS_BTT $BTT)
	{
		if (is_array($this->onText)) {
			call_user_func($this->onText, $BTT, $this);
		}
	}
	
	public function delete($hostId = NULL)
	{
		return ButtonManager::removeButton($this, $hostId);
	}
	
	public function UCID($val)
	{
		console("ERROR: UCID may only be set in constructor!");
		return $this;
	}
	public function ReqI($val)
	{
		console("ERROR: Do not set ReqI manually!");
		return $this;
	}
	public function ClickID($val)
	{
		console("ERROR: Do not set ClickID manually!");
		return $this;
	}
	public function key()
	{
		return $this->key;
	}
	public function group()
	{
		return $this->group;
	}
}