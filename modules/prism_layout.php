<?php

class LayoutParser {
	
	private $version = -1;
	private $revision = -1;
	private $numObjects = -1;
	private $laps = -1;
	
	/** Base path for layouts. Ends on / */
	private $basePath;
	
	private $objects = array();
	
	const UNPACK = 'x6/Cversion/Crevision/vnumObjects/Claps/x';
	const PACK = 'a6CCvCC';
	
	public function __construct($path) {
		if (substr($path, -1) != '/') {
			$path .= '/';
		}
		
		$this->basePath = $path;
	}
	
	public function __toString()
	{
		$retVal  = "Version: ".$this->version."\n";
		$retVal .= "Revision: ".$this->revision."\n";
		$retVal .= "NumObjects: ".count($this->objects)."\n";
		$retVal .= "Laps: ".$this->laps."\n";
		
		$retVal .= "Objects: ".$this->laps."\n---------------------------------------\n\n";
		foreach ($this->objects as $obj)
		{
			$retVal .= (string) $obj."\n";
		}
		
		
		return $retVal;
	}
	
	/**
	 * Reads a layout from the file.
	 * @param shortTrackName Track name like FE1 or BL1
	 * @param Layoutname Name for the layout behind the BL1_
	 */
	public function parseLayout($shortTrackName, $layoutName) {
		$lyt = file_get_contents($this->basePath . $shortTrackName . '_' . $layoutName . '.lyt');
		
		foreach (unpack(Layout::UNPACK, substr($lyt, 0, 12)) as $property => $value) {
			$this->$property = $value;
		}
		
		for ($i = 0; $i < $this->numObjects; $i++) {
			$object = new LayoutObject(substr($lyt, 12 + $i * 8, 8));
			// stored with xyindex key to easily find objects again
			$this->objects[$object->x().':'.$object->y()] = $object;
		}
	}
	
	/**
	 * Whether or not the object is an marshal object
	 * @return boolean
	 */
	private function isMarshal($object) {
		return $object instanceof MarshalObject
		||	$object->type() == LayoutObject::OBJ_MARSHALL
		||	$object->type() == LayoutObject::OBJ_MARSHALL_LEFT
		||	$object->type() == LayoutObject::OBJ_MARSHALL_RIGHT;
	}
	/**
	 * Write the layout in a file.
	 * @param shortTrackName Track name like FE1 or BL1
	 * @param Layoutname Name for the layout behind the BL1_
	 */
	public function writeLayout($shortTrackName, $layoutName) {
		$out = pack(self::PACK, 'LFSLYT', $this->version, $this->revision, count($this->objects), $this->laps, 0x01);
		
		foreach ($this->objects as $object) {
			$out .= $object->pack();
		}
		
		$lyt = file_put_contents($this->basePath . $shortTrackName . '_' . $layoutName . '.lyt', $out);
	}
	
	public function clearLayout() {
		$this->objects = array();
	}
	
	public function addObject($obj) {
		// send insim add packet...
		$this->removeObject();
		$this->objects[$obj->x().':'.$obj->y()] = $obj;
	}
	
	public function removeObject($obj) {
		// send insim remove packet...
		unset($this->objects[$obj->x().':'.$obj->y()]);
	}
	
	public function findObject($x, $y)
	{
		return isset($this->objects[$x.$y.$index])?$this->objects[$x.$y.$index]:null;
	}
}

?>