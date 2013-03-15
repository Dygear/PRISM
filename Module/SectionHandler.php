<?php
/**
 * PHPInSimMod - SectionHandler Module
 * @package PRISM
 * @subpackage SectionHandler
*/
namespace PRISM\Module\SectionHandler;
use PRISM\Module\IniLoader;

//require_once(ROOTPATH . '/modules/prism_iniloader.php');

abstract class SectionHandler extends IniLoader
{
	abstract public function initialise();
}

?>