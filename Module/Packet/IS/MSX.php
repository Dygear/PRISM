<?php

class IS_MSX extends Struct    	// MSg eXtended - like MST but longer (not for commands)
{
	const PACK = 'CCxxa96';
	const UNPACK = 'CSize/CType/CReqI/CZero/a96Msg';

	protected $Size = 100;				# 100
	protected $Type = ISP_MSX;			# ISP_MSX
	protected $ReqI = 0;				# 0
	protected $Zero = null;

	public $Msg;						# last byte must be zero

	public function pack()
	{
		if (strLen($this->Msg) > 95) {
			foreach(explode("\n", wordwrap($this->Msg, 95, "\n", true)) as $Msg) {
				$this->Msg($Msg)->Send();
			}
		}
        
		return parent::pack();
	}
}; function IS_MSX() { return new IS_MSX; }