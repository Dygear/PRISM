<?php
/**
 * Button Tests.
 * 
 * Flow:
 * On new Connection a few Buttons are added to the new users screen:
 * - buttonTests_bg: a dark background for the other buttons
 * - buttonTests_hdr: headline 'Welcome'
 * - buttonTests_name: The driver's name
 * - buttonTests_next: Next button
 * - buttonTests_chng_name: text input test
 * 
 * Actions for next button:
 * 1. click updates some texts, removes a button and changes the callback action
 * 2. click removes the rest of the buttons 
 * 
 * Actions for text input button:
 * enter text -> change name
 */
class buttonTests extends Plugins
{
	const URL = 'http://www.lfsforum.net/showthread.php?t=74858';
	const NAME = 'Button Tests';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = '"Unit" Testing for the button management';

	public function __construct()
	{
		if (ButtonManager::reserveArea(50, 50, 50, 30))
		{
//			$this->registerPacket('onPrismConnect', ISP_VER);
			$this->registerPacket('onPlayerConnect', ISP_NCN);
		}
	}
	
	public function onPlayerConnect(IS_NCN $NCN)
	{
		if ($NCN->UCID != 0)
		{
			echo ":O";
			$btn_bg = new Button($NCN->UCID, 'buttonTests_bg', 'buttonTests');
			$btn_bg->L(45)->T(45)->W(60)->H(50);
			$btn_bg->BStyle |= ISB_DARK;
			$btn_bg->send();
			
			$btn_hdr = new Button($NCN->UCID, 'buttonTests_hdr', 'buttonTests');
			$btn_hdr->Text('Welcome');
			$btn_hdr->L(50)->T(50)->W(50)->H(10);
			$btn_hdr->send();
			
			$btn_name = new Button($NCN->UCID, 'buttonTests_name', 'buttonTests');
			$btn_name->Text($NCN->PName);
			$btn_name->L(50)->T(60)->W(50)->H(20);
			$btn_name->send();
			
			$btn_chn = new Button($NCN->UCID, 'buttonTests_chng_name', 'buttonTests');
			$btn_chn->Text('Change Name');
			$btn_chn->L(47)->T(85)->W(22)->H(7);
			$btn_chn->BStyle |= ISB_LIGHT;
			$btn_chn->registerOnText($this, 'changeName', 20);
			$btn_chn->send();
			
			$btn_button = new Button($NCN->UCID, 'buttonTests_next', 'buttonTests');
			$btn_button->Text('next');
			$btn_button->L(87)->T(85)->W(16)->H(7);
			$btn_button->BStyle |= ISB_LIGHT;
			$btn_button->registerOnClick($this, 'changeButtons');
			$btn_button->send();
		}
	}
	
	public function changeName(IS_BTT $BTT)
	{
		$btn = ButtonManager::getButtonForKey($BTT->UCID, 'buttonTests_name');
		$btn->Text($BTT->Text);
		$btn->send();
	}
	public function changeButtons(IS_BTC $BTC)
	{
		// remove name and change name button
		ButtonManager::removeButtonByKey($BTC->UCID, 'buttonTests_chng_name');
		ButtonManager::removeButtonByKey($BTC->UCID, 'buttonTests_name');
		
		// change header
		$btn_hdr = ButtonManager::getButtonForKey($BTC->UCID, 'buttonTests_hdr');
		$btn_hdr->Text('Powered by PRISM!');
		$btn_hdr->send();
		
		// change next action
		$btn_button = ButtonManager::getButtonForKey($BTC->UCID, 'buttonTests_next');
		$btn_button->registerOnClick($this, 'hideAllButtons');
		$btn_button->send();
	}
	public function hideAllButtons(IS_BTC $BTC)
	{
		ButtonManager::removeButtonsByGroup($BTC->UCID, 'buttonTests');
	}
}