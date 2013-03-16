<?php

namespace PRISM\Module;

use Module\IniLoader;

require 'IniLoader.php';

abstract class SectionHandler extends \Module\IniLoader
{
	abstract public function init();
}
