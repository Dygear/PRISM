<?php
class contact extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Contact';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Collision Detection Plugin.';

	public function __construct() {
		$this->registerPacket('onCON', ISP_CON);
	}
	public function onCON($CON) {
		#$CON->getA()->bug();
	echo <<<EOS
IS_CON Packet {
	Close => {$CON->getClosingSpeed()}
	Time => {$CON->getTime()}
	A => CarContact Struct {
		PLID => {$CON->getA()->getPLID()}
		Info => {$CON->getA()->getInfO()}
		Steer => {$CON->getA()->getWheelAngle()}
		ThrBrk => {$CON->getA()->ThrBrk} {
			Throttle => {$CON->getA()->getThrottle()}
			Brake => {$CON->getA()->getBrake()}
		}
		CluHan => {$CON->getA()->CluHan} {
			Clutch => {$CON->getA()->getClutch()}
			Handbrake => {$CON->getA()->getHandbrake()}
		}
		GearSp => {$CON->getA()->GearSp} {
			Gear => {$CON->getA()->getGear()}
		}
		Speed => {$CON->getA()->getSpeed()}
		Direction => {$CON->getA()->getDirection()}
		Heading => {$CON->getA()->getHeading()}
		AccelF => {$CON->getA()->getAccelerationLongitudinal()}
		AccelR => {$CON->getA()->getAccelerationLateral()}
		X => {$CON->getA()->getX()}
		Y => {$CON->getA()->getY()}
	}
	B => CarContact Struct {
		PLID => {$CON->getB()->getPLID()}
		Info => {$CON->getB()->getInfO()}
		Steer => {$CON->getB()->getWheelAngle()}
		ThrBrk => {$CON->getB()->ThrBrk} {
			Throttle => {$CON->getB()->getThrottle()}
			Brake => {$CON->getB()->getBrake()}
		}
		CluHan => {$CON->getB()->CluHan} {
			Clutch => {$CON->getB()->getClutch()}
			Handbrake => {$CON->getB()->getHandbrake()}
		}
		GearSp => {$CON->getB()->GearSp} {
			Gear => {$CON->getB()->getGear()}
		}
		Speed => {$CON->getB()->getSpeed()}
		Direction => {$CON->getB()->getDirection()}
		Heading => {$CON->getB()->getHeading()}
		AccelF => {$CON->getB()->getAccelerationLongitudinal()}
		AccelR => {$CON->getB()->getAccelerationLateral()}
		X => {$CON->getB()->getX()}
		Y => {$CON->getB()->getY()}
	}
}

EOS;
	}
}
?>