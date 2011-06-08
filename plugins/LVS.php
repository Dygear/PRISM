<?php
class LVS extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'LVS';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Lap Verification System.';

	private $LVS = TRUE;

	public function __construct()
	{
		$this->registerSayCommand('prism lvs', 'cmdLVSToggle', '<On|Off> - Turns Lap Verification System On / Off', ADMIN_CVAR + ADMIN_TRACK);
	}

	public function cmdLVSToggle($cmd, $ucid)
	{
		$MTC = new IS_MTC;
		$MTC->UCID($ucid);
		if (($argc = count($argv = str_getcsv($cmd, ' '))) > 2)
		{
			$OnOff = strtolower($argv[2]);
			if ($OnOff == 'on')
			{
				$this->LVS = TRUE;
				$MTC->Text('Lap Verification System is now ^3On^8!');
			}
			else if ($OnOff == 'off')
			{
				$this->LVS = FALSE;
				$MTC->Text('Lap Verification System is now ^3Off^8!');
			}
			else
				$MTC->Text('Please provide a ^3On^8 or ^3Off^8 only as an argument to this command.');
		}
		else
			$MTC->Text('Lap Verification System is currently ^3' . (($this->LVS) ? 'On' : 'Off') . '^8.');
		$MTC->Send();
	}
}
?>