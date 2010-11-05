<?php
/**
 * PHPInSimMod - Telnet Module
 * @package PRISM
 * @subpackage Telnet
*/

class TSPluginSection extends TSSection
{
	public function __construct(ScreenContainer $parentSection, $width, $height, $ttype = 0)
	{
		parent::__construct($parentSection);
		
		$this->setLocation(1, 3);
		$this->setSize($width, $height);
		$this->setTType($ttype);
		$this->setId('plugins');
//		$this->setBorder(TS_BORDER_REGULAR);
	}
	
	public function __destruct()
	{
		parent::__destruct();
	}
	
	public function handleKey($key)
	{
		switch($key)
		{
			case KEY_CURUP :
				$this->previousItem();
				break;
			
			case KEY_CURDOWN :
				$this->nextItem();
				break;
			
			default :
				return false;
		}
		
		return true;
	}

	protected function selectItem()
	{
		
	}

	protected function setInputMode()
	{
		$object = $this->getCurObject();
		switch ($object->getId())
		{
			default :
				$this->setInputCallback(null);
				break;
		}
	}
	
	private function createMenu()
	{
//		$textArea = new TSTextArea();
//		$textArea->setId('accounts');
//		$textArea->setText(VT100_STYLE_BOLD.'A'.VT100_STYLE_RESET.'ccounts');
//		$textArea->setOptions(TS_OPT_ISSELECTABLE | TS_OPT_ISSELECTED);
//		$this->add($textArea);
//
//		$textArea = new TSTextArea();
//		$textArea->setId('hosts');
//		$textArea->setText(VT100_STYLE_BOLD.'H'.VT100_STYLE_RESET.'osts');
//		$textArea->setOptions(TS_OPT_ISSELECTABLE);
//		$this->add($textArea);
//
//		$textArea = new TSTextArea();
//		$textArea->setId('plugins');
//		$textArea->setText(VT100_STYLE_BOLD.'P'.VT100_STYLE_RESET.'lugins');
//		$textArea->setOptions(TS_OPT_ISSELECTABLE);
//		$this->add($textArea);
	}
}

?>