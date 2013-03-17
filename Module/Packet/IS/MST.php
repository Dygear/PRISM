<?php

class IS_MST extends Struct    	// MSg Type - send to LFS to type message or command
{
	const PACK = 'CCxxa64';
	const UNPACK = 'CSize/CType/CReqI/CZero/a64Msg';

	protected $Size = 68;				# 68
	protected $Type = ISP_MST;			# ISP_MST
	protected $ReqI = 0;				# 0
	protected $Zero = null;

	public $Msg;						# last byte must be zero

	public function pack()
	{
		if (strLen($this->Msg) > 63) {
			foreach(explode("\n", wordwrap($this->Msg, 63, "\n", true)) as $Msg) {
				$this->Msg($Msg)->Send();
			}
            
			return;
		}
        
		return parent::pack();
	}
}; function IS_MST() { return new IS_MST; }