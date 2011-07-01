<?php
class colorButtons extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Color Buttons Example';
	const AUTHOR = "Mark 'Dygear' Tomlin";
	const VERSION = '1.1.0';
	const DESCRIPTION = 'Shows how to use colors in buttons with BStyle & Color Escape Codes.';

	private $BTNs = array();
	private $Time = array();

	public function __construct()
	{
		$this->registerSayCommand('prism buttons', 'cmdColorButtons', '<x=15> <y=75> <ttl=10> - Shows the different color options available.');
		$this->registerSayCommand('prism prompt', 'cmdPrompt', '<x=81> <y=67> <ttl=10> - ');
	}
	public function cmdPrompt($cmd, $ucid)
	{
		$X = 67.1875; $Y = 80.75; $TTL = 10;

		if (($argc = count($argv = str_getcsv($cmd, ' '))) > 2)
		{
			switch ($argc)
			{
				case 5:
					$TTL = (int) array_pop($argv);
				case 4:
					$Y = (int) array_pop($argv);
				case 3:
					$X = (int) array_pop($argv);
			}
		}

		if (!isset($this->BTNs[$ucid]))
			$this->BTNs[$ucid] = array();

		$BTN = new IS_BTN()->UCID($ucid)->ClickID(0);

		# Confirm Folder Delete
		#
		#	ICON	Are you sure you want to remove the folder 'Jefferson Airplane' and
		#	ICON	move all it's contents to the Recycle Bin?
		#	
		# 													|	 Yes     |	|	 No	   |

		# Prompt Area
		$this->BTNs[$ucid][] = $BTN->ClickID(++$BTN->ClickID)->T($Y)->L($X)->W(65.625)->H(38.5)->BStyle(ISB_DARK)->Send();

		# Buttons
		$this->BTNs[$ucid][] = $BTN->T($Y + ($BTN->H - 12.1875))->W(12.1875)->H(5.25);
		$this->BTNs[$ucid][] = $BTN->ClickID(++$BTN->ClickID)->L($BTN->L + 60)->BStyle(ISB_LIGHT + 5)->Text('Yes')->Send();
		$this->BTNs[$ucid][] = $BTN->ClickID(++$BTN->ClickID)->L($BTN->L + 81.75)->BStyle(ISB_LIGHT + 4)->Text('No')->Send();
		

		$timeStamp = $this->createTimer('tmrClearButtons', $TTL);
		$this->Time[$timeStamp] = $ucid;
		ksort($this->Time);
	}
	public function cmdColorButtons($cmd, $ucid)
	{
		$X = 15; $Y = 75; $TTL = 10;

		if (($argc = count($argv = str_getcsv($cmd, ' '))) > 2)
		{
			switch ($argc)
			{
				case 5:
					$TTL = (int) array_pop($argv);
				case 4:
					$Y = (int) array_pop($argv);
				case 3:
					$X = (int) array_pop($argv);
			}
		}
	
		if (!isset($this->BTNs[$ucid]))
			$this->BTNs[$ucid] = array();

		$BTN = new IS_BTN()->UCID($ucid)->ClickID(0)->W(10)->H(10);

		# Grid Items
		for ($y = 0; $y <= 7; ++$y)
		{
			for ($x = 0, $i = 0; $x <= 9; ++$x, ++$i)
				$this->BTNs[$ucid][] = $BTN->ClickID(++$BTN->ClickID)->L($X + ($x * $BTN->W) + 1)->T($Y + ($y * $BTN->H) + 1)->Text("{$y}^$i{$x}")->BStyle(ISB_LIGHT + $y)->Send();
		}
		# X Axis Header
		for ($i = 0; $i <= 9; ++$i)
			$this->BTNs[$ucid][] = $BTN->ClickID(++$BTN->ClickID)->L($X + ($i * $BTN->W) + 1)->T($Y - ($BTN->H + 1))->BStyle(ISB_DARK)->Text("^$i$i")->Send();
		# Y Axis Header
		for ($i = 0; $i <= 7; ++$i)
			$this->BTNs[$ucid][] = $BTN->ClickID(++$BTN->ClickID)->L($X - $BTN->W)->T($Y + ($i * $BTN->H) + 1)->BStyle(ISB_DARK + $i)->Text($i)->Send();

		$timeStamp = $this->createTimer('tmrClearButtons', $TTL);
		$this->Time[$timeStamp] = $ucid;
		ksort($this->Time);
	}
	public function tmrClearButtons()
	{
		$timeNow = microtime(TRUE);
		foreach ($this->Time as $time => $ucid)
		{
			if ($time < $timeNow)
			{
				$BFN = new IS_BFN;
				$BFN->SubT(BFN_DEL_BTN)->UCID($ucid);
				foreach ($this->BTNs[$ucid] as $ClickID)
					$BFN->ClickID($ClickID)->Send();
				unset($this->BTNs[$ucid]);
				unset($this->Time[$time]);
			}
		}
		return PLUGIN_STOP;
	}
}
?>