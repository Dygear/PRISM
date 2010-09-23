<?php

class TSAccountSection extends TSSection
{
	public function __construct($width, $height, $ttype = 0)
	{
		$this->setLocation(1, 3);
		$this->setSize($width, $height);
		$this->setTType($ttype);
		$this->setId('accounts');
//		$this->setBorder(TS_BORDER_REGULAR);

		$this->createMenu();
		
		// Menu / content separator line
		$vertLine = new TSVLine(18, 3, 20);
		$vertLine->setTType($ttype);
		$this->add($vertLine);
	}
	
	public function handleKey($key)
	{
		switch($key)
		{
			case KEY_CURUP :
				$newItem = $this->previousItem();
				break;
			
			case KEY_CURDOWN :
				$newItem = $this->nextItem();
				break;
			
			default :
				return false;
		}
		
		// Do something on the new item
		//$newItem = ...
		
		return true;
	}
	
	private function createMenu()
	{
		$textArea = new TSTextArea(2, 4, 15, 1);
		$textArea->setId('accountCreate');
		$textArea->setText('Create');
		$textArea->setOptions(TS_OPT_ISSELECTABLE | TS_OPT_ISSELECTED);
		$this->add($textArea);

		$textArea = new TSTextArea(2, 5, 15, 1);
		$textArea->setId('accountPass');
		$textArea->setText('Change password');
		$textArea->setOptions(TS_OPT_ISSELECTABLE);
		$this->add($textArea);

		$textArea = new TSTextArea(2, 6, 15, 1);
		$textArea->setId('accountFlags');
		$textArea->setText('Change flags');
		$textArea->setOptions(TS_OPT_ISSELECTABLE);
		$this->add($textArea);

		$textArea = new TSTextArea(2, 7, 15, 1);
		$textArea->setId('accountDelete');
		$textArea->setText('Delete');
		$textArea->setOptions(TS_OPT_ISSELECTABLE);
		$this->add($textArea);

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