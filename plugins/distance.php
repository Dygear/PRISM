<?php
class distance extends Plugins {
	const NAME = 'Distance Meter';
	const AUTHOR = "Mark 'Dygear' Tomlin";
	const VERSION = '0.1.0';
	const DESCRIPTION = 'Shows a distance meter.';

	private $BTNs = array(); # Array of IS_BTN instances.
	private $COORDs = array(); # Array of the last known coords for each player.
	private $TOTALs = array(); # Array of total distance traveled by each player.

	public function __construct() {
		$this->registerPacket('onMCI', ISP_MCI);
	}
	public function onMCI($Packet) {
		foreach ($Packet->Info as $CompCar) {
			$PLID = $CompCar->PLID;
			// Spawn a new button instance if one is not here.
			$BTN = new IS_BTN;
			if (!isset($this->BTNs[$PLID])) {
				# X Delta
				$BTN->ClickID(13)->T(178)->L(26)->W(3)->H(6)->BStyle(ISB_DARK + 4)->Text('V')->Send();
				$BTN->ClickID(23)->T(178)->L(29)->W(19)->H(6)->BStyle(ISB_DARK + 4)->Text('X Delta')->Send();
				$BTN->ClickID(33)->T(178)->L(48)->W(3)->H(6)->BStyle(ISB_DARK + 4)->Text('V')->Send();
				$this->BTNs[$PLID]['X'] = new IS_BTN;
				$this->BTNs[$PLID]['X']->ClickID(3)->T(184)->L(26)->W(25)->H(6)->BStyle(ISB_DARK + 4)->Send()->W(0)->H(0);

				# Y Delta
				$BTN->ClickID(14)->T(178)->L(51)->W(3)->H(6)->BStyle(ISB_DARK + 1)->Text('V')->Send();
				$BTN->ClickID(24)->T(178)->L(54)->W(19)->H(6)->BStyle(ISB_DARK + 1)->Text('Y Delta')->Send();
				$BTN->ClickID(34)->T(178)->L(73)->W(3)->H(6)->BStyle(ISB_DARK + 1)->Text('V')->Send();
				$this->BTNs[$PLID]['Y'] = new IS_BTN;
				$this->BTNs[$PLID]['Y']->ClickID(4)->T(184)->L(51)->W(25)->H(6)->BStyle(ISB_DARK + 1)->Send()->W(0)->H(0);

				# Z Delta
				$BTN->ClickID(15)->T(178)->L(76)->W(3)->H(6)->BStyle(ISB_DARK + 5)->Text('V')->Send();
				$BTN->ClickID(25)->T(178)->L(79)->W(19)->H(6)->BStyle(ISB_DARK + 5)->Text('Z Delta')->Send();
				$BTN->ClickID(35)->T(178)->L(98)->W(3)->H(6)->BStyle(ISB_DARK + 5)->Text('V')->Send();
				$this->BTNs[$PLID]['Z'] = new IS_BTN;
				$this->BTNs[$PLID]['Z']->ClickID(5)->T(184)->L(76)->W(25)->H(6)->BStyle(ISB_DARK + 5)->Send()->W(0)->H(0);

				# Distance Delta
				$BTN->ClickID(16)->T(178)->L(101)->W(3)->H(6)->BStyle(ISB_DARK + 3)->Text('V')->Send();
				$BTN->ClickID(26)->T(178)->L(104)->W(19)->H(6)->BStyle(ISB_DARK + 3)->Text('Distance Delta')->Send();
				$BTN->ClickID(36)->T(178)->L(123)->W(3)->H(6)->BStyle(ISB_DARK + 3)->Text('V')->Send();
				$this->BTNs[$PLID]['Dist'] = new IS_BTN;
				$this->BTNs[$PLID]['Dist']->ClickID(6)->T(184)->L(101)->W(25)->H(6)->BStyle(ISB_DARK + ISB_RIGHT + 3)->Send()->W(0)->H(0);

				# Total Distance
				$BTN->ClickID(17)->T(178)->L(126)->W(3)->H(6)->BStyle(ISB_DARK + 6)->Text('V')->Send();
				$BTN->ClickID(27)->T(178)->L(129)->W(19)->H(6)->BStyle(ISB_DARK + 6)->Text('Total Distance')->Send();
				$BTN->ClickID(37)->T(178)->L(148)->W(3)->H(6)->BStyle(ISB_DARK + 6)->Text('V')->Send();
				$this->BTNs[$PLID]['Totl'] = new IS_BTN;
				$this->BTNs[$PLID]['Totl']->ClickID(7)->T(184)->L(126)->W(25)->H(6)->BStyle(ISB_DARK + ISB_RIGHT + 6)->Send()->W(0)->H(0);

				# 
				$this->TOTALs[$PLID] = 0;
			}
			$BTN->ClickID(47)->T(190)->L(101)->W(50)->H(6)->BStyle(ISB_LIGHT + ISB_RIGHT + 6)->Send()->W(0)->H(0);
			$BTN->ClickID(57)->T(190)->L(151)->W(12)->H(6)->BStyle(ISB_LIGHT + ISB_RIGHT + 4)->Send()->W(0)->H(0);

			// Setup our Coord data.
			$lCoords = (isset($this->COORDs[$PLID])) ? $this->COORDs[$PLID] : $CompCar;
			$cCoords = $CompCar;

			// Calculate Distance
			$X = abs($cCoords->X - $lCoords->X);
			$Y = abs($cCoords->Y - $lCoords->Y);
			$Z = abs($cCoords->Z - $lCoords->Z);
			$D = round(sqrt(pow($X, 2) + pow($Y, 2) + pow($Z, 2)));

			// Update Buttons
			$this->BTNs[$PLID]['X']->Text(number_format($X))->Send();
			$this->BTNs[$PLID]['Y']->Text(number_format($Y))->Send();
			$this->BTNs[$PLID]['Z']->Text(number_format($Z))->Send();
			$this->BTNs[$PLID]['Dist']->Text(number_format($D))->Send();
			$this->BTNs[$PLID]['Totl']->Text(number_format($this->TOTALs[$PLID] += $D))->Send();
			$binary = base_convert($this->BTNs[$PLID]['Totl']->Text, 10, 2);
			$BTN->ClickID(47)->Text($binary)->Send(); # Binary Total Distance
			$BTN->ClickID(57)->Text(strlen($binary) . ' bits')->Send(); # Binary Bit Count
			$this->COORDs[$PLID] = $cCoords;
		}
	}
}
?>