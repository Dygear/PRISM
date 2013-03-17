<?php

class IS_MTC extends Struct    	// Msg To Connection - hosts only - send to a connection / a player / all
{
	const PACK = 'CCxCCCxxa128';
	const UNPACK = 'CSize/CType/CReqI/CSound/CUCID/CPLID/CSp2/CSp3/a128Text';

	protected $Size = 136;				# 8 + TEXT_SIZE (TEXT_SIZE = 4, 8, 12... 128)
	protected $Type = ISP_MTC;			# ISP_MTC
	protected $ReqI = 0;				# 0
	public $Sound = null;				# sound effect (see Message Sounds below)

	public $UCID = 0;					# connection's unique id (0 = host / 255 = all)
	public $PLID = 0;					# player's unique id (if zero, use UCID)
	protected $Sp2 = null;
	protected $Sp3 = null;

	public $Text;						# up to 128 characters of text - last byte must be zero

	public function pack()
	{
		if (strLen($this->Text) > 127) {
			foreach(explode("\n", wordwrap($this->Text, 127, "\n", true)) as $Text) {
				$this->Text($Text)->Send();
			}
		}
        
		return parent::pack();
	}
}; function IS_MTC() { return new IS_MTC; }