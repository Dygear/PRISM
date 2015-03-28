<?php
class welcome extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Welcome & MOTD';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Welcome messages for clients, and Message of the Day (MOTD)';

    protected $BTNMan = array();

	public function __construct()
	{
		$this->registerPacket('onPrismConnect', ISP_VER);
		$this->registerPacket('onClientConnect', ISP_NCN);
	}

	public function onPrismConnect(IS_VER $VER)
	{
		IS_MSX()->Msg('PRISM Version ^3'.PHPInSimMod::VERSION.'^8 Has Connected.')->Send();
	}

	public function onClientConnect(IS_NCN $NCN)
	{
		if ($NCN->UCID == 0)
			return;

		$this->BTNMan[$NCN->UName] = new betterButtonManager($NCN->UCID);
        $this->createNamedTimer("WelcomePlugin_ButtonHandle_{$NCN->UName}", 'DoButtonHandle', 0.50, Timer::REPEAT, array($NCN->UName));

        $this->BTNMan[$UName]->InitButton('poweredBy', 'welcomeMSG', (IS_Y_MAX - IS_Y_MIN), IS_X_MIN, IS_X_MAX, 8, ISB_DARK, 'This server is powered by', 10);
        $this->BTNMan[$UName]->AddClickEventToBtn('poweredBy', $this, 'onPoweredByClick', array($NCN->UCID))
        $this->BTNMan[$UName]->InitButton('prism', 'welcomeMSG', (IS_Y_MAX - IS_Y_MIN + 8), IS_X_MIN, IS_X_MAX, 8, ISB_DARK, '^3PRISM ^8Version ^7'.PHPInSimMod::VERSION.'^8.', 10);

	}
	
	public function onPoweredByClick($UCID)
	{
		IS_MTC()->Text('^3For more info on PRISM visit:')->UCID($UCID)->send();
		IS_MTC()->Text('^7https://www.lfs.net/forum/312')->UCID($UCID)->send();
	}

    public function DoButtonHandle($UName)
    {
        $this->BTNMan[$UName]->HandleButtons();
    }
}
?>
