<?php
/**
 * PHPInSimMod - SMX Module
 * @package PRISM
 * @subpackage SMX
*/

class SMX
{
	const HEADER = 'x6LFSSMX/xVersion/xRevision/CVersion/xDimensions/CResolution/xVertex/x4/a32Track/x3GroundColor/x9/lObjects';
	const COLOR = 'CR/CG/CB';

	public $Version;
	public $Resolution;
	public $Track;
	public $GroundColor;
	public $Objects;

	public function __construct($smxFilePath)
	{
		$this->file = file_get_contents($smxFilePath);
		$this->readHeader($this->file);
		for ($i = 0, $Objects = $this->Objects, $this->Objects = array(), $offset = 64; $i < $Objects; ++$i)
			$this->Objects[$i] = $this->readObject($offset);
		unset($this->file);
	}
	protected function readHeader()
	{
		foreach (unpack(SMX::HEADER, substr($this->file, 0, 64)) as $property => $value)
			$this->$property = $value;
		$this->GroundColor = unpack(SMX::COLOR, substr($this->file, 48, 3));
	}
	protected function readObject(&$offset)
	{
		return new Object($offset, $this->file);
	}
}
class Object
{
	const CENTER = 'lX/lY/lZ';
	const OBJECT = 'lRadius/lPoints/lTriangles';
	const POINT = 'lX/lY/lZ/lColour';
	const TRIANGLE = 'vA/vB/vC/x2';

	public $Center;
	public $Radius;
	public $Points;
	public $Triangles;

	public function __construct(&$offset, $file)
	{
		# Center
		$this->Center = unpack(Object::CENTER, substr($file, $offset, 12));
		$offset += 12;
		# Object
		$Object = unpack(Object::OBJECT, substr($file, $offset, 12));
		foreach ($Object as $property => $value)
			$this->$property = $value;
		$offset += 12;
		# Point
		for ($i = 0, $Points = $this->Points, $this->Points = array(); $i < $Points; ++$i, $offset += 16)
			$this->Points[$i] = unpack(Object::POINT, substr($file, $offset, 16));
		# Triangle
		for ($i = 0, $Triangles = $this->Triangles, $this->Triangles = array(); $i < $Triangles; ++$i, $offset += 8)
			$this->Triangles[$i] = unpack(Object::TRIANGLE, substr($file, $offset, 8));
	}
}
?>