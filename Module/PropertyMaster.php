<?php

namespace PRISM\Module;

abstract class PropertyMaster
{
    public function __get($property)
	{
		return (isset($this->$property)) ? $this->$property : $return = NULL;
	}
}
