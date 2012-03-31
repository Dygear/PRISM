<?php
/**
 * PHPInSimMod - Geometry Module
 * @package PRISM
 * @subpackage Geometry
*/

class Point2D
{
    public $x = 0;
    public $y = 0;
    
    public function __construct($x = 0, $y = 0)
    {
        $this->x = $x;
        $this->y = $y;
    }
}

class Polygon2D
{
    public $Points      = array();
    public $NumPoints   = 0;
    
    public function __construct(array $points = array())
    {
        $this->Points       = $points;
        $this->NumPoints    = count($points);
    }
    
    public function __destruct()
    {
        array_splice($this->Points, 0, $this->NumPoints);
    }
    
    //  Simple check for convex polygons
    public function contains(Point2D $p)
    {
        $p1 = $this->Points[0];
        $p2 = $this->Points[1];
        $p3 = $this->Points[2];
        $p4 = $this->Points[3];

        if (($p->y - $p1->y) * ($p2->x - $p1->x) - ($p->x - $p1->x) * ($p2->y - $p1->y) >= 0 &&
            ($p->y - $p3->y) * ($p4->x - $p3->x) - ($p->x - $p3->x) * ($p4->y - $p3->y) >= 0)
        {
            $p1 = $this->Points[1];
            $p2 = $this->Points[2];
            $p3 = $this->Points[3];
            $p4 = $this->Points[0];

            if (($p->y - $p1->y) * ($p2->x - $p1->x) - ($p->x - $p1->x) * ($p2->y - $p1->y) >= 0 &&
                ($p->y - $p3->y) * ($p4->x - $p3->x) - ($p->x - $p3->x) * ($p4->y - $p3->y) >= 0)
            {
                return true;
            }
        }
    }
}

// Simple polygon grid.
// You give it a number of rows and columns and the radius of the grid in the constructor.
// Polygons will be stored in all the grid blocks that they intersect with.
// Easiest method to create a new PolyGrid is via the static method PolyGrid::fromArray (see below).
// Or otherwise create a blank PolyGrid and use the insert method to add polygons one at a time.
// You can then get the polygons in a certain block through the method getArrayByPoint (see below).
class PolyGrid
{
    private $Radius;
    private $Rows;
    private $Cols;
    private $RowHeight;
    private $ColWidth;
    private $Grid = array();
    private $GridCounter = array();
    
    public function __construct($rows = 2, $cols = 2, $radius = 0x7FFFFFFF)
    {
        $this->Radius = $radius;
        $this->Rows = $rows;
        $this->Cols = $cols;
        $this->RowHeight = ($this->Radius * 2) / $this->Cols;
        $this->ColWidth = ($this->Radius * 2) / $this->Rows;
        
        // Create grid
        for ($a = 0; $a < $rows; $a++)
        {
            $this->Grid[] = array();
            $this->GridCounter[] = array();
            for ($b = 0; $b < $cols; $b++)
            {
                $this->Grid[$a][] = array();
                $this->GridCounter[$a][] = 0;
            }
        }
    }
    
    public function __destruct()
    {
        for ($a = 0; $a < $this->Rows; $a++)
        {
            array_splice($this->Grid[$a], 0, $this->Cols);
        }
        array_splice($this->Grid, 0, $this->Rows);
    }
    
    public function insert(PolyGridData $data)
    {
        $q = array();
        foreach ($data->Poly->Points as $point)
        {
            // find block in grid
            $gi = $this->getGridIndexByPoint($point);
            if ($gi !== null && !in_array($data, $this->Grid[$gi[0]][$gi[1]]))
            {
                $this->Grid[$gi[0]][$gi[1]][] = $data;
                $this->GridCounter[$gi[0]][$gi[1]]++;
            }
        }
    }
    
    // $point - the point lying in the grid block we're looking for
    // $polyArray - the found grid block will be written to this parameter
    // Returns the number of polygons in the block or -1 if no grid block found
    public function getArrayByPoint(Point2D $point, array &$polyArray)
    {
        $gi = $this->getGridIndexByPoint($point);
        if ($gi !== null)
        {
            $polyArray = $this->Grid[$gi[0]][$gi[1]];
            return $this->GridCounter[$gi[0]][$gi[1]];
        }
        return -1;
    }
    
    private function getGridIndexByPoint(Point2D $point)
    {
        $x = -$this->Radius;
        $y = -$this->Radius;
        
        for ($a = 0; $a < $this->Rows; $a++)
        {
            // find row
            if ($point->y > $y && $point->y < $y + $this->RowHeight)
            {
                // Find column
                for ($b = 0; $b < $this->Cols; $b++)
                {
                    if ($point->x > $x && $point->x < $x + $this->ColWidth)
                    {
                        return array($a, $b);
                    }
                    $x += $this->ColWidth;
                }
                
                break;
            }
            $y += $this->RowHeight;
        }
        
        return null;
    }
    
    // Creates a new PolyGrid object and populates it with given array of polygons
    public static function fromArray(array &$polygons, $rows = 2, $cols = 2, $radius = 0x7FFFFFFF)
    {
        $qt = new PolyGrid($rows, $cols, $radius);
        
        foreach ($polygons as $i => $poly)
        {
            $qt->insert(new PolyGridData($i, $poly));
        }
        
        return $qt;
    }
}

class PolyGridData
{
    public $Key;
    public $Poly;
    
    public function __construct($key, Polygon2D $poly)
    {
        $this->Key = $key;
        $this->Poly = $poly;
    }
}

?>