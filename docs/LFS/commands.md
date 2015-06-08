# Live for Speed command line options and text commands

## Command line options
LFS can be started using a command line or another program.
A dedicated host mode is available with no hardware draw : "nogfx".
Before selecting host options, you may find it helpful to run LFS
and try out the options on the Start New Game screen - the required
upload bandwidth for those options is displayed on screen.

A command line or a command file is required for dedicated hosts.

NOTE - alternative way to use the command line options:

* A command file xxx.txt can be used instead of a long command line.
* The command file contains the command line parameters.
* The parameters can be spread onto several lines.
* The command file can contain comments, starting with two slashes //
* Then the command line would be simply :

LFS /cfg=xxx.txt (where xxx.txt is the name of the config file)

* /host=Host Name      :FIRST IN LIST
* /pass=Pass           :if required - password
* /admin=Pass          :if required - admin password
* /ip=X.X.X.X          :if required - local specified ip address
* /port=63392          :a high number below 65536
* /mode=demo           :demo / s1 / s2
* /usemaster=yes       :no / yes / hidden
* /track=XXCR          :track and config (e.g. BL1 / SO3R / FE4)
* /weather=1           :weather : 1,2,3 in Blackwood
* /cars=[cars]         :see below : "Car Strings"
* /maxguests=4         :max number of guests that can join host
* /adminslots=0        :slots reserved for admins (0 to 8)
* /carsmax=5           :max number of cars in a race
* /carshost=1          :max number of cars (real+ai) on host pc
* /carsguest=1         :max number of cars (real+ai) per guest pc
* /pps=4               :smoothness (3-6) number of car updates per second
* /qual=0              :qualifying minutes, 0 for no qualifying
* /laps=5              :number of lap, 0 for practice
* /wind=1              :0 no wind / 1 low wind / 2 high wind
* /dedicated=no        :no / yes / nogfx / invisible
* /vote=yes            :no / yes : can guests vote to kick or ban
* /select=yes          :no / yes : can guests select track
* /rstmin=X            :no restart for X seconds after race start
* /rstend=X            :no restart for X seconds after race finish
* /autokick=no         :no / kick / ban / spec (wrong way drivers)
* /midrace=yes         :no / yes               (join during race)
* /mustpit=no          :no / yes               (pit stop required)
* /canreset=no         :no / yes               (allow car reset)
* /fcv=no              :no / yes               (force cockpit view)
* /cruise=no           :no / yes               (allow wrong way)
* /start=finish        :fixed/finish/reverse/random (default race start)
* /insim=PORT          :listen for InSim (PORT is between 1 and 65535)
* /windowed=X          :no / yes - overrides the cfg.txt setting
* /welcome=X.txt       :set welcome text file
* /tracks=X.txt        :set list of allowed tracks
* /log=X.txt           :set message log file
* /ndebug=no           :no / yes               (network debug)
* /autosave=0          :MPR autosave (0-no / 1-manual / 2-auto)
* /mprdir=X            :set the data folder for mpr saving
* /lytdir=X            :set the data folder for layouts


## Host commands
Some text commands are intended for hosts and administrators.

Using the normal text message system (pressing T in a normal host or
simply typing into a nogfx host), the message becomes a command if you
start it with a slash character.

Simple commands with no parameter:

* /restart             :start a race
* /qualify             :start qualifying
* /end                 :return to game setup screen
* /names               :toggle display between player and user names
* /help                :get list of commands
* /reinit              :total restart (removes all connections)

Commands with a parameter - entry screen mode:

* /track XXCR          :track and config      (e.g. BL1 / SO3R / FE4)
* /weather X           :lighting              (e.g. 1, 2, 3...)
* /qual X              :qualifying minutes    (0 = no qualifying)
* /laps X              :number of laps        (0 = practice)
* /hours X             :number of hours       (if laps not specified)
* /wind X              :0 no / 1 low / 2 high

Commands with a parameter - any time:

