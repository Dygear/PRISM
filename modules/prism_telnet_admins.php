<?php
/**
 * PHPInSimMod - Telnet Module
 * @package PRISM
 * @subpackage Telnet
*/

class TSAdminSection extends TSSection
{
	public function __construct(ScreenContainer $parentSection, $width, $height, $ttype = 0)
	{
		parent::__construct($parentSection);
		
		$this->setLocation(1, 3);
		$this->setSize($width, $height);
		$this->setTType($ttype);
		$this->setId('admins');
//		$this->setBorder(TS_BORDER_REGULAR);

		$this->createMenu();
		
		// Menu / content separator line
//		$vertLine = new TSVLine(18, 3, 20);
//		$vertLine->setTType($ttype);
//		$this->add($vertLine);
	}
	
	public function __destruct()
	{
		parent::__destruct();
	}
	
	// Toggle through 'add admin' and all t he existing admin accounts (and select)
	public function handleKey($key)
	{
		if ($this->subSection->getActive())
		{
			if ($this->subSection->handleKey($key))
				return true;
		}
		
		$newItem = null;
		
		switch($key)
		{
			case KEY_CURUP :
				$newItem = $this->previousItem();
				break;
			
			case KEY_CURDOWN :
				$newItem = $this->nextItem();
				break;
			
			case KEY_CURRIGHT :
				$this->selectItem();
				break;
			
			case KEY_ESCAPE :
			case KEY_CURLEFT :
				$this->deSelectItem();
				break;
			
			default :
				return false;
		}
		
		// Draw the new content screen
		if ($newItem !== null)
		{
			// Remove sub section from drawing list
			if ($this->subSection !== null)
				$this->remove($this->subSection);
			
			// Create new sub section - either to add or edit an admin
			if ($newItem->getId() == 'adminAdd')
			{
				$this->subSection = new TSAdminContentSection($this, TS_AACTION_ADD, 59, 21, '', $this->getTType());
			}
			else
			{
				$this->subSection = new TSAdminContentSection($this, TS_AACTION_EDIT, 59, 21, $newItem->getText(), $this->getTType());
			}
			
			// Add sub section to drawing list
			$this->add($this->subSection);
		}
		
		return true;
	}
	
	private function createMenu()
	{
		global $PRISM;
		
		$textArea = new TSTextArea(2, 4, 15, 1);
		$textArea->setId('adminAdd');
		$textArea->setText('Add admin');
		$textArea->setOptions(TS_OPT_ISSELECTABLE | TS_OPT_ISSELECTED);
		$this->add($textArea);

		// Create list of selectable usernames
		$admins = $PRISM->admins->getAdminsInfo();
		
		$line = 5;
		foreach ($admins as $username => $details)
		{
			$textArea = new TSTextArea(2, $line, 15, 1);
			$textArea->setId('a'.($line - 5));
			$textArea->setText($username);
			$textArea->setOptions(TS_OPT_ISSELECTABLE);
			$this->add($textArea);
			
			$line++;
		}

		$this->subSection = new TSAdminContentSection($this, TS_AACTION_ADD, 59, 21, '', $this->getTType());
		$this->add($this->subSection);
	}
	
	public function redrawMenu()
	{
		$this->removeAll();
		$this->resetSection();
		$this->createMenu();
	}
	
	protected function selectItem()
	{
//		console('Selecting item '.$this->subSection->getId().' ('.$this->subSection->getUsername().')');
		
		// Change focus (set actives)
		$this->setActive(false);
		$this->getCurObject()->setBold(true);
		$this->subSection->setActive(true);
	}
	
	protected function deSelectItem()
	{
		// Change focus (set actives)
		if ($this->getActive() == true)
			return;
		
//		console('Selecting item '.$this->subSection->getId().' ('.$this->subSection->getUsername().')');
		
		$this->setActive(true);
		$this->getCurObject()->setBold(false);
		$this->subSection->setActive(false);
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
}

define('TS_AACTION_ADD', 0);
define('TS_AACTION_EDIT', 1);

class TSAdminContentSection extends TSSection
{
	private $actionType		= 0;
	private $username		= '';
	private $userDetails	= array();
	
