<?php
/**
 * PHPInSimMod - SMX Module
 * @package PRISM
 * @subpackage SMX
*/
// Simple Mesh eXport
class SMX
{
	const HEADER = 'a6LFSSMX/CGameVersion/CGameRevision/CSMXVersion/xDimensions/CResolution/CVertexColors/x4/a32Track/x3GroundColor/x9/lObjects';
	const COLOR = 'CR/CG/CB';

	public $LFSSMX = 'LFSSMX';
	public $GameVersion = NULL;
	public $GameRevision = NULL;
	public $SMXVersion = 0;
	public $Dimensions = 3;
	public $Resolution;
	public $VertexColors = 1;
	public $Track;
	public $GroundColor;
	public $Objects;
	public $Object = array();

	public function __construct($smxFilePath)
	{
		$this->file = file_get_contents($smxFilePath);

		if ($this->readHeader($this->file) === TRUE)
			return; # trigger_error returns (bool) TRUE, so if the return is true, there was an error.

		for ($i = 0, $offset = 64, $i < $this->Objects; ++$i)
			$this->Object[$i] = $this->readObject($offset);
		unset($this->file);
		
		return $this;
	}
	protected function readHeader()
	{
		if (substr($this->file, 0, 6) !== 'LFSSMX')
			return trigger_error('This is not an LFS SMX file.', E_USER_ERROR);

		foreach (unpack(SMX::HEADER, substr($this->file, 6, 58)) as $property => $value)
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

	public $Center = array();
	public $Radius = array();
	public $Points = array();
	public $Triangles = array();

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