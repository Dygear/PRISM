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
			$obj = new LayoutObject($x, $y, $Info->Z/65536, LayoutObject::$OBJ_REAL_OBJECT, $Info->Heading / 32768, 136 + ($Info->PLID % 4), 0);
			
			$key = $x.':'.$y;
			
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
			
			if (count($this->PylonList) > 500)
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
			
			$AXM = new IS_AXM();
			$AXM->PMOAction(PMO_ADD_OBJECTS)->Info(array($obj))->send();
		}
	}
}
?>