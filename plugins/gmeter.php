<?php
class gmeter extends Plugins {
	const NAME = 'G-Force Meter';
	const AUTHOR = "sinanju, Dygear & morpha";
	const VERSION = '0.1.0';
	const DESCRIPTION = 'Shows a G-Force meter.';

	const GRAVITY = 9.80665;

	private $BTNs = array();
	private $TIMEs = array();
	private $SPEEDs = array();

	public function __construct() {
		$this->registerPacket('onMCI', ISP_MCI);
	}
	public function onMCI($Packet) {
		$cTime = microtime(TRUE);
		foreach ($Packet->Info as $CompCar) {
			// Spawn a new button instance if one is not here.
			if (!isset($this->BTNs[$CompCar->PLID])) {
				$this->BTNs[$CompCar->PLID] = new IS_BTN;
				$this->BTNs[$CompCar->PLID]->T(184)->L(164)->W(10)->H(6)->BStyle(ISB_DARK + ISB_RIGHT + 1)->Send();
				$this->BTNs[$CompCar->PLID]->W(0)->H(0);
			}
			
			// Speeds
			$cSpeed = (($CompCar->Speed / 32768) * 100);
			$lSpeed = (isset($this->SPEEDs[$CompCar->PLID])) ? $this->SPEEDs[$CompCar->PLID] : 0;

			// Times
			$lTime = (isset($this->TIMEs[$CompCar->PLID])) ? $this->TIMEs[$CompCar->PLID] : 0;

			// Get gForce
			$gForce = round(($cSpeed - $lSpeed) / ($this::GRAVITY * ($cTime - $lTime)), 2);

			// Update Button
			$this->BTNs[$CompCar->PLID]->Text(sprintf('%.2f', $gForce))->Send();
			
			// Save State
			$this->TIMEs[$CompCar->PLID] = $cTime;
			$this->SPEEDs[$CompCar->PLID] = $cSpeed;
		}
	}
}
?>