	public function __construct(ScreenContainer $parentSection, $actionType, $width, $height, $username, $ttype = 0)
	{
		parent::__construct($parentSection);
		
		$this->actionType = $actionType;
		$this->setLocation(20, 3);
		$this->setSize($width, $height);
		$this->setTType($ttype);
		$this->setId('adminsContent');
		$this->setBorder(TS_BORDER_REGULAR);
		
		if ($username)
		{
			$this->setUsername($username);
			$this->setCaption('Edit admin '.$this->username);
		}
		else
		{
			$this->setCaption('Add new administrator');
		}

		$this->createAdminContent();
	}
	
	public function __destruct()
	{
		parent::__destruct();
	}
	
	public function handleKey($key)
	{
		switch ($key)
		{
			case KEY_SHIFTTAB :
			case KEY_CURUP :
				$newItem = $this->previousItem();
				$this->setInputMode();
				break;
			
			case KEY_TAB :
			case KEY_CURDOWN :
				$newItem = $this->nextItem();
				$this->setInputMode();
				break;
			
			case KEY_CURRIGHT :
				break;
			
			case KEY_ENTER :
				switch ($this->getCurObject()->getId())
				{
					case 'adminSave' :
						$this->adminSave();
						break;
					
					case 'adminDelete' :
						$this->adminDelete();
						break;
				}
				break;

			default :
				return false;
		}
		
		return true;
	}

	protected function setInputMode()
	{
		$object = $this->getCurObject();
		switch ($object->getId())
		{
			case 'adminUsername' :
				$this->setInputCallback(
					$this, 
					'handleAdminInput', 
					TELNET_MODE_LINEEDIT, 
					array(31 + strlen($object->getText()), 6), 
					$object->getText(), 
					23
				);
//				console('Setting username line edit callback');
				break;
			
			case 'adminPassword' :
				if ($this->actionType == TS_AACTION_ADD)
				{
					$this->setInputCallback(
						$this, 
						'handleAdminInput', 
						TELNET_MODE_LINEEDIT, 
						array(31 + strlen($object->getText()), 10), 
						$object->getText(), 
						24
					);
				}
				else
				{
					$this->setInputCallback(
						$this, 
						'handleAdminInput', 
						TELNET_MODE_LINEEDIT, 
						array(31 + strlen($object->getText()), 6), 
						$object->getText(), 
						24
					);
				}
//				console('Setting password line edit callback');
				break;
			
			case 'adminFlags' :
				if ($this->actionType == TS_AACTION_ADD)
				{
					$this->setInputCallback(
						$this, 
						'handleAdminInput', 
						TELNET_MODE_LINEEDIT, 
						array(31 + strlen($object->getText()), 14), 
						$object->getText(), 
						26
					);
				}
				else
				{
					$this->setInputCallback(
						$this, 
						'handleAdminInput', 
						TELNET_MODE_LINEEDIT, 
						array(31 + strlen($object->getText()), 10), 
						$object->getText(), 
						26
					);
				}
//				console('Setting flags line edit callback');
				break;
			
			default :
				$this->setInputCallback(null);
//				console('Setting key edit callback');
				break;
		}
		
	}
	
	protected function selectItem()
	{
		
	}
	
