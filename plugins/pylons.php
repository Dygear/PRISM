<?php
require_once('plugins/layout/LayoutObject.php');

class pylons extends Plugins
{
	const URL = '..';
	const NAME = 'Pylons AutoX Objects Demo';
	const AUTHOR = "Kai Lochbaum aka GeForz";
	const VERSION = 'demo';
	const DESCRIPTION = 'Paints Pylons!';
	
	private $Pylons = array();
	private $PylonList = array();
	
	public function __construct()
	{
		$this->registerPacket('onPrismConnect', ISP_VER);
		$this->registerPacket('onMCI', ISP_MCI);
		$this->registerPacket('onObjectHit', ISP_OBH);
	}
	
	public function onPrismConnect(IS_VER $VER)
	{
		$AXM = new IS_AXM();
		$AXM->PMOAction(PMO_CLEAR_ALL)->send();
	}

	
	public function onMCI(IS_MCI $MCI)
	{
		// drop pylon on mci place :O
		foreach($MCI->Info as $Info)
		{
			$x = $Info->X/65536;
			$y = $Info->Y/65536;
			$obj = new LayoutObject($x, $y, $Info->Z/65536, LayoutObject::$OBJ_REAL_OBJECT, 0, 136 + ($Info->PLID % 4), 0);
			
			// THIS IS BAD! Listen for new added object packet from lfs and use those...
			$key = $obj->rawX().':'.$obj->rawY();
			
			for($i = 0; $i < count($this->PylonList); $i++)
			{
				if ($this->PylonList[$i] == $key)
				{
					unset($this->PylonList[$i]);
					$this->PylonList = array_values($this->PylonList);
					break;
				}
			}
			$this->PylonList[] = $key;
			$this->Pylons[$key] = $obj;
			
			/*
			if (count($this->PylonList) > 700)
			{
				$obsolete = array_slice($this->PylonList, 0, 30);
				$this->PylonList = array_values(array_slice($this->PylonList, 30));
				
				$obsolete_items = array();
				foreach ($obsolete as $obskey)
				{
					$obsolete_items[] = $this->Pylons[$obskey];
					unset($this->Pylons[$obskey]);
				}
				
				$AXM = new IS_AXM();
				$AXM->PMOAction(PMO_DEL_OBJECTS)->Info($obsolete_items)->send();
			}
			*/
			
			if (count($this->PylonList) < 500)
			{
				$AXM = new IS_AXM();
				$AXM->PMOAction(PMO_ADD_OBJECTS)->Info(array($obj))->send();
				echo "New Object at " . $key . "\n";
			}
		}
	}
	
	public function onObjectHit(IS_OBH $OBH)
	{
		if (($OBH->OBHFlags & (OBH_LAYOUT | OBH_ON_SPOT)) != (OBH_LAYOUT | OBH_ON_SPOT))
		{
			// only modify added objects in their original spot
			return;
		}
		
		$key = $OBH->X.':'.$OBH->Y;
		
		if (isset($this->Pylons[$key]))
		{
			// move object out of the way
			$obj = $this->Pylons[$key];
			$AXM = new IS_AXM();
			$AXM->PMOAction(PMO_DEL_OBJECTS)->Info(array($obj))->send();
			unset($this->Pylons[$key]);
			
			$xyh = new XYHelper($OBH->C->Heading / 128 * 180);
			$xyh->goMeter($obj->x(), $obj->y(), 5);
			$obj->setX($xyh->fX);
			$obj->setY($xyh->fY);
			$AXM->PMOAction(PMO_ADD_OBJECTS)->Info(array($obj))->send();
			$this->Pylons[$obj->rawX().':'.$obj->rawY()] = $obj;
		}
		else
		{
			echo "unknown object at: " . $key;
		}
	}
}


class XYHelper {
	
	private $heading;
	
	public $fX;
	public $fY;
	
	private $headX;
	private $headY;
	private $orthX;
	private $orthY;
	
	/** Creates the helper which helps adding values to a point with heading... */
	public function __construct($heading) {
		$this->setHeading($heading);
	}
	
	public function goMeter($originX, $originY, $front = 1, $right = 0) {
		$this->fX = $originX + $front * $this->headX + $right * $this->orthX;
		$this->fY = $originY + $front * $this->headY + $right * $this->orthY;
	}
	
	/** Heading in deg! */
	public function setHeading($heading) {
		$this->heading = $heading;
		
		$arc = -pi() * $heading / 180;
		if ($arc < 0) {
			$arc = 2 * pi() + $arc;
		}
		
		// calculate heading and orthogonal (right) "vector"
		$this->headX = sin($arc);
		$this->headY = cos($arc);
		$this->orthX = $this->headY;
		$this->orthY = - $this->headX;
	}
	
	public function headingDegCalc($add) {
		$ret = $heading + $add;
		if ($ret > 180)
			$ret -= 360;
		else if ($ret <= -180) {
			$ret += 360;
		}
		return $ret;
	}

	/** Calculates the angle between two points in lfs-manner... i.e. 0deg is Y+; +90 is x-*/
	public static function headingBetweenPoints($positionX, $positionY, $targetX, $targetY) {
		return rad2deg(atan2($positionX - $targetX, $targetY - $positionY));
	}
}
?>