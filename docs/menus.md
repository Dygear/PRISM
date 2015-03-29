# Menus
The PRISM Menu System is based on an object oriented hierarchy from [SourceMod](http://wiki.alliedmods.net/Menu_API_\(SourceMod\)). Understanding this hierarchy, is critical to using menus effectively.

## Overview
Menus are a callback based system. Each callback represents an action that occurs during a menu display cycle. A cycle consists of a number of notifications:

* Start notification.
	* Display notification if the menu can be displayed to the client.
	* Either an item select or menu cancel notification.
* End notification.

Since End signifies the end of a full display cycle, it is usually used to destroy temporary menus.

## Specification
A detailed explanation of these events is below. For PRISM, a menuHandle is returned in the constructor and a MenuAction are always set in the menuHandler constructor as the callback.

* **Start** - the menu has been acknowledged. This does not mean it will be displayed; however, it guarantees that "onMenuEnd" will be called.
	* onMenuStart, this action is triggered intrinsicly upon startup.
* **Display** - the menu is being displayed to a client.
	* onMenuDisplay, An IMenuPanel pointer and client index are available.
		* param1: A client index.
* **Select** - an item on the menu has been selected. The item position given will be the position in the menu, rather than the ClickID of the pressed (unless the menu is a raw panel).
	* onMenuSelect, a client index and item position are passed.
		* param1: A client index.
		* param2: An item position.
* **Cancel** - the menu's display to one client has been cancelled.
	* onMenuCancel, A reason for cancellation is provided.
		* param1: A client index.
		* param2: A menu cancellation reason code.

* **End** - the menu's display cycle has finished; this means that the "Start" action has occurred, and either "Select" or "Cancel" has occurred thereafter. This is typically where menu resources are removed/deleted.
	* onMenuEnd.
		* param1: A menu end reason code.
		* param2: If param1 was MENU_END_CANCELLED, this contains a menu cancellation reason code.

## Panels
For panels, the callback rules change. Panels only receive two of the above callbacks, and it is guaranteed that only one of them will be called for a given display cycle.

* **Select** - a button has been pressed. This can be any number and should not be considered as reliably in bounds. For example, even if you only had 2 items in your panel, a client could trigger a key press of "43."
	* onMenuSelect, A client index and ClickID number pressed are passed.
		* param1: A client index.
		* param2: ClickID of the button pressed.
* **Cancel** - the menu's display to one client has been cancelled.
	* onMenuCancel, The menu's display to one client has been cancelled.
		* param1: A client index.
		* param2: A menu cancellation reason code.

## Examples
First, let's start off with a very basic menu. We want the menu to look like this:
`Do you like apples?
  1. Yes
  2. No`
We'll draw this menu with both a basic Menu and a Panel to show the API differences.

### Basic Menu
First, let's write our example using the Menu building API.
```php
<?php
class basicMenu extends Plugins, Menus {
	public function __construct() {
		$this->registerSayCommand('menuTest', 'menuTest');
	}
	public function menuHandler($menu, $action, $param1, $param2) {
		if ($action == MENU_ACTION_SELECT)
		{	# If an option was selected, tell the client about the item.
			$found = $menu->getMenuItem($param2, $info = NULL);
			$this->clientPrint($param1, PRINT_CHAT, "You selected item: {$param2} (found? {$found} info: {$info}"));
		}
		else if ($action == MENU_ACTION_CANCEL)
		{	# If the menu was cancelled, print a message to the console about it. */
			$this->clientPrint($param1, PRINT_CHAT, "Client menu was cancelled.  Reason: {$param2}"));
		}
		else if (action == MENU_ACTION_END)
		{	# Distorys our menu.
			$menu->close();
		}
	}
	public function menuTest($args, $CLID) {
		$menu = new handleMenu('menuHandler');
		$menu->setMenuTitle("Do you like apples?");
		$menu->addMenuItem("yes", "Yes");
		$menu->addMenuItem("no", "No");
		$menu->setMenuExitButton(FALSE);
		$menu->displayMenu($CLID, 20);

		return PLUGIN_HANDLED;
	}
}
?>
```
**Note a few very important points from this example**:

* One of either Select or Cancel will always be sent to the action handler.
* End will always be sent to the action handler.
* We destroy our Menu in the End action, because our Handle is no longer needed. If we had destroyed the Menu after DisplayMenu, it would have canceled the menu's display to the client.
* Menus, by default, have an exit button. We disabled this in our example.
* Our menu is set to display for 20 seconds. That means that if the client does not select an item within 20 seconds, the menu will be canceled. This is usually desired for menus that are for voting.
* Although we created and destroyed a new Menu Handle, we didn't need to. It is perfectly acceptable to create the Handle once for the lifetime of the plugin.

### Basic Panel
Now, let's rewrite our example to use Panels instead.
```php
<?php
	class basicPanel extends Plugins, Panels {
		public function __construct() {
			$this->registerSayCommand('panelTest', 'panelTest');
		}
		public function panelHandler($action, $param1, $param2) {
			if ($action == MENU_ACTION_SELECT) {
				$this->clientPrint($param1, PRINT_CHAT, "You selected item: {$param2}");
			} else if (action == MENU_ACTION_CANCEL) {
				$this->clientPrint($param1, PRINT_CHAT, "Menu was cancelled.  Reason: {$param2}");
			}
		}
		public function panelTest($args, $CLID) {
			$panel = new handlePanel();
			$panel->setTitle("Do you like apples?");
			$panel->drawItem("Yes");
			$panel->drawItem("No");
			$panel->sendToClient($CLID, 'panelHandler', 20);
			$panel->close();

			return PLUGIN_HANDLED;
		}
	}
?>
```
As you can see, Panels are significantly different.

* We can destroy the Panel as soon as we're done displaying it. We can create the Panel once and keep re-using it, but we can destroy it at any time without interrupting client menus.
* The Handler function gets much less data. Since panels are designed as a raw display, no "item" information is saved internally. Thus, the handler function only knows whether the display was canceled or whether (and what) numerical key was pressed.
* There is no automation. You cannot add more than a certain amount of selectable items to a Panel and get pagination. Automated control functionality requires using the heftier Menu object API.

### Basic Paginated Menu
Now, let's take a more advanced example -- pagination. Let's say we want to build a menu for changing the map. An easy way to do this is to read the tracklist.txt file at the start of a plugin and build a menu out of it.
Since reading and parsing a file is an expensive operation, we only want to do this once per track. Thus we'll build the menu in onTrackStart, and we won't call closeHandle until onTrackEnd.

Our example tracklist.txt
`AS4
AS5
AS7R
BL1
BL1R
BL2
BL2R
FE1
FE1R
FE4R
FE5
KY1
KY2R
KY3
SO1
SO3
SO4R
SO5`

Source code:
```php
<?php
	class changeMap extends Plugins, Menus {
		private $trackMenu;
		public function __construct() {
			$this->registerSayCommand('prism change track', 'cmdChangeTrack', 'Displays a menu to change the track.', ADMIN_TRACK);
			$this->makeTrackMenu();
		}
		public function cmdChangeTrack($args, $CLID) {
			if ($this->trackMenu == NULL)
				$this->clientPrint($param1, PRINT_CHAT, "The tracklist.txt file was not found!");
			else
				$this->trackMenu->display($CLID, MENU_TIME_FOREVER);
			return PLUGIN_HANDLED;
		}
		public function makeTrackMenu() {
			# Open the file
			if (($tracklist = file('tracklist.txt')) == FALSE)
				return ($this->trackMenu = NULL);

			# Create the menu Handle
			$this->trackMenu = new handleMenu('menuChangeTrack');
			foreach ($tracklist as $trackname)
			{
				# Skip Comments
				if ($trackname{0} == ';')
					continue;
				# Cut off the name at any whitespace
				if (($whitespace = strPos($trackname, ' ') != FALSE)
					$trackname = subStr($trackname, $whitespace);
				# Check if the map is valid
				if (!$this->isTrackValid($trackname))
					continue;
				# Add it to the menu
				$this->trackMenu->addMenuItem($trackname, $trackname);
			}
			# Make sure we close the file!
			unset($tracklist);

			# Finally, set the title
			$this->trackMenu->setTitle("Please select a track:");
		}
		public function menuChangeTrack($action, $param1, $param2) {
			if ($action == MENU_ACTION_SELECT) {
				# Get item info
				$found = $this->trackMenu->getMenuItem($param2, $info = NULL);
				# Tell the client
				$this->printToChat($param1, "You selected item: {$param2} (found? {$found} info: {$info})");
				# Change the map
				$this->serverCommand("/end");
				$this->serverCommand("/track {$info}");
			}
		}
	}
?>
```

This menu results in many selections (our tracklist.txt file had around 18 maps).

Finally, the console output printed this before the map changed to my selection, Kyoto GP:

`You selected item: 13 (found? 1 info: KY3)`
Displaying and designing this Menu with a raw ShowMenu message or Panel API would be very time consuming and difficult. We would have to keep track of all the items in an array of hardcoded size, pages which the user is viewing, and write a function which calculated item selection based on current page and key press. The Menu system, thankfully, handles all of this for you.
**Notes**:

* Control options which are not available are not drawn. For example, in the first page, you cannot go "back," and in the last page, you cannot go "next." Despite this, the menu API tries to keep each the interface as consistent as possible. Thus, visually, each navigational control is always in the same position.
* Although we specified no time out for our menu, if we had placed a timeout, flipping through pages does not affect the overall time. For example, if we had a timeout of 20, each successive page flip would continue to detract from the overall display time, rather than restart the allowed hold time back to 20.
* If we had disabled the Exit button, options 8 and 9 would still be "Back" and "Next," respectively.
* Again, we did not free the Menu Handle in MenuAction_End. This is because our menu is global/static, and we don't want to rebuild it every time.
* These images show "Back." In SourceMod revisions 1011 and higher, "Back" is changed to "Previous," and "Back" is reserved for the special "ExitBack" functionality.



## Voting
PRISM also has API for displaying menus as votable choices to more than one client. PRISM automatically handles selecting an item and randomly picking a tie-breaker. The voting API adds two new MENU_ACTION values, which for vote displays, are **always** passed:

* MENU_ACTION_VOTE_START: Fired after MENU_ACTION_START when the voting has officially started.
* MENU_ACTION_VOTE_END: Fired when all clients have either voted or cancelled their vote menu. The chosen item is passed through param1. This is fired before MENU_ACTION_END. It is important to note that it does not supercede MENU_ACTION_END, nor is it the same thing. Menus should never be destroyed in MENU_ACTION_VOTE_END. Note: This is not called if VoteMenu::setVoteResultCallback() is used.
* MENU_ACTION_VOTE_CANCEL: Fired if the menu is cancelled while the vote is in progress. If this is called, MENU_ACTION_VOTE_END or the result callback will not be called, but MENU_ACTION_END will be afterwards. A vote cancellation reason is passed in param1.

The voting system extends overall menus with two additional properties:

* Only one vote can be active at a time. You must call Vote::isInProgress() or else Vote::Menu() will fail.
* If a client votes and then disconnects while the vote is still active, the client's vote will be invalidated.

The example below shows has to create a function called Vote::doMenu() which will ask all clients whether or not they would like to change to the given track.
```php
<?php
	class simpleVote extends Plugin, Vote {
		public function __construct() {
			$this->registerSayCommand('prism vote track', 'cmdVoteMenu', '<trackcode> - Allows you to vote for a track to go to.');
		}
		public function handleVoteMenu($menu, $action, $param1, $param2) {
			if ($action == MENU_ACTION_END)
			{	# This is called after VoteEnd
				$menu->close();
			}
			else if ($action == MENU_ACTION_VOTE_END)
			{
				# 0=yes, 1=no
				if (param1 == 0)
				{
					$menu->getMenuItem($param1, $track);
					$this->serverCommand("/end");
					$this->serverCommand("/track {$track}");
				}
			}
		}
		public function cmdVoteMenu($args, $CLID) {
			# Make sure there is no vote in progress.
			if ($this->isVoteMenuInProgress())
				return PLUGIN_HANDLED;

			# Check Arg Count
			if (($argc = count($argv = str_getcsv($cmd, ' '))) < 3 || $argc > 3)
			{
				$this->printToChat($param1, "You must input only one track code. (For example BL1).");
				return PLUGIN_HANDLED;
			}

			# Make sure the track is valid.
			$track = array_pop($argv);
			if (!$this->isValidTrack($track))
				return PLUGIN_HANDLED;

			$menu = new voteHandle('handleVoteMenu');
			$menu->setTitle("Change map to: {$track}?");
			$menu->addMenuItem($track, "Yes");
			$menu->addMenuItem("no", "No");
			$menu->setExitButton(FALSE);
			$menu->voteMenuToAll(20);

			return PLUGIN_HANDLED;
		}
	}
?>
```
### Advanced Voting
If you need more information about voting results than MENU_ACTION_VOTE_END gives you, you can choose to have a different callback invoked. The new callback will provide much more information, but at a price: MENU_ACTION_VOTE_END will not be called, and you will have to decide how to interpret the results. This is done via Vote::setVoteResultCallback().

Example:
```php
<?php
	class advancedVoting extends Plugin, Vote {
		public function __constructor() {
			$this->registerSayCommand('prism vote menu', 'doVoteMenu', '<track> - Makes a vote for a track.');
		}
		public handleVoteMenu($menu, $action, $param1, $param2) {
			if ($action == MENU_ACTION_END)
			{
				/* This is called after VoteEnd */
				$menu->close();
			}
		}
		public handleVoteMenu($menu, $votes, $clients, $items) {
			/* See if there were multiple winners */
			$winner = 0;
			if (($count = count($items)) > 1)
				$winner = rand(0, $count);

			$track = $menu->getMenuItem($items[$winner]);
			$this->serverCommand("/end");
			$this->serverCommand("/changetrack {$track}");
		}
		public function doVoteMenu($track) {
			# Make sure there is no vote in progress.
			if ($this->isVoteMenuInProgress())
				return PLUGIN_HANDLED;

			# Check Arg Count
			if (($argc = count($argv = str_getcsv($cmd, ' '))) < 3 || $argc > 3)
			{
				$this->printToChat($param1, "You must input only one track code. (For example BL1).");
				return PLUGIN_HANDLED;
			}

			# Make sure the track is valid.
			$track = array_pop($argv);
			if (!$this->isValidTrack($track))
				return PLUGIN_HANDLED;

			$menu = new voteHandle('handleVoteMenu');
			$menu->voteResultCallback('handleVoteResults');
			$menu->setMenuTitle("Change track to: {$track}?");
			$menu->addMenuItem($track, 'Yes');
			$menu->addMenuItem('no', 'No');
			$menu->setMenuExitButton(FALSE);
			$menu->voteMenuToAll(20);
		}
	}
?>
```


## ExitBack
ExitBack is a special term to refer to the "ExitBack Button." This button is disabled by default. Normally, paginated menus have no "Previous" item for the first page. If the "ExitBack" button is enabled, the "Previous" item will show up as "Back."
Selecting the "ExitBack" option will exit the menu with MENU_CANCEL_EXIT_BACK and MENU_END_EXIT_BACK. The functionality of this is the same as a normal menu exit internally; extra functionality must be defined through the callbacks.

## Closing Menu Handles
It is only necessary to close a menu handle on MENU_ACTION_END. The MENU_ACTION_END is done every time a menu is closed and no longer needed.