* /maxguests X         :max number of guests that can join host
* /adminslots X        :slots reserved for admins (0 to 8)
* /carsmax X           :max number of cars in a race
* /carshost X          :max number of cars (real+ai) on host pc
* /carsguest X         :max number of cars (real+ai) per guest pc
* /pps X               :smoothness (3-6) number of car updates per second
* /msg X               :send system message
* /vote X              :no / yes                    (allow guest voting)
* /select X            :no / yes                    (guests select track)
* /rstmin X            :no restart for X seconds after race start
* /rstend X            :no restart for X seconds after race finish
* /autokick X          :no / kick / ban / spec      (wrong way drivers)
* /midrace X           :no / yes                    (join during race)
* /mustpit X           :no / yes                    (pit stop required)
* /canreset X          :no / yes                    (allow car reset)
* /fcv X               :no / yes                    (force cockpit view)
* /cruise X            :no / yes                    (allow wrong way)
* /start X             :fixed/finish/reverse/random (default race start)
* /pass X              :set new password            (BLANK = no password)
* /cars [cars]         :see below : "Car Strings"
* /welcome X.txt       :set welcome text file
* /tracks X.txt        :set list of allowed tracks
* /hlog X.txt          :set message log file on host
* /ndebug X            :no / yes (network debug)
* /autosave X          :MPR autosave (0-no / 1-manual / 2-auto)
* /save_mpr X          :save MPR with name X (autosave must be 1 or 2)

Autocross layout commands:

* /axlist X            :get list of layouts for track X - e.g. AU1
* /axload X            :load layout X on host
* /axsave X            :save layout X on host
* /axlaps X            :set autocross number of laps
* /axclear             :clear layout

Ban / Kick / Spectate commands - any time:

* /spec X              :make user X join the spectators
* /kick X              :disconnect user X
* /ban X Y             :ban user X for Y days (0 = 12 hours)
* /unban X             :remove ban on user X
* /pitlane X           :send user X to the pit lane
* /pit_all             :send all cars to the pit lane

Penalties:

* /p_dt USERNAME       :give drive through penalty
* /p_sg USERNAME       :give stop-go penalty
* /p_30 USERNAME       :give 30 second time penalty
* /p_45 USERNAME       :give 45 second time penalty
* /p_clear USERNAME    :clear a time or pit penalty

Race Control Messages: (big text in centre of screen)

* /rcm MESSAGE         :set a Race Control Message to be sent
* /rcm_ply USERNAME    :send the RCM to USERNAME
* /rcm_all             :send the RCM to all
* /rcc_ply USERNAME    :clear USERNAME's RCM
* /rcc_all             :clear all RCMs


The host commands are also available to any user who has connected to
the host using the admin password if one was specified when the host
was started.


## To display a welcome message on a host
Create a text file named "X.txt" in your LFS folder.
Type up to 200 characters into the text file.

Use the command /welcome=X.txt in your startup command line


## To restrict the tracks allowed on a host
Create a text file named "X.txt" in your LFS folder.
List all the tracks and configurations you want to allow.
Type one configuration on each line.
You must use the short name of the tracks:
\[first two letters of name\] \[config number\] \[reversed\]

Example:

* BL1
* BL1R
* BL2
* BL2R
* FE1
* FE1R

Use the command /tracks=X.txt in your startup command line


## Local commands
Most of these text commands replicate functions usually controlled by
pressing on-screen buttons but can be useful in other situations, for
example when controlling LFS from an external program using InSim.

Game setup screen only:

* /ready               :set ready
* /cancel              :cancel ready
* /clear               :clear all racers from list

Game setup screen or in game:

* /car XXX             :select car (e.g. XRT)
* /setup X             :select setup X
* /colour X            :select colour X
* /join                :join the race
* /ai [NAME]           :add ai driver (can specify NAME)
* /spec                :spectate or leave grid
* /leave               :disconnect from host
* /player X            :select existing player X

Mode / replay control:

* /spr X               :run a SP replay from entry (front end) screen
* /mpr X               :run a MP replay from entry (front end) screen
* /end                 :exit from replay back to entry screen
* /sp                  :go into single player from entry screen
* /mp IP PORT          :join a local mp game from entry screen

Any time:

* /exit                :exit LFS
* /entry               :return to entry screen
* /speedreduce X       :total speed steer reduction (0 to 1)
* /reducehalf X        :speed in m/s for half of reduction
* /loadkb X            :load kb settings file (data\misc\X.kbs)
* /savekb X            :save kb settings file (data\misc\X.kbs)
* /out X               :only seen by external programs
* /log X.txt           :set message log file

