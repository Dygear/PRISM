<?php
class debugger extends Plugins
{
	// Set our URL, Name, Author, Version, Description
	const URL = '';
	const NAME = 'PRISM Debugger';
	const AUTHOR = 'T3charmy';
	const VERSION = '';
	const DESCRIPTION = 'Use this plugin to send errors from PHP to your LFS server';

	protected $Error = array();
	protected $conns = array();

	public function __construct()
	{
		# PHP Stuff
		set_error_handler(array($this, 'onPrismError'));
		register_shutdown_function(array($this, 'onPrismClose'));

		$this->registerPacket('onPrismConnect', ISP_VER);

        $this->registerInsimCommand('error', 'debugForceError', 'Forces PHP error.', ADMIN_SPECTATE);
    }

    public function onPrismError($eNo, $eStr, $eFile, $eLine)
    {
        if (error_reporting() !== 0)
        {
            if(!isset($this->Error['Last'])) {
                $this->Error['Last']['Time'] = time() - 5;
            }
            $Error = sprintf("^1> ERROR: ^7[%d] %s. %s:%d",$eNo,$eStr,basename($eFile),$eLine);
            if(!isset($this->Error['Last']['Msg'])
                || $this->Error['Last']['Msg'] != $Error
                || time() - $this->Error['Last']['Time'] >= 5) {
                IS_MTC()->Sound(4)->UCID(255)->Text($Error)->Send();
                $this->Error['Last']['Msg'] = $Error;
                $this->Error['Last']['Time'] = time();
            }
        }
        return false;
    }
    /* }}} */

    /* public onPrismClose() {{{ */
    /**
     * onPrismClose
     *
     * @access public
     * @return void
     */
    public function onPrismClose()
    {
        if(error_get_last()['type'] === E_ERROR)
        {
            foreach($this->conns as $host){
                IS_MTC()->Sound(4)->UCID(255)->Text('^1> FATAL ERROR. ^7Insim will restart in 3 seconds!')->Send($host); # readd $host
            }
        }
        else
        {
            foreach($this->conns as $host){
                IS_MTC()->Sound(SND_SYSMESSAGE)->UCID(255)->Text('^1> INSIM CLOSING. ^3Good Bye...')->Send($host);
            }
        }
        return false;
    }

    public function onPrismConnect(IS_VER $VER)
    {
        $this->conns[] = $this->getCurrentHostId();
        IS_MTC()->UCID(255)->Text('^6> ^7'.debugger::NAME.' ^7Has Connected.')->Send();
        console(debugger::NAME.' Has Connected.');
    }

    public function debugForceError($cmd, $ucid)
    {
        $argv = str_getcsv($cmd, ' ');
        $cmd = array_shift($argv);
        trigger_error(implode(' ', $argv));
    }
}
