<?php
class gapmon extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Gap Monitor';
	const AUTHOR = 'NotAnIllusion';
	const VERSION = '1.0.0';
	const DESCRIPTION = 'Gap Monitoring Tool';

	private $race = array();
	private $follow = FALSE;
	private $BtnX = 100;	# midpoint between buttons, span 15+5 left and right
	private $BtnY = 175;	# top buttons' vertical position
	
	public function __construct()
	{
		$this->registerPacket('onNewPlayer', ISP_NPL);
		$this->registerPacket('onPlayerLeave', ISP_PLL);
		$this->registerPacket('onSector', ISP_SPX, ISP_LAP);
		$this->registerPacket('onNodeLap', ISP_NLP);
		$this->registerPacket('onRestart', ISP_RST);
//		$IS_TINY = new IS_TINY();
//		$IS_TINY->SubT(TINY_NLP)->Send();
	}

	public function onNewPlayer(IS_NPL $NPL)
	{
		$this->race[$NPL->ReqI] = array(
			'position' => 0,
			'lap' => 0,
			'etime' => 0,
			'gapahead' => 0,
			'gapbehind' => 0,
			'REMOTE' => TRUE
		);
	
		if(!($NPL->UCID & 6))
		{
			$this->follow = $NPL->ReqI;	# PLID to Time For.
			$this->race[$this->follow]['REMOTE'] = FALSE;
			
			$BTN = new IS_BTN();
			$BTN->ClickID(100)->BStyle(16)->L($this->BtnX-20)->T($this->BtnY)->W(15)->H(5)->Text('0.00')->Send(); # Top Left
			$BTN->ClickID(101)->BStyle(20)->L($this->BtnX-20)->T($this->BtnY+5)->W(15)->H(5)->Text('0.00')->Send(); # Bottom Left
			$BTN->ClickID(102)->BStyle(16)->L($this->BtnX+5)->T($this->BtnY)->W(15)->H(5)->Text('0.00')->Send(); # Top Right
			$BTN->ClickID(103)->BStyle(20)->L($this->BtnX+5)->T($this->BtnY+5)->W(15)->H(5)->Text('0.00')->Send(); # Bottom Right
		}
	}
	
	public function onPlayerLeave(IS_PLL $PLL)
	{
		unset($this->race[$PLL->PLID]);
		if(isset($this->follow))
		{
			if($PLL->PLID == $this->follow)
			{
				$this->follow = FALSE;
				$BFN = new ISP_BFN;
				$BFN->SubT(1)->Send(BFN_CLEAR);
			}
		}
	}
	
	public function onSector(Struct $Sector)
	{
		$this->race[$Sector->PLID] = $Sector->ETime;
		$TINY = new IS_TINY();
		$TINY->ReqI($Sector->PLID)->SubT(TINY_NLP)->Send();
	}
	
	public function onNodeLap(IS_NLP $NLP)
	{
		# If there is less then 2 plays, this is not going to work, so abort the function.
		if (count($this->race) < 2)
			return PLUGIN_CONTINUE;
		
		if($this->follow !== FALSE)
		{
			$BTN = new IS_BTN();

			if(($NLP->PLID == $this->follow) && ($this->race[$this->follow]['position'] >= 2))
			{
				# Gap ahead
				foreach($this->race as $racer)
				{
					if($racer['position'] == $this->race[$this->follow]['position'] - 1)
					{
						$gap = $this->race[$this->follow]['etime'] - $racer['etime'];
						$diff = $gap - $this->race[$this->follow]['gapahead'];

						$BTN->ClickID(100)->BStyle(16)->L($this->BtnX-20)->T($this->BtnY)->W(15)->H(5)->Text('+'.number_format($gap / 1000, 2))->Send(); # Top Left
						if($diff <= 0)
							$BTN->ClickID(101)->BStyle(20)->L($this->BtnX-20)->T($this->BtnY+5)->W(15)->H(5)->Text(number_format($diff / 1000, 2))->Send(); # Bottom Left
						else
							$BTN->ClickID(101)->BStyle(20)->L($this->BtnX-20)->T($this->BtnY+5)->W(15)->H(5)->Text('+'.number_format($diff / 1000, 2))->Send(); # Bottom Left

						$this->race[$this->follow]['gapahead'] = $gap;
					}
				}
			}
			
			if($this->race[$NLP->PLID]['position'] == $this->race[$this->follow]['position'] + 1)
			{
				# Gap behind
				$gap = $this->race[$NLP->PLID]['etime'] - $this->race[$this->follow]['etime'];
				$diff = $gap - $this->race[$this->follow]['gapbehind'];

				$BTN->ClickID(102)->BStyle(16)->L($this->BtnX+5)->T($this->BtnY)->W(15)->H(5)->Text('+'.number_format($gap / 1000, 2))->Send(); # Top Right
				if($diff <= 0)
					$BTN->ClickID(103)->BStyle(20)->L($this->BtnX+5)->T($this->BtnY+5)->W(15)->H(5)->Text(number_format($diff / 1000, 2))->Send(); # Bottom Right
				else
					$BTN->ClickID(103)->BStyle(20)->L($this->BtnX+5)->T($this->BtnY+5)->W(15)->H(5)->Text('+'.number_format($diff / 1000, 2))->Send(); # Bottom Right

				$this->race[$this->follow]['gapbehind'] = $gap;
			}
		}
	}
	
	public function onRestart(IS_RST $RST)
	{
		$this->race = array();
		$BNF = new IS_BFN();
		$BNF->SubT(BFN_DEL_BTN)->Send();
		$TINY = new IS_TINY();
		$TINY->ReqI(255)->SubT(TINY_NPL)->Send();
	}
}
?>