Useful commands for scripts and controller buttons:

* /run X               :run the script X     (data\script\X.lfs)
* /hrun X              :run script X on host (admins only)
* /exec E C            :run program E        (with command line C)
* /wait E C            :like exec but LFS hangs until E exits

* /fov    [degrees]           - field of view
* /ff     [0-200]             - force feedback strength
* /axis   [axis]   [function] - e.g. /axis 2 throttle   (see below)
* /invert [0/1]    [function] - e.g. /invert 1 brake    (see below)
* /button [button] [function] - e.g. /button 5 shift_up (see below)
* /key    [key]    [function] - e.g. /key Q handbrake   (see below)
* /head_tilt       [degrees]  - 1g head tilt
* /lateral_shift   [m]        - 1g lateral shift
* /forward_shift   [m]        - 1g forward shift
* /vertical_shift  [m]        - 1g vertical shift
* /hidetext   [yes/no]        - hide or show text (SHIFT + F)
* /showmouse  [yes/no]        - show or hide mouse (SHIFT + Z)
* /say        [message]       - same as typing a chat message
* /echo       [text]          - show text only on local screen
* /ctrlf      [num] [text]    - change text e.g. "ctrlf 1 hello"
* /altf       [num] [text]    - change text e.g. "altf 1 bye"
* /wheel_turn [degrees]       - specify turn angle of controller
* /press      [key]           - simulate key press (see PARAMETERS)
* /shift      [key]           - SHIFT + key
* /ctrl       [key]           - CTRL + key
* /alt        [key]           - ALT + key
* /autoclutch [0-1]           - turn autoclutch off / on

* /shifter    [auto/sequential/shifter]    - shift type
* /view       [fol/heli/cam/driver/custom] - select view

* /view save                  - save any changes made to a custom view
* /view reload                - reload custom view (without saving)

IN A SCRIPT: //comment - this line is ignored
IN CHAT BOX: //xxx - short for /run xxx


### To get info from LFS World - /w and /ws commands
/w CMD sends command to LFS World for current car/track
e.g.  /w pb  (Personal Best)  or  /w laps  (Laps)

/ws TRACK CAR CMD sends command for specified car/track
e.g.  /ws BL1R XRT pb  (get PB in XR GT TURBO at Blackwood GP REV)

More online DB access commands can be found on the
"LFS Keys" page at www.liveforspeed.net

### To get info from master server - /m command
/m find USER : find a user online
/m ?         : get a list of master commands


## Car Strings
The /cars command for the startup command line or for hosts / admins
now uses the three-letter S2 car codes.

Example:

* /cars=XFG+XRG        :Allow XF GTI and XR GT
* /cars=MRT            :Allow MRT5 only

These Car Groups can be used as well:

* ALL    - all cars
* ROAD   - road legal cars
* RACE   - race cars
* TBO    - same as XRT+RB4+FXO
* LRF    - same as LX6+RAC+FZ5
* GTR    - same as FXR+XRR+FZR

The plus and minus symbols can be used in conjuction with these :

* /cars=TBO+LX4        :Allow XRT, RB4, FXO and LX4
* /cars=ROAD-UF1       :Allow all road cars except the UF 1000


## FUNCTION NAMES for the /button AND /key COMMANDS
steer_left, steer_right, steer_fast, steer_slow
throttle, brake, shift_up, shift_down, clutch, handbrake
left_view, right_view, rear_view, horn, flash, reset
pit_speed, tc_disable, ignition, zoom_in, zoom_out
reverse, gear_1 - gear_7, ctrl_f1 - ctrl_f12


## FUNCTION NAMES for the /axis AND /invert COMMANDS
steer, combined, throttle, brake
lookh, lookp, lookr
clutch, handbrake, shiftx, shifty


## UNASSIGNING a button or axis
To unassign a button or axis from a function,
you can assign -1 to that function.

Example 1 : /button -1 shift_up <- unassign the shift up button
Example 2 : /axis -1 clutch     <- unassign the clutch axis


## PARAMETERS for the key commands (press / shift / ctrl / alt)
Letters A to Z
Numbers 0 to 9
F1 to F12
up, down, left, right
space, enter, esc, tab
less, more