	private function createAdminContent()
	{
		if ($this->actionType == TS_AACTION_ADD)
		{
			// New username
			$textArea = new TSTextInput(30, 5, 30, 3);
			$textArea->setId('adminUsername');
			$textArea->setTType($this->getTType());
			$textArea->setMaxLength(23);
			$textArea->setText('');
			$textArea->setOptions(TS_OPT_ISSELECTABLE | TS_OPT_ISEDITABLE);
			$textArea->setBorder(TS_BORDER_REGULAR);
			$textArea->setCaption('LFS Username (exact match)');
			$this->add($textArea);

			// New password
			$textArea = new TSTextInput(30, 9, 30, 3);
			$textArea->setId('adminPassword');
			$textArea->setTType($this->getTType());
			$textArea->setMaxLength(23);
			$textArea->setText('');
			$textArea->setOptions(TS_OPT_ISSELECTABLE | TS_OPT_ISEDITABLE);
			$textArea->setBorder(TS_BORDER_REGULAR);
			$textArea->setCaption('Prism Password');
			$this->add($textArea);

			// Admin flags
			$textArea = new TSTextInput(30, 13, 30, 3);
			$textArea->setId('adminFlags');
			$textArea->setTType($this->getTType());
			$textArea->setMaxLength(26);
			$textArea->setText('abcdetc');
			$textArea->setOptions(TS_OPT_ISSELECTABLE | TS_OPT_ISEDITABLE);
			$textArea->setBorder(TS_BORDER_REGULAR);
			$textArea->setCaption('Permission flags');
			$this->add($textArea);
			
			// Save
			$textArea = new TSTextArea(30, 17, 12, 3);
			$textArea->setId('adminSave');
			$textArea->setTType($this->getTType());
			$textArea->setText('Save admin');
			$textArea->setOptions(TS_OPT_ISSELECTABLE);
			$textArea->setBorder(TS_BORDER_REGULAR);
			$this->add($textArea);
		}
		else
		{
			// New password
			$textArea = new TSTextInput(30, 5, 30, 3);
			$textArea->setId('adminPassword');
			$textArea->setTType($this->getTType());
			$textArea->setText('');
			$textArea->setOptions(TS_OPT_ISSELECTABLE | TS_OPT_ISEDITABLE);
			$textArea->setBorder(TS_BORDER_REGULAR);
			$textArea->setCaption('Prism Password');
			$this->add($textArea);

			// Admin flags
			$textArea = new TSTextInput(30, 9, 30, 3);
			$textArea->setId('adminFlags');
			$textArea->setTType($this->getTType());
			$textArea->setText(flagsToString($this->userDetails['accessFlags']));
			$textArea->setOptions(TS_OPT_ISSELECTABLE | TS_OPT_ISEDITABLE);
			$textArea->setBorder(TS_BORDER_REGULAR);
			$textArea->setCaption('Permission flags');
			$this->add($textArea);
			
			// Save
			$textArea = new TSTextArea(30, 13, 12, 3);
			$textArea->setId('adminSave');
			$textArea->setTType($this->getTType());
			$textArea->setText('Save admin');
			$textArea->setOptions(TS_OPT_ISSELECTABLE);
			$textArea->setBorder(TS_BORDER_REGULAR);
			$this->add($textArea);

			// Delete
			$textArea = new TSTextArea(30, 17, 14, 3);
			$textArea->setId('adminDelete');
			$textArea->setTType($this->getTType());
			$textArea->setText('Delete admin');
			$textArea->setOptions(TS_OPT_ISSELECTABLE);
			$textArea->setBorder(TS_BORDER_REGULAR);
			$this->add($textArea);
		}
		
	}
	
	public function setUsername($username)
	{
		global $PRISM;
		
		$this->username = $username;
		$this->userDetails = $PRISM->admins->getAdminInfo($username);
	}
	
	public function getUsername()
	{
		return $this->username;
	}
	
	private function adminSave()
	{
		global $PRISM;
		// Collect data from input fields
		$username	= ($this->actionType == TS_AACTION_ADD) ? $this->getObjectById('adminUsername')->getText() : $this->username;
		$password	= $this->getObjectById('adminPassword')->getText();
		$flags		= $this->getObjectById('adminFlags')->getText();
		
		// Save admin
		if ($PRISM->admins->adminExists($username))
		{
			// Update admin
			if ($password != '')
				$PRISM->admins->changePassword($username, $password);
			$PRISM->admins->setAccessFlags($username, flagsToInteger($flags));
		}
		else
		{
			// New admin
			if ($username == '' || $password == '')
				return;
			$PRISM->admins->addAccount($username, $password, flagsToInteger($flags));
			$PRISM->admins->setAccessFlags($username, flagsToInteger($flags));
		}
		
		$this->parentSection->redrawMenu();
		
//		console('Save this admin '.$username.' / '.$password.' / '.$flags);
	}
	
	private function adminDelete()
	{
		global $PRISM;
		
		if ($this->username)
			$PRISM->admins->deleteAccount($this->username);
		
		$this->parentSection->redrawMenu();

//		console('Delete this admin '.$this->username);
	}
	
	public function handleAdminInput($line)
	{
		$this->getCurObject()->setText($line);
		$this->setInputMode();
//		console('handleAdminInput ('.$this->getCurObject()->getId().') received a line : '.$line);
	}
}

?>