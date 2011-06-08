<?php
class distance extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Distance Meter';
	const AUTHOR = "Mark 'Dygear' Tomlin";
	const VERSION = '0.1.0';
	const DESCRIPTION = 'Shows a distance meter.';

	private $BTNs = array(); # Array of IS_BTN instances.
	private $COORDs = array(); # Array of the last known coords for each player.
	private $TOTALs = array(); # Array of total distance traveled by each player.

	public function __construct()
	{
		$this->registerPacket('onMCI', ISP_MCI);
	}
	public function onMCI($Packet)
	{
		$PPS = $this->getHostInfo()->getPPS();
		foreach ($Packet->Info as $CompCar)
		{
			$PLID = $CompCar->PLID;
			$PacketData = Plugins::getClientByPLID($PLID);
			if (isset($PacketData))
			{
				$UCID = $PacketData->UCID; 
				// Spawn a new button instance if one is not here.
				$BTN = new IS_BTN;
				if (!isset($this->BTNs[$PLID]))
				{
					$BTN->UCID($UCID);
					# X Delta
					$BTN->ClickID(13)->T(178)->L(26)->W(3)->H(6)->BStyle(ISB_DARK + 4)->Text('V')->Send();
					$BTN->ClickID(23)->T(178)->L(29)->W(19)->H(6)->BStyle(ISB_DARK + 4)->Text('X Delta')->Send();
					$BTN->ClickID(33)->T(178)->L(48)->W(3)->H(6)->BStyle(ISB_DARK + 4)->Text('V')->Send();
					$this->BTNs[$PLID]['X'] = new IS_BTN;
					$this->BTNs[$PLID]['X']->UCID($UCID)->ClickID(3)->T(184)->L(26)->W(25)->H(6)->BStyle(ISB_DARK + 4)->Send();

					# Y Delta
					$BTN->ClickID(14)->T(178)->L(51)->W(3)->H(6)->BStyle(ISB_DARK + 1)->Text('V')->Send();
					$BTN->ClickID(24)->T(178)->L(54)->W(19)->H(6)->BStyle(ISB_DARK + 1)->Text('Y Delta')->Send();
					$BTN->ClickID(34)->T(178)->L(73)->W(3)->H(6)->BStyle(ISB_DARK + 1)->Text('V')->Send();
					$this->BTNs[$PLID]['Y'] = new IS_BTN;
					$this->BTNs[$PLID]['Y']->UCID($UCID)->ClickID(4)->T(184)->L(51)->W(25)->H(6)->BStyle(ISB_DARK + 1)->Send();

					# Z Delta
					$BTN->ClickID(15)->T(178)->L(76)->W(3)->H(6)->BStyle(ISB_DARK + 5)->Text('V')->Send();
					$BTN->ClickID(25)->T(178)->L(79)->W(19)->H(6)->BStyle(ISB_DARK + 5)->Text('Z Delta')->Send();
					$BTN->ClickID(35)->T(178)->L(98)->W(3)->H(6)->BStyle(ISB_DARK + 5)->Text('V')->Send();
					$this->BTNs[$PLID]['Z'] = new IS_BTN;
					$this->BTNs[$PLID]['Z']->UCID($UCID)->ClickID(5)->T(184)->L(76)->W(25)->H(6)->BStyle(ISB_DARK + 5)->Send();

					# Distance Delta
					$BTN->ClickID(16)->T(178)->L(101)->W(3)->H(6)->BStyle(ISB_DARK + 3)->Text('V')->Send();
					$BTN->ClickID(26)->T(178)->L(104)->W(19)->H(6)->BStyle(ISB_DARK + 3)->Text('Distance Delta')->Send();
					$BTN->ClickID(36)->T(178)->L(123)->W(3)->H(6)->BStyle(ISB_DARK + 3)->Text('V')->Send();
					$this->BTNs[$PLID]['Dist'] = new IS_BTN;
					$this->BTNs[$PLID]['Dist']->UCID($UCID)->ClickID(6)->T(184)->L(101)->W(25)->H(6)->BStyle(ISB_DARK + ISB_RIGHT + 3)->Send();

					# Total Distance
					$BTN->ClickID(17)->T(178)->L(126)->W(3)->H(6)->BStyle(ISB_DARK + 6)->Text('V')->Send();
					$BTN->ClickID(27)->T(178)->L(129)->W(19)->H(6)->BStyle(ISB_DARK + 6)->Text('Total Distance')->Send();
					$BTN->ClickID(37)->T(178)->L(148)->W(3)->H(6)->BStyle(ISB_DARK + 6)->Text('V')->Send();
					$this->BTNs[$PLID]['Totl'] = new IS_BTN;
					$this->BTNs[$PLID]['Totl']->UCID($UCID)->ClickID(7)->T(184)->L(126)->W(25)->H(6)->BStyle(ISB_DARK + ISB_RIGHT + 6)->Send();

					# These would be the bit length display buttons.
					$this->BTNs[$PLID]['Bits'] = new IS_BTN;
					$this->BTNs[$PLID]['Bits']->UCID($UCID)->ClickID(47)->T(190)->L(101)->W(50)->H(6)->BStyle(ISB_LIGHT + ISB_RIGHT + 6)->Send();
					$this->BTNs[$PLID]['BitC'] = new IS_BTN;
					$this->BTNs[$PLID]['BitC']->UCID($UCID)->ClickID(57)->T(190)->L(151)->W(12)->H(6)->BStyle(ISB_LIGHT + ISB_RIGHT + 4)->Send();

					$this->BTNs[$PLID]['MPS'] = new IS_BTN;
					$this->BTNs[$PLID]['MPS']->UCID($UCID)->ClickID(8)->T(190)->L(26)->W(25)->H(6)->BStyle(ISB_LIGHT + 4)->Send();
					$this->BTNs[$PLID]['MPH'] = new IS_BTN;
					$this->BTNs[$PLID]['MPH']->UCID($UCID)->ClickID(18)->T(190)->L(51)->W(25)->H(6)->BStyle(ISB_LIGHT + 4)->Send();
					$this->BTNs[$PLID]['KPH'] = new IS_BTN;
					$this->BTNs[$PLID]['KPH']->UCID($UCID)->ClickID(28)->T(190)->L(76)->W(25)->H(6)->BStyle(ISB_LIGHT + 4)->Send();

					# (Re)set the total distance.
					$this->TOTALs[$PLID] = 0;
				}
				// Setup our Coord data.
				$lCoords = (isset($this->COORDs[$PLID])) ? $this->COORDs[$PLID] : $CompCar;
				$cCoords = $CompCar;

				// Calculate Distance
				$X = abs($cCoords->X - $lCoords->X);
				$Y = abs($cCoords->Y - $lCoords->Y);
				$Z = abs($cCoords->Z - $lCoords->Z);
				$D = round(sqrt(($X * $X) + ($Y * $Y) + ($Z * $Z)));
				$T = $this->TOTALs[$PLID] += $D;
				$B = base_convert($T, 10, 2);

				// Caclulate Speed
				$MPS = number_format($CompCar->Speed / 327.68, 1);		# Meters Per Second
				$MPH = number_format($CompCar->Speed / 146.486067, 1);	# Miles Per Hour
				$KPH = number_format($CompCar->Speed / 91.01, 1);		# Kilometers Per Hour

				// Update Buttons
				$this->BTNs[$PLID]['X']->Text(number_format($X))->Send();
				$this->BTNs[$PLID]['Y']->Text(number_format($Y))->Send();
				$this->BTNs[$PLID]['Z']->Text(number_format($Z))->Send();
				$this->BTNs[$PLID]['Dist']->Text(number_format($D))->Send();
				$this->BTNs[$PLID]['Totl']->Text(number_format($T))->Send();
				$this->BTNs[$PLID]['Bits']->Text($B)->Send(); # Binary Total Distance
				$this->BTNs[$PLID]['BitC']->Text(strlen($B) . ' bits')->Send(); # Binary Bit Count

				$this->BTNs[$PLID]['MPS']->Text($MPS)->Send();
				$this->BTNs[$PLID]['MPH']->Text($MPH)->Send();
				$this->BTNs[$PLID]['KPH']->Text($KPH)->Send();

				$this->COORDs[$PLID] = $cCoords;
			}
		}
	}
}
?>