<?php

class IS_STA extends Struct // STAte
{
    const PACK = 'CCCxfvCCCCCCCCxxa6CC';
	const UNPACK = 'CSize/CType/CReqI/CZero/fReplaySpeed/vFlags/CInGameCam/CViewPLID/CNumP/CNumConns/CNumFinished/CRaceInProg/CQualMins/CRaceLaps/CSpare2/CSpare3/a6Track/CWeather/CWind';

	protected $Size = 28;				# 28
	protected $Type = ISP_STA;			# ISP_STA
	public $ReqI;						# ReqI if replying to a request packet
	protected $Zero;

	public $ReplaySpeed;				# 4-byte float - 1.0 is normal speed

	public $Flags;						# ISS state flags (see below)
	public $InGameCam;					# Which type of camera is selected (see below)
	public $ViewPLID;					# Unique ID of viewed player (0 = none)

	public $NumP;						# Number of players in race
	public $NumConns;					# Number of connections including host
	public $NumFinished;				# Number finished or qualified
	public $RaceInProg;					# 0 - no race / 1 - race / 2 - qualifying

	public $QualMins;
	public $RaceLaps;					# see "RaceLaps" near the top of this document
	protected $Spare2;
	protected $Spare3;

	public $Track;						# short name for track e.g. FE2R
	public $Weather;					# 0,1,2...
	public $Wind;						# 0=off 1=weak 2=strong
}; function IS_STA() { return new IS_STA; }