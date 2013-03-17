<?php

class IS_VER extends Struct // VERsion
{
    const PACK = 'CCCxa8a6v';
	const UNPACK = 'CSize/CType/CReqI/CZero/a8Version/a6Product/vInSimVer';

	protected $Size = 20;				# 20
	protected $Type = ISP_VER;			# ISP_VERSION
	public $ReqI;						# ReqI as received in the request packet
	protected $Zero;

	public $Version;					# LFS version, e.g. 0.3G
	public $Product;					# Product : DEMO or S1
	public $InSimVer = INSIM_VERSION;	# InSim Version : increased when InSim packets change
}; function IS_VER() { return new IS_VER; }