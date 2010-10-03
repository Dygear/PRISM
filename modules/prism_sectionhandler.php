<?php

require_once(ROOTPATH . '/modules/prism_iniloader.php');

abstract class SectionHandler extends IniLoader
{
	abstract public function initialise();
}

?>