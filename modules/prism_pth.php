<?php
/**
 * PHPInSimMod - PTH Module
 * @package PRISM
 * @subpackage PTH
*/

class PTH
{
	const HEADER = 'CVersion/CRevision/lNodes/lFinishLine';

	public $Version;
	public $Revision;
	public $Nodes;
	public $FinishLine;

	public function __construct($pthFilePath)
	{
		$this->file = file_get_contents($pthFilePath);
		$this->readHeader($this->file);
		for ($Node = 0; $Node < $this->Nodes; ++$Node)
			$this->Node[$Node] = $this->readNode($Node);
		unset($this->file);
	}
	protected function readHeader()
	{
		foreach (unpack(PTH::HEADER, substr($this->file, 6, 10)) as $property => $value)
			$this->$property = $value;
	}
	protected function readNode($Node)
	{
		$RawNode = substr($this->file, 16 + ($Node * 40), 40);
		$NodeObj = new Node;
		$NodeObj->readCenter($RawNode);
		$NodeObj->readDirection($RawNode);
		$NodeObj->readLimit($RawNode);
		$NodeObj->readRoad($RawNode);
		return $NodeObj;
	}
}
class Node
{
	const CENTER = 'lX/lY/lZ';
	const DIRECTION = 'fX/fY/fZ';
	const LIMIT = 'fLeft/fRight';
	const ROAD = 'fLeft/fRight';

	public $Center;
	public $Direction;
	public $Limit;
	public $Road;

	public function readCenter($RawNode)
	{
		$this->Center = (object) unpack(Node::CENTER, substr($RawNode, 0, 12));
	}
	public function readDirection($RawNode)
	{
		$this->Direction = (object) unpack(Node::DIRECTION, substr($RawNode, 12, 12));
	}
	public function readLimit($RawNode)
	{
		$this->Limit = (object) unpack(Node::LIMIT, substr($RawNode, 24, 8));
	}
	public function readRoad($RawNode)
	{
		$this->Road = (object) unpack(Node::ROAD, substr($RawNode, 32, 8));
	}
}
?>