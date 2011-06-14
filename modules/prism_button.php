<?php
/**
 * Overwriting some methods. Should probably be merged into ISP_BTN.
 */
class Button extends IS_BTN
{
	private $key;
	private $onClick;
	private $onText;
	
	public static $TO_ALL = 255;
	public static $TO_LOCAL = 0;
	
	public function __construct($key, $UCID = 0, $hostID = NULL)
	{
		$this->key = $key;
		$this->UCID = $UCID;
	}
	
	public function send($hostId = NULL)
	{
		ButtonManager::registerButton($this, $hostId);
		parent::send($hostId);
	}
	
	public function registerOnClick(Plugins $plugin, $methodName)
	{
		$this->onClick = array($plugin, $methodName);
		$this->BStyle |= ISB_CLICK;
	}
	public function registerOnText(Plugins $plugin, $methodName)
	{
		$this->onClick = array($plugin, $methodName);
	}
	public function delete($hostId = NULL)
	{
		return ButtonManager::removeButton($this, $hostId);
	}
	
	public function UCID($val)
	{
		echo "UCID may only be set in constructor!";
		return $this;
	}
	public function ReqI($val)
	{
		echo "Do not set ClickID manually!";
		return $this;
	}
	public function ClickID($val)
	{
		echo "Do not set ClickID manually!";
		return $this;
	}
}
?>