## This is a user based Button manager, this adds support for
  * dynamic GUIs that can be moved around on screen (not dragable)
  * alternating text
  * buttons that expire after certain amount of time

## To use:
* on NCN:
  * You will need to have a variable in your plugin class that stores ButtonHandlers
    * create new button Manger:
      * $this->BTNMan[$NCN->UName] = new betterButtonManager($UCID);
   * create a timer like so: 
    $this->createNamedTimer("PluginName_ButtonHandle_{$NCN->UName}", 'DoButtonHandle', 0.50, Timer::REPEAT, array($NCN->UName));
    * The function for this timer is:
        public function DoButtonHandle($UName)
        {
          $this->BTNMan[$UName]->HandleButtons();
        }
* To create a new button:
  * $this->BTNMan[$UName]->InitButton(ButtonName, Group, Top, Left, Width, Height, ButtonStyle, Text, SecondsToShow);
    * example: $this->BTNMan[$UName]->InitButton('Welcome', 'welcomescreen', 50, 50, 100, 100, ISB_DARK, 'Welcome to Our Server!', 10);
      This will create a button that is:
      * 50 down from the top
      * 50 from the left
      * 100 wide
      * 100 tall
      * Dark Background
      * Welcomes the user,
      * Stays on screen for 10 seconds
* To load a cluster:
  * $this->BTNMan[$UName]->LoadGUICluster('example_cluster', 'example_layout', 100, 100);
    * this will load the ini file /plugins/gui/example_cluster/example_layout.ini
* To remove a single button:
  * $this->BTNMan[$UName]->RemoveButton('Welcome');
* To remove a button group/cluster
  * $this->BTNMan[$UName]->RemoveGroup('welcomescreen'); #this will remove the button we used
  * $this->BTNMan[$UName]->RemoveGroup('example_cluster'); #this will remove the cluster we used
* To move a cluster
  * you have 3 options
    * move just vertically
      * $this->BTNMan[$UName]->MoveGUIClusterV('example_cluster', 5)
        * Will move the cluster down 5
    * move just horizontally
      * $this->BTNMan[$UName]->MoveGUIClusterH('example_cluster', 10)
        * Will move the cluster right 10
    * or move both vertical and horizontal
      * $this->BTNMan[$UName]->MoveGUIClusterD('example_cluster', 5, 10)
        * Will move the cluster down 5, and right 10
* SIDE NOTE: each cluster will only show 1 layout at a time
  * For example if you do
    * $this->BTNMan[$UName]->LoadGUICluster('example_cluster', 'example_layout', 100, 100);
  * then do
    * $this->BTNMan[$UName]->LoadGUICluster('example_cluster', 'example_layout2', 100, 100);
  * the call to load the 2nd layout will remove all buttons from the example_cluster that is shown
    and then show the new layout
