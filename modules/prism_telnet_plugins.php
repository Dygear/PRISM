<?php

class TSPluginSection extends TSSection
{
	public function __construct($width, $height, $ttype = 0)
	{
		$this->setLocation(1, 3);
		$this->setSize($width, $height);
		$this->setTType($ttype);
		$this->setId('plugins');
//		$this->setBorder(TS_BORDER_REGULAR);
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