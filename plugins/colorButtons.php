<?php
class colorButtons extends Plugins
{
	const NAME = 'Color Buttons Example';
	const AUTHOR = 'Mark \'Dygear\' Tomlin';
	const VERSION = '1.0.0';
	const DESCRIPTION = 'Shows how to use colors in buttons with BStyle & Color Escape Codes.';

	public function __construct()
	{
		$this->registerSayCommand('prism help buttons', 'cmdHelpButtons', 'Shows the different color options available.');
	}

	public function cmdHelpButtons($cmd, $plid, $ucid)
	{
		$X = 25;	$W = 10;
		$Y = 50;	$H = 10;

		$BTN = new IS_BTN;
		$BTN->UCID($ucid)->ClickID(0)->W($W)->H($H);

		# X Axis Header
		for ($i = 0; $i <= 9; ++$i)
			$BTN->ClickID(++$BTN->ClickID)->L($X + ($i * $W) + 1)->T($Y - ($H + 1))->BStyle(ISB_DARK)->Text("^$i$i")->Send();

		# Y Axis Header
		for ($i = 0; $i <= 7; ++$i)
			$BTN->ClickID(++$BTN->ClickID)->L($X - $W)->T($Y + ($i * $H) + 1)->BStyle(ISB_DARK + $i)->Text($i)->Send();

		# Grid Items
		for ($y = 0; $y <= 7; ++$y)
		{
			for ($x = 0, $i = 0; $x <= 9; ++$x, ++$i)
				$BTN->ClickID(++$BTN->ClickID)->L($X + ($x * $W) + 1)->T($Y + ($y * $H) + 1)->Text("{$y}^$i{$x}")->BStyle(ISB_LIGHT + $y)->Send();
		}
	}
}
?>