<?php

/**
 * "Singleton" class to manage buttons!
 */

define('BM_MAX_BUTTONS', 240);

class ButtonManager
{
	// poor man's debugging
	private static function debug()
	{
		// disabled
		return;
		
		global $PRISM;
		$trace = debug_backtrace();
		console($trace[1]['function']);
		foreach (self::$buttons as $hostId => $users)
		{
			console("\t".$hostId . ' {');
			foreach ($users as $UCID => $buttons)
			{
				$out = "Conn ".$UCID.': ';
				foreach ($buttons as $clickID => $BTN)
				{
					if ($BTN != null)
						$out .= $clickID.' ';
				}
				console("\t\t$out");
			}
			console("\t}");
		}
	}
	
	private static $buttons = array();

	/** Called by Button->send(). Assigns unique clickId. */
	public static function registerButton(Button $BTN, $hostId = null)
	{
		if ($BTN->ClickID != -1)
		{
			// clickid already set 
			return true;
		}
		
		self::debug();
		
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		// make sure hostid is set in array
		if (!isset(self::$buttons[$hostId]))
		{
			self::$buttons[$hostId] = array();
		}
		
		if ($BTN->UCID == 255) {
			// TODO special handling...
		}
		else {
			// make sure the button-reservation array exists
			if (!isset(self::$buttons[$hostId][$BTN->UCID]))
			{
				self::$buttons[$hostId][$BTN->UCID] = array_fill(0, BM_MAX_BUTTONS, null);
			}
			$ids = self::$buttons[$hostId][$BTN->UCID];
			
			// get first free id
			$id = -1;
			for ($i = 0; $i < BM_MAX_BUTTONS; $i++)
			{
				if ($ids[$i] === null)
				{
					$id = $i;
					break;
				}
			}
			
			if ($id === -1)
			{
				echo "No free ButtonID found."; // add "paging" here...
				return false;
			}
			else {
				self::$buttons[$hostId][$BTN->UCID][$id] = $BTN;
				return $id;
			}
		}
		
		return false;
	}
	
// removal
	public static function removeButtonByKey($UCID, $key, $hostId = NULL)
	{
		$button = self::getButtonForKey($UCID, $key, $hostId);
		if ($button != null)
		{
			self::removeButton($button, $hostId);
		}
	}
	
	public static function removeButtonsByGroup($UCID, $group, $hostId = NULL)
	{
		$buttons = self::getButtonsForGroup($UCID, $group, $hostId);
		foreach ($buttons as $button)
		{
			self::removeButton($button, $hostId);
		}
	}
	
	public static function removeButton(Button $BTN, $hostId = NULL)
	{
		self::debug();
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		
		// send delete command
		$bfn = new IS_BFN;
		$bfn->SubT(BFN_DEL_BTN)->UCID($BTN->UCID)->ClickID($BTN->ClickID)->Send();
		
		// remove button from button array
		self::$buttons[$hostId][$BTN->UCID][$BTN->ClickID] = null;
		
		self::debug();
	}
	
	
// interaction
	public static function onButtonClick(IS_BTC $BTC)
	{
		self::debug();
		global $PRISM;
		$hostId = $PRISM->hosts->curHostID;
		
		if (!isset(self::$buttons[$hostId][$BTC->UCID][$BTC->ClickID]))
		{
			console('ERROR: Received click for unknown button!');
			var_dump($BTC);
			var_dump(self::$buttons);
		}
		else
		{
			$button = self::$buttons[$hostId][$BTC->UCID][$BTC->ClickID];
			$button->click($BTC);
		}
		
		self::debug();
	}
	public static function onButtonText(IS_BTT $BTT)
	{
		self::debug();
		global $PRISM;
		$hostId = $PRISM->hosts->curHostID;
		
		if (!isset(self::$buttons[$hostId][$BTT->UCID][$BTT->ClickID]))
		{
			console('ERROR: Received button text for unknown button!');
			var_dump($BTC);
			var_dump(self::$buttons);
		}
		else
		{
			$button = self::$buttons[$hostId][$BTT->UCID][$BTT->ClickID];
			$button->enterText($BTT);
		}
	}
	
	
	
//Getter methods	
	public static function getButtonForKey($UCID, $key, $hostId = NULL)
	{
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		
		if (isset(self::$buttons[$hostId][$UCID]))
		{
			foreach (self::$buttons[$hostId][$UCID] as $button)
			{
				if ($button != null && $button->key() == $key)
				{
					return $button;
				}
			}
		}
		
		return null;
	}
	
	public static function getButtonsForGroup($UCID, $group, $hostId = NULL)
	{
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		
		$buttons = array();
		
		if (isset(self::$buttons[$hostId][$UCID]))
		{
			foreach (self::$buttons[$hostId][$UCID] as $button)
			{
				if ($button != null && $button->group() == $group)
				{
					$buttons[] = $button;
				}
			}
		}
		
		return $buttons;
	}
	
	
	
	public static function clearButtonsForConn($UCID, $hostId = NULL)
	{
		$hostButtons = self::buttonsForHost($hostId);
		unset($hostButtons[$UCID]);
	}

	/*
	public static function clearAllButtons($hostId = NULL)
	{
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		
		unset(self::$buttons[$hostId]); // does unset trigger a not set notice?
	}
	
	private static function buttonsForConn($UCID, $hostId = NULL)
	{
		$hostButtons = self::buttonsForHost($hostId);
		
		if (!isset($hostButtons[$UCID]))
		{
			$hostButtons[$UCID] = array();
		}
		
		return $hostButtons[$UCID];
	}
	*/
	
	private static function buttonsForHost($hostId = NULL)
	{
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		
		if (!isset(self::$buttons[$hostId]))
		{
			self::$buttons[$hostId] = array();
		}
		
		return self::$buttons[$hostId];
	}
	
	/*
	public static function removeButton(Button $BTN, $hostId = NULL)
	{
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		
		// send delete command
		$bfn = new IS_BFN;
		$bfn->SubT(BFN_DEL_BTN)->UCID($BTN->UCID)->ClickID($BTN->ClickID)->Send();
		
		// remove button from button array
		unset(self::$buttons[$hostId][$BTN->UCID][$BTN->ClickID]);
	}
	*/

// Area reserving	
	
	private static $reservedAreas = array();
	
	/**
	 * Reserves an area for button display.
	 * This is a hint to avoid overlapping buttons. There are no hard reservations
	 * build into the button management system.
	 * @param int $L @see IS_BTN L parameter
	 * @param int $T @see IS_BTN T parameter
	 * @param int $W @see IS_BTN W parameter
	 * @param int $H @see IS_BTN H parameter
	 * @return false if part of the area was already reserved
	 */
	public static function reserveArea($L = -1, $T = -1, $W = -1, $H = -1)
	{
		$L = $L<0?IS_Y_MIN:$L;
		$T = $T<0?IS_X_MIN:$T;
		$W = $W<0?IS_Y_MAX:$W;
		$H = $H<0?(IS_X_MAX - IS_X_MIN):$H;
		
		foreach (self::$reservedAreas as $area)
		{
			if (
				$area['L'] < ($L + $W)
				&&
				($area['L'] + $area['W']) > $L
				&&
				$area['T'] < ($T + $H)
				&&
				($area['T'] + $area['H']) > $T
			)
			{
				console('Area already reserved. L:'.$area['L'].", T:".$area['T'].", W:".$area['W'].", H:".$area['H']);
				return false;
			}
		}
		
		self::$reservedAreas[] = array(
			'L' => $L,
			'T' => $T,
			'W' => $W,
			'H' => $H
		);
		
		return true;
	}
}