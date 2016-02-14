```c++
#ifndef _ISPACKETS_H_
#define _ISPACKETS_H_
/////////////////////

// InSim for Live for Speed

// InSim allows communication between up to 8 external programs and LFS.

// TCP or UDP packets can be sent in both directions, LFS reporting various
// things about its state, and the external program requesting info and
// controlling LFS with special packets, text commands or keypresses.

// NOTE : This text file was written with a TAB size equal to 4 spaces.


// INSIM VERSION NUMBER (updated for version 0.6M)
// ====================

const int INSIM_VERSION = 7;


// CHANGES
// =======

// Version 0.6M (INSIM_VERSION increased to 7)
// ------------
// Backward compatibility system - send INSIM_VERSION in the IS_ISI
// Older programs (that send zero) are assumed to require version 6
// New join request system enabled if ISF_REQ_JOIN is set in IS_ISI
// IS_JRR can also be used to reset a car at a specified location
// Packet IS_CSC to report changes in car state (currently start or stop)
// Zbyte added to CarContObject structure to report car's altitude
// Zbyte added to IS_OBH so the layout object can be identified
// IS_MSO / IS_III / IS_ACR message out packets now have variable size
// IS_BFN can now be used to delete a range of buttons with a single packet
// New packet IS_OCO can be used to override specific or all start lights
// New IS_AXM option PMO_SELECTION to set the current editor selection
// Added TTC_SEL to request an IS_AXM with layout editor selection
// Added TINY_AXM to request IS_AXM packets for the entire layout
// IS_SSH documentation updated as it is no longer only for bmp files
// New packet IS_UCO sends info about InSim checkpoints and circles
// New packet IS_SLC reports a connection's currently selected car
// Packet TINY_SLC to request an IS_SLC for all connections
// Added TINY_ALC and SMALL_ALC to get and set allowed cars (like /cars)
// Value 5 (out of bounds) added to the IS_HLV packet

// Version 0.6H (INSIM_VERSION increased to 6)
// ------------
// IS_REO and IS_NLP increased in size to allow 40 drivers
// ObjectInfo Zchar is now Zbyte - see layout file description
// ISP_NCI packet added to give host more info about new guest

// Version 0.6C
// ------------
// Small change to the in-game usage of IS_REO - only valid after SMALL_VTA
// Some more values (CPW / OOS / JOOS / HACK) added to the leave reasons

// Version 0.6B (INSIM_VERSION increased to 5)
// ------------
// Lap timing info added to IS_RST (Timing byte)
// NLP / MCI minimum time interval reduced to 40 ms (was 50 ms)
// IS_VTC now cancels game votes even if the majority has not been reached
// IS_MTC (Msg To Connection) now has a variable length (up to 128 characters)
// IS_MTC can be sent to all (UCID = 255) and sound effect can be specified
// IS_CON reports contact between two cars           (if ISF_CON is set)
// IS_OBH reports information about any object hit   (if ISF_OBH is set)
// IS_HLV reports incidents that would violate HLVC  (if ISF_HLV is set)
// IS_PLC sets allowed cars for individual players
// IS_AXM to add / remove / clear autocross objects
// IS_ACR reports successful or attempted admin commands
// OG_SHIFT and OG_CTRL (keys) bits added to OutGaugePack
// New IS_RIP option RIPOPT_FULL_PHYS to use full physics when searching
// ISS_SHIFTU_HIGH is no longer used (no high / low view distinction)
// FIX : Clutch axis / button was not reported from Controls screen
// FIX : TTime in IS_RIP was wrong in mid-joined Multiplayer Replays
// FIX : IS_BTN did not allow the documented limit of 240 characters
// FIX : OutGaugePack ID was always zero regardless of ID in cfg.txt
// FIX : InSim camera with vertical pitch would cause LFS to crash

// Version 0.5Z (no change to INSIM_VERSION)
// ------------
// NLP / MCI packets are now output at regular intervals
// CCI_LAG bit added to the CompCar structure


// TYPES : (all multi-byte types are PC style - lowest byte first)
// =====

// char         1-byte character
// byte         1-byte unsigned integer
// word         2-byte unsigned integer
// short        2-byte signed integer
// unsigned     4-byte unsigned integer
// int          4-byte signed integer
// float        4-byte float

// RaceLaps (rl) : (various meanings depending on range)

// 0       : practice
// 1-99    : number of laps...   laps  = rl
// 100-190 : 100 to 1000 laps... laps  = (rl - 100) * 10 + 100
// 191-238 : 1 to 48 hours...    hours = rl - 190


// InSim PACKETS
// =============

// All InSim packets use a four byte header

// Size : total packet size - a multiple of 4
// Type : packet identifier from the ISP_ enum (see below)
// ReqI : non zero if the packet is a packet request or a reply to a request
// Data : the first data byte

// Spare bytes and Zero bytes must be filled with ZERO


// INITIALISING InSim
// ==================

// To initialise the InSim system, type into LFS : /insim xxxxx
// where xxxxx is the TCP and UDP port you want LFS to open.

// OR start LFS with the command line option : LFS /insim=xxxxx
// This will make LFS listen for packets on that TCP and UDP port.


// TO START COMMUNICATION
// ======================

// TCP : Connect to LFS using a TCP connection, then send this packet :
// UDP : No connection required, just send this packet to LFS :

struct IS_ISI // InSim Init - packet to initialise the InSim system
{
    byte    Size;       // 44
    byte    Type;       // ISP_ISI
    byte    ReqI;       // If non-zero LFS will send an IS_VER packet
    byte    Zero;       // 0

    word    UDPPort;    // Port for UDP replies from LFS (0 to 65535)
    word    Flags;      // Bit flags for options (see below)

    byte    InSimVer;   // The INSIM_VERSION used by your program
    byte    Prefix;     // Special host message prefix character
    word    Interval;   // Time in ms between NLP or MCI (0 = none)

    char    Admin[16];  // Admin password (if set in LFS)
    char    IName[16];  // A short name for your program
};

// NOTE 1) UDPPort field when you connect using UDP :

// zero     : LFS sends all packets to the port of the incoming packet
// non-zero : LFS sends all packets to the specified UDPPort

// NOTE 2) UDPPort field when you connect using TCP :

// zero     : LFS sends NLP / MCI packets using your TCP connection
// non-zero : LFS sends NLP / MCI packets to the specified UDPPort

// NOTE 3) Flags field (set the relevant bits to turn on the option) :

#define ISF_RES_0          1    // bit  0 : spare
#define ISF_RES_1          2    // bit  1 : spare
#define ISF_LOCAL          4    // bit  2 : guest or single player
#define ISF_MSO_COLS       8    // bit  3 : keep colours in MSO text
#define ISF_NLP           16    // bit  4 : receive NLP packets
#define ISF_MCI           32    // bit  5 : receive MCI packets
#define ISF_CON           64    // bit  6 : receive CON packets
#define ISF_OBH          128    // bit  7 : receive OBH packets
#define ISF_HLV          256    // bit  8 : receive HLV packets
#define ISF_AXM_LOAD     512    // bit  9 : receive AXM when loading a layout
#define ISF_AXM_EDIT    1024    // bit 10 : receive AXM when changing objects
#define ISF_REQ_JOIN    2048    // bit 11 : process join requests

// In most cases you should not set both ISF_NLP and ISF_MCI flags
// because all IS_NLP information is included in the IS_MCI packet.

// The ISF_LOCAL flag is important if your program creates buttons.
// It should be set if your program is not a host control system.
// If set, then buttons are created in the local button area, so
// avoiding conflict with the host buttons and allowing the user
// to switch them with SHIFT+B rather than SHIFT+I.

// NOTE 4) InSimVer field :

// Provide the INSIM_VERSION that your program was designed for.
// Later LFS versions will try to retain backward compatibility
// if it can be provided, within reason.  Not guaranteed.

// NOTE 5) Prefix field, if set when initialising InSim on a host :

// Messages typed with this prefix will be sent to your InSim program
// on the host (in IS_MSO) and not displayed on anyone's screen.


// ENUMERATIONS FOR PACKET TYPES
// =============================

enum // the second byte of any packet is one of these
{
    ISP_NONE,       //  0                   : not used
    ISP_ISI,        //  1 - instruction     : insim initialise
    ISP_VER,        //  2 - info            : version info
    ISP_TINY,       //  3 - both ways       : multi purpose
    ISP_SMALL,      //  4 - both ways       : multi purpose
    ISP_STA,        //  5 - info            : state info
    ISP_SCH,        //  6 - instruction     : single character
    ISP_SFP,        //  7 - instruction     : state flags pack
    ISP_SCC,        //  8 - instruction     : set car camera
    ISP_CPP,        //  9 - both ways       : cam pos pack
    ISP_ISM,        // 10 - info            : start multiplayer
    ISP_MSO,        // 11 - info            : message out
    ISP_III,        // 12 - info            : hidden /i message
    ISP_MST,        // 13 - instruction     : type message or /command
    ISP_MTC,        // 14 - instruction     : message to a connection
    ISP_MOD,        // 15 - instruction     : set screen mode
    ISP_VTN,        // 16 - info            : vote notification
    ISP_RST,        // 17 - info            : race start
    ISP_NCN,        // 18 - info            : new connection
    ISP_CNL,        // 19 - info            : connection left
    ISP_CPR,        // 20 - info            : connection renamed
    ISP_NPL,        // 21 - info            : new player (joined race)
    ISP_PLP,        // 22 - info            : player pit (keeps slot in race)
    ISP_PLL,        // 23 - info            : player leave (spectate - loses slot)
    ISP_LAP,        // 24 - info            : lap time
    ISP_SPX,        // 25 - info            : split x time
    ISP_PIT,        // 26 - info            : pit stop start
    ISP_PSF,        // 27 - info            : pit stop finish
    ISP_PLA,        // 28 - info            : pit lane enter / leave
    ISP_CCH,        // 29 - info            : camera changed
    ISP_PEN,        // 30 - info            : penalty given or cleared
    ISP_TOC,        // 31 - info            : take over car
    ISP_FLG,        // 32 - info            : flag (yellow or blue)
    ISP_PFL,        // 33 - info            : player flags (help flags)
    ISP_FIN,        // 34 - info            : finished race
    ISP_RES,        // 35 - info            : result confirmed
    ISP_REO,        // 36 - both ways       : reorder (info or instruction)
    ISP_NLP,        // 37 - info            : node and lap packet
    ISP_MCI,        // 38 - info            : multi car info
    ISP_MSX,        // 39 - instruction     : type message
    ISP_MSL,        // 40 - instruction     : message to local computer
    ISP_CRS,        // 41 - info            : car reset
    ISP_BFN,        // 42 - both ways       : delete buttons / receive button requests
    ISP_AXI,        // 43 - info            : autocross layout information
    ISP_AXO,        // 44 - info            : hit an autocross object
    ISP_BTN,        // 45 - instruction     : show a button on local or remote screen
    ISP_BTC,        // 46 - info            : sent when a user clicks a button
    ISP_BTT,        // 47 - info            : sent after typing into a button
    ISP_RIP,        // 48 - both ways       : replay information packet
    ISP_SSH,        // 49 - both ways       : screenshot
    ISP_CON,        // 50 - info            : contact between cars (collision report)
    ISP_OBH,        // 51 - info            : contact car + object (collision report)
    ISP_HLV,        // 52 - info            : report incidents that would violate HLVC
    ISP_PLC,        // 53 - instruction     : player cars
    ISP_AXM,        // 54 - both ways       : autocross multiple objects
    ISP_ACR,        // 55 - info            : admin command report
    ISP_HCP,        // 56 - instruction     : car handicaps
    ISP_NCI,        // 57 - info            : new connection - extra info for host
    ISP_JRR,        // 58 - instruction     : reply to a join request (allow / disallow)
    ISP_UCO,        // 59 - info            : report InSim checkpoint / InSim circle
    ISP_OCO,        // 60 - instruction     : object control (currently used for lights)
    ISP_TTC,        // 61 - instruction     : multi purpose - target to connection
    ISP_SLC,        // 62 - info            : connection selected a car
    ISP_CSC,        // 63 - info            : car state changed
};

enum // the fourth byte of an IS_TINY packet is one of these
{
    TINY_NONE,      //  0 - keep alive      : see "maintaining the connection"
    TINY_VER,       //  1 - info request    : get version
    TINY_CLOSE,     //  2 - instruction     : close insim
    TINY_PING,      //  3 - ping request    : external progam requesting a reply
    TINY_REPLY,     //  4 - ping reply      : reply to a ping request
    TINY_VTC,       //  5 - both ways       : game vote cancel (info or request)
    TINY_SCP,       //  6 - info request    : send camera pos
    TINY_SST,       //  7 - info request    : send state info
    TINY_GTH,       //  8 - info request    : get time in hundredths (i.e. SMALL_RTP)
    TINY_MPE,       //  9 - info            : multi player end
    TINY_ISM,       // 10 - info request    : get multiplayer info (i.e. ISP_ISM)
    TINY_REN,       // 11 - info            : race end (return to race setup screen)
    TINY_CLR,       // 12 - info            : all players cleared from race
    TINY_NCN,       // 13 - info request    : get NCN for all connections
    TINY_NPL,       // 14 - info request    : get all players
    TINY_RES,       // 15 - info request    : get all results
    TINY_NLP,       // 16 - info request    : send an IS_NLP
    TINY_MCI,       // 17 - info request    : send an IS_MCI
    TINY_REO,       // 18 - info request    : send an IS_REO
    TINY_RST,       // 19 - info request    : send an IS_RST
    TINY_AXI,       // 20 - info request    : send an IS_AXI - AutoX Info
    TINY_AXC,       // 21 - info            : autocross cleared
    TINY_RIP,       // 22 - info request    : send an IS_RIP - Replay Information Packet
    TINY_NCI,       // 23 - info request    : get NCI for all guests (on host only)
    TINY_ALC,       // 24 - info request    : send a SMALL_ALC (allowed cars)
    TINY_AXM,       // 25 - info request    : send IS_AXM packets for the entire layout
    TINY_SLC,       // 26 - info request    : send IS_SLC packets for all connections
};

enum // the fourth byte of an IS_SMALL packet is one of these
{
    SMALL_NONE,     //  0                   : not used
    SMALL_SSP,      //  1 - instruction     : start sending positions
    SMALL_SSG,      //  2 - instruction     : start sending gauges
    SMALL_VTA,      //  3 - report          : vote action
    SMALL_TMS,      //  4 - instruction     : time stop
    SMALL_STP,      //  5 - instruction     : time step
    SMALL_RTP,      //  6 - info            : race time packet (reply to GTH)
    SMALL_NLI,      //  7 - instruction     : set node lap interval
    SMALL_ALC,      //  8 - both ways       : set or get allowed cars (TINY_ALC)
};

enum // the fourth byte of an IS_TTC packet is one of these
{
    TTC_NONE,       //  0                   : not used
    TTC_SEL,        //  1 - info request    : send IS_AXM for a layout editor selection
};


// GENERAL PURPOSE PACKETS - IS_TINY (4 bytes) / IS_SMALL (8 bytes) / IS_TTC (8 bytes)
// =======================

// To avoid defining several packet structures that are exactly the same, and to avoid
// wasting the ISP_ enumeration, IS_TINY is used at various times when no additional data
// other than SubT is required.  IS_SMALL is used when an additional integer is needed.

// IS_TINY

struct IS_TINY // General purpose 4 byte packet
{
    byte    Size;       // 4
    byte    Type;       // ISP_TINY
    byte    ReqI;       // 0 unless it is an info request or a reply to an info request
    byte    SubT;       // subtype, from TINY_ enumeration (e.g. TINY_RACE_END)
};

// IS_SMALL

struct IS_SMALL // General purpose 8 byte packet
{
    byte    Size;       // 8
    byte    Type;       // ISP_SMALL
    byte    ReqI;       // 0 unless it is an info request or a reply to an info request
    byte    SubT;       // subtype, from SMALL_ enumeration (e.g. SMALL_SSP)

    unsigned UVal;  // value (e.g. for SMALL_SSP this would be the OutSim packet rate)
};

// IS_TTC

struct IS_TTC // General purpose 8 byte packet (Target To Connection)
{
    byte    Size;       // 8
    byte    Type;       // ISP_TTC
    byte    ReqI;       // 0 unless it is an info request or a reply to an info request
    byte    SubT;       // subtype, from TTC_ enumeration (e.g. TTC_SEL)

    byte    UCID;       // connection's unique id (0 = local)
    byte    B1;         // B1, B2, B3 may be used in various ways depending on SubT
    byte    B2;
    byte    B3;
};


// VERSION REQUEST
// ===============

// It is advisable to request version information as soon as you have connected, to
// avoid problems when connecting to a host with a later or earlier version.  You will
// be sent a version packet on connection if you set ReqI in the IS_ISI packet.

// This version packet is sent on request :

struct IS_VER // VERsion
{
    byte    Size;           // 20
    byte    Type;           // ISP_VERSION
    byte    ReqI;           // ReqI as received in the request packet
    byte    Zero;

    char    Version[8];     // LFS version, e.g. 0.3G
    char    Product[6];     // Product : DEMO / S1 / S2 / S3
    byte    InSimVer;       // InSim version (see below)
    byte    Spare;          // Spare
};

// To request an IS_VER packet at any time, send this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_VER      (request an IS_VER)

// NOTE : LFS tries to match InSimVer with the version requested in your program's IS_ISI
// packet if it is lower than the latest version known to LFS.  If backward compatibility
// is no longer possible then this version may be higher than your program requested.
// In that case your program may not be able to read some packets sent to it by LFS.
// If you connect to an older LFS version then InSimVer may be lower than requested.

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_PING     (request a TINY_REPLY)


// CLOSING InSim
// =============

// You can send this IS_TINY to close the InSim connection to your program :

// ReqI : 0
// SubT : TINY_CLOSE    (close this connection)

// Another InSimInit packet is then required to start operating again.

// You can shut down InSim completely and stop it listening at all by typing /insim=0
// into LFS (or send a MsgTypePack to do the same thing).


// MAINTAINING THE CONNECTION - IMPORTANT
// ==========================

// If InSim does not receive a packet for 70 seconds, it will close your connection.
// To open it again you would need to send another InSimInit packet.

// LFS will send a blank IS_TINY packet like this every 30 seconds :

// ReqI : 0
// SubT : TINY_NONE     (keep alive packet)

// You should reply with a blank IS_TINY packet :

// ReqI : 0
// SubT : TINY_NONE     (has no effect other than resetting the timeout)

// NOTE : If you want to request a reply from LFS to check the connection
// at any time, you can send this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_PING     (request a TINY_REPLY)

// LFS will reply with this IS_TINY :

// ReqI : non-zero      (as received in the request packet)
// SubT : TINY_REPLY    (reply to ping)


// STATE REPORTING AND REQUESTS
// ============================

// LFS will send an IS_STA any time the info in it changes.

struct IS_STA // STAte
{
    byte    Size;           // 28
    byte    Type;           // ISP_STA
    byte    ReqI;           // ReqI if replying to a request packet
    byte    Zero;

    float   ReplaySpeed;    // 4-byte float - 1.0 is normal speed

    word    Flags;          // ISS state flags (see below)
    byte    InGameCam;      // Which type of camera is selected (see below)
    byte    ViewPLID;       // Unique ID of viewed player (0 = none)

    byte    NumP;           // Number of players in race
    byte    NumConns;       // Number of connections including host
    byte    NumFinished;    // Number finished or qualified
    byte    RaceInProg;     // 0 - no race / 1 - race / 2 - qualifying

    byte    QualMins;
    byte    RaceLaps;       // see "RaceLaps" near the top of this document
    byte    Spare2;
    byte    Spare3;

    char    Track[6];       // short name for track e.g. FE2R
    byte    Weather;        // 0,1,2...
    byte    Wind;           // 0=off 1=weak 2=strong
};

// InGameCam is the in game selected camera mode (which is
// still selected even if LFS is actually in SHIFT+U mode).
// For InGameCam's values, see "View identifiers" below.

// ISS state flags

#define ISS_GAME            1       // in game (or MPR)
#define ISS_REPLAY          2       // in SPR
#define ISS_PAUSED          4       // paused
#define ISS_SHIFTU          8       // SHIFT+U mode
#define ISS_16              16      // UNUSED
#define ISS_SHIFTU_FOLLOW   32      // FOLLOW view
#define ISS_SHIFTU_NO_OPT   64      // SHIFT+U buttons hidden
#define ISS_SHOW_2D         128     // showing 2d display
#define ISS_FRONT_END       256     // entry screen
#define ISS_MULTI           512     // multiplayer mode
#define ISS_MPSPEEDUP       1024    // multiplayer speedup option
#define ISS_WINDOWED        2048    // LFS is running in a window
#define ISS_SOUND_MUTE      4096    // sound is switched off
#define ISS_VIEW_OVERRIDE   8192    // override user view
#define ISS_VISIBLE         16384   // InSim buttons visible

// To request an IS_STA at any time, send this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_SST      (Send STate)

// Setting states

// These states can be set by a special packet :

// ISS_SHIFTU_NO_OPT    - SHIFT+U buttons hidden
// ISS_SHOW_2D          - showing 2d display
// ISS_MPSPEEDUP        - multiplayer speedup option
// ISS_SOUND_MUTE       - sound is switched off

struct IS_SFP // State Flags Pack
{
    byte    Size;       // 8
    byte    Type;       // ISP_SFP
    byte    ReqI;       // 0
    byte    Zero;

    word    Flag;       // the state to set
    byte    OffOn;      // 0 = off / 1 = on
    byte    Sp3;        // spare
};

// Other states must be set by using keypresses or messages (see below)


// SCREEN MODE
// ===========

// You can send this packet to LFS to set the screen mode :

struct IS_MOD // MODe : send to LFS to change screen mode
{
    byte    Size;       // 20
    byte    Type;       // ISP_MOD
    byte    ReqI;       // 0
    byte    Zero;

    int     Bits16;     // set to choose 16-bit
    int     RR;         // refresh rate - zero for default
    int     Width;      // 0 means go to window
    int     Height;     // 0 means go to window
};

// The refresh rate actually selected by LFS will be the highest available rate
// that is less than or equal to the specified refresh rate.  Refresh rate can
// be specified as zero in which case the default refresh rate will be used.

// If Width and Height are both zero, LFS will switch to windowed mode.


// TEXT MESSAGES AND KEY PRESSES
// ==============================

// You can send 64-byte text messages to LFS as if the user had typed them in.
// Messages that appear on LFS screen (up to 128 bytes) are reported to the
// external program.  You can also send simulated keypresses to LFS.

// MESSAGES OUT (FROM LFS)
// ------------

struct IS_MSO // MSg Out - system messages and user messages - variable size
{
    byte    Size;       // 12, 16, 20... 136 depending on Msg
    byte    Type;       // ISP_MSO
    byte    ReqI;       // 0
    byte    Zero;

    byte    UCID;       // connection's unique id (0 = host)
    byte    PLID;       // player's unique id (if zero, use UCID)
    byte    UserType;   // set if typed by a user (see User Values below) 
    byte    TextStart;  // first character of the actual text (after player name)

    char    Msg[128];   // 4, 8, 12... 128 characters - last byte is zero
};

// User Values (for UserType byte)

enum
{
    MSO_SYSTEM,         // 0 - system message
    MSO_USER,           // 1 - normal visible user message
    MSO_PREFIX,         // 2 - hidden message starting with special prefix (see ISI)
    MSO_O,              // 3 - hidden message typed on local pc with /o command
    MSO_NUM
};

// NOTE : Typing "/o MESSAGE" into LFS will send an IS_MSO with UserType = MSO_O

struct IS_III // InsIm Info - /i message from user to host's InSim - variable size
{
    byte    Size;       // 12, 16, 20... 72 depending on Msg
    byte    Type;       // ISP_III
    byte    ReqI;       // 0
    byte    Zero;

    byte    UCID;       // connection's unique id (0 = host)
    byte    PLID;       // player's unique id (if zero, use UCID)
    byte    Sp2;
    byte    Sp3;

    char    Msg[64];    // 4, 8, 12... 64 characters - last byte is zero
};

struct IS_ACR // Admin Command Report - a user typed an admin command - variable size
{
    byte    Size;       // 12, 16, 20... 72 depending on Text
    byte    Type;       // ISP_ACR
    byte    ReqI;       // 0
    byte    Zero;

    byte    UCID;       // connection's unique id (0 = host)
    byte    Admin;      // set if user is an admin
    byte    Result;     // 1 - processed / 2 - rejected / 3 - unknown command
    byte    Sp3;

    char    Text[64];   // 4, 8, 12... 64 characters - last byte is zero
};

// MESSAGES IN (TO LFS)
// -----------

struct IS_MST // MSg Type - send to LFS to type message or command
{
    byte    Size;       // 68
    byte    Type;       // ISP_MST
    byte    ReqI;       // 0
    byte    Zero;

    char    Msg[64];    // last byte must be zero
};

struct IS_MSX // MSg eXtended - like MST but longer (not for commands)
{
    byte    Size;       // 100
    byte    Type;       // ISP_MSX
    byte    ReqI;       // 0
    byte    Zero;

    char    Msg[96];    // last byte must be zero
};

struct IS_MSL // MSg Local - message to appear on local computer only
{
    byte    Size;       // 132
    byte    Type;       // ISP_MSL
    byte    ReqI;       // 0
    byte    Sound;      // sound effect (see Message Sounds below)

    char    Msg[128];   // last byte must be zero
};

struct IS_MTC // Msg To Connection - hosts only - send to a connection / a player / all
{
    byte    Size;       // 8 + TEXT_SIZE (TEXT_SIZE = 4, 8, 12... 128)
    byte    Type;       // ISP_MTC
    byte    ReqI;       // 0
    byte    Sound;      // sound effect (see Message Sounds below)

    byte    UCID;       // connection's unique id (0 = host / 255 = all)
    byte    PLID;       // player's unique id (if zero, use UCID)
    byte    Sp2;
    byte    Sp3;

//  char    Text[TEXT_SIZE]; // up to 128 characters of text - last byte must be zero
};

// Message Sounds (for Sound byte)

enum
{
    SND_SILENT,
    SND_MESSAGE,
    SND_SYSMESSAGE,
    SND_INVALIDKEY,
    SND_ERROR,
    SND_NUM
};

// You can send individual key presses to LFS with the IS_SCH packet.
// For standard keys (e.g. V and H) you should send a capital letter.
// This does not work with some keys like F keys, arrows or CTRL keys.
// You can also use IS_MST with the /press /shift /ctrl /alt commands.

struct IS_SCH // Single CHaracter
{
    byte    Size;       // 8
    byte    Type;       // ISP_SCH
    byte    ReqI;       // 0
    byte    Zero;

    byte    CharB;      // key to press
    byte    Flags;      // bit 0 : SHIFT / bit 1 : CTRL
    byte    Spare2;
    byte    Spare3;
};


// MULTIPLAYER NOTIFICATION
// ========================

// LFS will send this packet when a host is started or joined :

struct IS_ISM // InSim Multi
{
    byte    Size;       // 40
    byte    Type;       // ISP_ISM
    byte    ReqI;       // usually 0 / or if a reply : ReqI as received in the TINY_ISM
    byte    Zero;

    byte    Host;       // 0 = guest / 1 = host
    byte    Sp1;
    byte    Sp2;
    byte    Sp3;

    char    HName[32];  // the name of the host joined or started
};

// On ending or leaving a host, LFS will send this IS_TINY :

// ReqI : 0
// SubT : TINY_MPE      (MultiPlayerEnd)

// To request an IS_ISM packet at any time, send this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_ISM      (request an IS_ISM)

// NOTE : If LFS is not in multiplayer mode, the host name in the ISM will be empty.


// VOTE NOTIFY AND CANCEL
// ======================

// LFS notifies the external program of any votes to restart or qualify

// The Vote Actions are defined as :

enum
{
    VOTE_NONE,          // 0 - no vote
    VOTE_END,           // 1 - end race
    VOTE_RESTART,       // 2 - restart
    VOTE_QUALIFY,       // 3 - qualify
    VOTE_NUM
};

struct IS_VTN // VoTe Notify
{
    byte    Size;       // 8
    byte    Type;       // ISP_VTN
    byte    ReqI;       // 0
    byte    Zero;

    byte    UCID;       // connection's unique id
    byte    Action;     // VOTE_X (Vote Action as defined above)
    byte    Spare2;
    byte    Spare3;
};

// When a vote is cancelled, LFS sends this IS_TINY

// ReqI : 0
// SubT : TINY_VTC      (VoTe Cancelled)

// When a vote is completed, LFS sends this IS_SMALL

// ReqI : 0
// SubT : SMALL_VTA     (VoTe Action)
// UVal : action        (VOTE_X - Vote Action as defined above)

// You can instruct LFS host to cancel a vote using an IS_TINY

// ReqI : 0
// SubT : TINY_VTC      (VoTe Cancel)


// ALLOWED CARS
// ============

// To set the allowed cars on the host (like /cars command) you can send this IS_SMALL :

// ReqI : 0
// SubT : SMALL_ALC     (ALlowed Cars)
// UVal : Cars          (see below)

// To find out the allowed cars at any time (on guest or host) send this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_ALC      (request a SMALL_ALC)

// LFS will reply with this IS_SMALL :

// ReqI : non-zero      (as received in the request packet)
// SubT : SMALL_ALC     (ALlowed Cars)
// UVal : Cars          (see below)

// You can send a packet to limit the cars that can be used by a given connection
// The resulting set of selectable cars is a subset of the cars set to be available
// on the host (by the /cars command or SMALL_ALC)

// For example :
// Cars = 0          ... no cars can be selected on the specified connection
// Cars = 0xffffffff ... all the host's available cars can be selected

struct IS_PLC // PLayer Cars
{
    byte    Size;       // 12
    byte    Type;       // ISP_PLC
    byte    ReqI;       // 0
    byte    Zero;

    byte    UCID;       // connection's unique id (0 = host / 255 = all)
    byte    Sp1;
    byte    Sp2;
    byte    Sp3;

    unsigned    Cars;   // allowed cars - see below
};

// XF GTI           -       1
// XR GT            -       2
// XR GT TURBO      -       4
// RB4 GT           -       8
// FXO TURBO        -    0x10
// LX4              -    0x20
// LX6              -    0x40
// MRT5             -    0x80
// UF 1000          -   0x100
// RACEABOUT        -   0x200
// FZ50             -   0x400
// FORMULA XR       -   0x800
// XF GTR           -  0x1000
// UF GTR           -  0x2000
// FORMULA V8       -  0x4000
// FXO GTR          -  0x8000
// XR GTR           - 0x10000
// FZ50 GTR         - 0x20000
// BMW SAUBER F1.06 - 0x40000
// FORMULA BMW FB02 - 0x80000


// HANDICAPS
// =========

// You can send a packet to add mass and restrict the intake on each car model
// The same restriction applies to all drivers using a particular car model
// This can be useful for creating multi class hosts

struct CarHCP // Car handicaps in 2 bytes - there is an array of these in the HCP (below)
{
    byte    H_Mass;     // 0 to 200 - added mass (kg)
    byte    H_TRes;     // 0 to  50 - intake restriction
};

struct IS_HCP // HandiCaPs
{
    byte    Size;       // 68
    byte    Type;       // ISP_HCP
    byte    ReqI;       // 0
    byte    Zero;

    CarHCP  Info[32];   // H_Mass and H_TRes for each car : XF GTI = 0 / XR GT = 1 etc
};


// RACE TRACKING
// =============

// In LFS there is a list of connections AND a list of players in the race
// Some packets are related to connections, some players, some both

// If you are making a multiplayer InSim program, you must maintain two lists
// You should use the unique identifier UCID to identify a connection

// Each player has a unique identifier PLID from the moment he joins the race, until he
// leaves.  It's not possible for PLID and UCID to be the same thing, for two reasons :

// 1) there may be more than one player per connection if AI drivers are used
// 2) a player can swap between connections, in the case of a driver swap (IS_TOC)

// When all players are cleared from race (e.g. /clear) LFS sends this IS_TINY

// ReqI : 0
// SubT : TINY_CLR      (CLear Race)

// When a race ends (return to race setup screen) LFS sends this IS_TINY

// ReqI : 0
// SubT : TINY_REN      (Race ENd)

// You can instruct LFS host to cancel a vote using an IS_TINY

// ReqI : 0
// SubT : TINY_VTC      (VoTe Cancel)

// The following packets are sent when the relevant events take place :

struct IS_RST // Race STart
{
    byte    Size;       // 28
    byte    Type;       // ISP_RST
    byte    ReqI;       // 0 unless this is a reply to an TINY_RST request
    byte    Zero;

    byte    RaceLaps;   // 0 if qualifying
    byte    QualMins;   // 0 if race
    byte    NumP;       // number of players in race
    byte    Timing;     // lap timing (see below)

    char    Track[6];   // short track name
    byte    Weather;
    byte    Wind;

    word    Flags;      // race flags (must pit, can reset, etc - see below)
    word    NumNodes;   // total number of nodes in the path
    word    Finish;     // node index - finish line
    word    Split1;     // node index - split 1
    word    Split2;     // node index - split 2
    word    Split3;     // node index - split 3
};

// Lap timing info (for Timing byte)

// bits 6 and 7 (Timing & 0xc0) :

// 0x40 : standard lap timing is being used
// 0x80 : custom timing - user checkpoints have been placed
// 0xc0 : no lap timing - e.g. open config with no user checkpoints

// bits 0 and 1 (Timing & 0x03) : number of checkpoints if lap timing is enabled

// To request an IS_RST packet at any time, send this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_RST      (request an IS_RST)

struct IS_NCN // New ConN
{
    byte    Size;       // 56
    byte    Type;       // ISP_NCN
    byte    ReqI;       // 0 unless this is a reply to a TINY_NCN request
    byte    UCID;       // new connection's unique id (0 = host)

    char    UName[24];  // username
    char    PName[24];  // nickname

    byte    Admin;      // 1 if admin
    byte    Total;      // number of connections including host
    byte    Flags;      // bit 2 : remote
    byte    Sp3;
};

struct IS_NCI // New Conn Info - sent on host only if an admin password has been set
{
    byte    Size;       // 16
    byte    Type;       // ISP_NCI
    byte    ReqI;       // 0 unless this is a reply to a TINY_NCI request
    byte    UCID;       // connection's unique id (0 = host)

    byte    Language;   // see below : Languages
    byte    Sp1;
    byte    Sp2;
    byte    Sp3;

    unsigned    UserID;     // LFS UserID
    unsigned    IPAddress;
};

struct IS_SLC // SeLected Car - sent when a connection selects a car (empty if no car)
{
    byte    Size;       // 8
    byte    Type;       // ISP_SLC
    byte    ReqI;       // 0 unless this is a reply to a TINY_SLC request
    byte    UCID;       // connection's unique id (0 = host)

    char    CName[4];   // car name
};

// NOTE : If a new guest joins and does have a car selected then an IS_SLC will be sent

struct IS_CNL // ConN Leave
{
    byte    Size;       // 8
    byte    Type;       // ISP_CNL
    byte    ReqI;       // 0
    byte    UCID;       // unique id of the connection which left

    byte    Reason;     // leave reason (see below)
    byte    Total;      // number of connections including host
    byte    Sp2;
    byte    Sp3;
};

struct IS_CPR // Conn Player Rename
{
    byte    Size;       // 36
    byte    Type;       // ISP_CPR
    byte    ReqI;       // 0
    byte    UCID;       // unique id of the connection

    char    PName[24];  // new name
    char    Plate[8];   // number plate - NO ZERO AT END!
};

struct IS_NPL // New PLayer joining race (if PLID already exists, then leaving pits)
{
    byte    Size;       // 76
    byte    Type;       // ISP_NPL
    byte    ReqI;       // 0 unless this is a reply to an TINY_NPL request
    byte    PLID;       // player's newly assigned unique id

    byte    UCID;       // connection's unique id
    byte    PType;      // bit 0 : female / bit 1 : AI / bit 2 : remote
    word    Flags;      // player flags

    char    PName[24];  // nickname
    char    Plate[8];   // number plate - NO ZERO AT END!

    char    CName[4];   // car name
    char    SName[16];  // skin name - MAX_CAR_TEX_NAME
    byte    Tyres[4];   // compounds

    byte    H_Mass;     // added mass (kg)
    byte    H_TRes;     // intake restriction
    byte    Model;      // driver model
    byte    Pass;       // passengers byte

    int     Spare;

    byte    SetF;       // setup flags (see below)
    byte    NumP;       // number in race - ZERO if this is a join request
    byte    Sp2;
    byte    Sp3;
};

// NOTE : PType bit 0 (female) is not reported on dedicated host as humans are not loaded
// You can use the driver model byte instead if required (and to force the use of helmets)

// Setup flags (for SetF byte)

#define SETF_SYMM_WHEELS    1
#define SETF_TC_ENABLE      2
#define SETF_ABS_ENABLE     4

// More...

struct IS_PLP // PLayer Pits (go to settings - stays in player list)
{
    byte    Size;       // 4
    byte    Type;       // ISP_PLP
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id
};

struct IS_PLL // PLayer Leave race (spectate - removed from player list)
{
    byte    Size;       // 4
    byte    Type;       // ISP_PLL
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id
};

struct IS_CRS // Car ReSet
{
    byte    Size;       // 4
    byte    Type;       // ISP_CRS
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id
};

struct IS_LAP // LAP time
{
    byte    Size;       // 20
    byte    Type;       // ISP_LAP
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    unsigned    LTime;  // lap time (ms)
    unsigned    ETime;  // total time (ms)

    word    LapsDone;   // laps completed
    word    Flags;      // player flags

    byte    Sp0;
    byte    Penalty;    // current penalty value (see below)
    byte    NumStops;   // number of pit stops
    byte    Sp3;
};

struct IS_SPX // SPlit X time
{
    byte    Size;       // 16
    byte    Type;       // ISP_SPX
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    unsigned    STime;  // split time (ms)
    unsigned    ETime;  // total time (ms)

    byte    Split;      // split number 1, 2, 3
    byte    Penalty;    // current penalty value (see below)
    byte    NumStops;   // number of pit stops
    byte    Sp3;
};

struct IS_PIT // PIT stop (stop at pit garage)
{
    byte    Size;       // 24
    byte    Type;       // ISP_PIT
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    word    LapsDone;   // laps completed
    word    Flags;      // player flags

    byte    Sp0;
    byte    Penalty;    // current penalty value (see below)
    byte    NumStops;   // number of pit stops
    byte    Sp3;

    byte    Tyres[4];   // tyres changed

    unsigned    Work;   // pit work
    unsigned    Spare;
};

struct IS_PSF // Pit Stop Finished
{
    byte    Size;       // 12
    byte    Type;       // ISP_PSF
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    unsigned    STime;  // stop time (ms)
    unsigned    Spare;
};

struct IS_PLA // Pit LAne
{
    byte    Size;       // 8
    byte    Type;       // ISP_PLA
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    byte    Fact;       // pit lane fact (see below)
    byte    Sp1;
    byte    Sp2;
    byte    Sp3;
};

// IS_CCH : Camera CHange

// To track cameras you need to consider 3 points

// 1) The default camera : VIEW_DRIVER
// 2) Player flags : CUSTOM_VIEW means VIEW_CUSTOM at start or pit exit
// 3) IS_CCH : sent when an existing driver changes camera

struct IS_CCH // Camera CHange
{
    byte    Size;       // 8
    byte    Type;       // ISP_CCH
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    byte    Camera;     // view identifier (see below)
    byte    Sp1;
    byte    Sp2;
    byte    Sp3;
};

struct IS_PEN // PENalty (given or cleared)
{
    byte    Size;       // 8
    byte    Type;       // ISP_PEN
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    byte    OldPen;     // old penalty value (see below)
    byte    NewPen;     // new penalty value (see below)
    byte    Reason;     // penalty reason (see below)
    byte    Sp3;
};

struct IS_TOC // Take Over Car
{
    byte    Size;       // 8
    byte    Type;       // ISP_TOC
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    byte    OldUCID;    // old connection's unique id
    byte    NewUCID;    // new connection's unique id
    byte    Sp2;
    byte    Sp3;
};

struct IS_FLG // FLaG (yellow or blue flag changed)
{
    byte    Size;       // 8
    byte    Type;       // ISP_FLG
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    byte    OffOn;      // 0 = off / 1 = on
    byte    Flag;       // 1 = given blue / 2 = causing yellow
    byte    CarBehind;  // unique id of obstructed player
    byte    Sp3;
};

struct IS_PFL // Player FLags (help flags changed)
{
    byte    Size;       // 8
    byte    Type;       // ISP_PFL
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    word    Flags;      // player flags (see below)
    word    Spare;
};

struct IS_FIN // FINished race notification (not a final result - use IS_RES)
{
    byte    Size;       // 20
    byte    Type;       // ISP_FIN
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id (0 = player left before result was sent)

    unsigned    TTime;  // race time (ms)
    unsigned    BTime;  // best lap (ms)

    byte    SpA;
    byte    NumStops;   // number of pit stops
    byte    Confirm;    // confirmation flags : disqualified etc - see below
    byte    SpB;

    word    LapsDone;   // laps completed
    word    Flags;      // player flags : help settings etc - see below
};

struct IS_RES // RESult (qualify or confirmed finish)
{
    byte    Size;       // 84
    byte    Type;       // ISP_RES
    byte    ReqI;       // 0 unless this is a reply to a TINY_RES request
    byte    PLID;       // player's unique id (0 = player left before result was sent)

    char    UName[24];  // username
    char    PName[24];  // nickname
    char    Plate[8];   // number plate - NO ZERO AT END!
    char    CName[4];   // skin prefix

    unsigned    TTime;  // race time (ms)
    unsigned    BTime;  // best lap (ms)

    byte    SpA;
    byte    NumStops;   // number of pit stops
    byte    Confirm;    // confirmation flags : disqualified etc - see below
    byte    SpB;

    word    LapsDone;   // laps completed
    word    Flags;      // player flags : help settings etc - see below

    byte    ResultNum;  // finish or qualify pos (0 = win / 255 = not added to table)
    byte    NumRes;     // total number of results (qualify doesn't always add a new one)
    word    PSeconds;   // penalty time in seconds (already included in race time)
};

// IS_REO : REOrder - this packet can be sent in either direction

// LFS sends one at the start of every race or qualifying session, listing the start order

// You can send one to LFS in two different ways, to specify the starting order :
// 1) In the race setup screen, to immediately rearrange the grid when the packet arrives
// 2) In game, just before a restart or exit, to specify the order on the restart or exit
// If you are sending an IS_REO in game, you should send it when you receive the SMALL_VTA
// informing you that the Vote Action (VOTE_END / VOTE_RESTART / VOTE_QUALIFY) is about
// to take place.  Any IS_REO received before the SMALL_VTA is sent will be ignored.

struct IS_REO // REOrder (when race restarts after qualifying)
{
    byte    Size;       // 44
    byte    Type;       // ISP_REO
    byte    ReqI;       // 0 unless this is a reply to an TINY_REO request
    byte    NumP;       // number of players in race

    byte    PLID[40];   // all PLIDs in new order
};

// To request an IS_REO packet at any time, send this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_REO      (request an IS_REO)

// Pit Lane Facts

enum
{
    PITLANE_EXIT,       // 0 - left pit lane
    PITLANE_ENTER,      // 1 - entered pit lane
    PITLANE_NO_PURPOSE, // 2 - entered for no purpose
    PITLANE_DT,         // 3 - entered for drive-through
    PITLANE_SG,         // 4 - entered for stop-go
    PITLANE_NUM
};

// Pit Work Flags

enum
{
    PSE_NOTHING,        // bit 0 (1)
    PSE_STOP,           // bit 1 (2)
    PSE_FR_DAM,         // bit 2 (4)
    PSE_FR_WHL,         // etc...
    PSE_LE_FR_DAM,
    PSE_LE_FR_WHL,
    PSE_RI_FR_DAM,
    PSE_RI_FR_WHL,
    PSE_RE_DAM,
    PSE_RE_WHL,
    PSE_LE_RE_DAM,
    PSE_LE_RE_WHL,
    PSE_RI_RE_DAM,
    PSE_RI_RE_WHL,
    PSE_BODY_MINOR,
    PSE_BODY_MAJOR,
    PSE_SETUP,
    PSE_REFUEL,
    PSE_NUM
};

// View identifiers

enum
{
    VIEW_FOLLOW,    // 0 - arcade
    VIEW_HELI,      // 1 - helicopter
    VIEW_CAM,       // 2 - tv camera
    VIEW_DRIVER,    // 3 - cockpit
    VIEW_CUSTOM,    // 4 - custom
    VIEW_MAX
};

const int VIEW_ANOTHER = 255; // viewing another car

// Languages

enum
{
    LFS_ENGLISH,                // 0
    LFS_DEUTSCH,                // 1
    LFS_PORTUGUESE,             // 2
    LFS_FRENCH,                 // 3
    LFS_SUOMI,                  // 4
    LFS_NORSK,                  // 5
    LFS_NEDERLANDS,             // 6
    LFS_CATALAN,                // 7
    LFS_TURKISH,                // 8
    LFS_CASTELLANO,             // 9
    LFS_ITALIANO,               // 10
    LFS_DANSK,                  // 11
    LFS_CZECH,                  // 12
    LFS_RUSSIAN,                // 13
    LFS_ESTONIAN,               // 14
    LFS_SERBIAN,                // 15
    LFS_GREEK,                  // 16
    LFS_POLSKI,                 // 17
    LFS_CROATIAN,               // 18
    LFS_HUNGARIAN,              // 19
    LFS_BRAZILIAN,              // 20
    LFS_SWEDISH,                // 21
    LFS_SLOVAK,                 // 22
    LFS_GALEGO,                 // 23
    LFS_SLOVENSKI,              // 24
    LFS_BELARUSSIAN,            // 25
    LFS_LATVIAN,                // 26
    LFS_LITHUANIAN,             // 27
    LFS_TRADITIONAL_CHINESE,    // 28
    LFS_SIMPLIFIED_CHINESE,     // 29
    LFS_JAPANESE,               // 30
    LFS_KOREAN,                 // 31
    LFS_BULGARIAN,              // 32
    LFS_LATINO,                 // 33
    LFS_UKRAINIAN,              // 34
    LFS_INDONESIAN,             // 35
    LFS_ROMANIAN,               // 36
    LFS_NUM_LANG                // 37
};

// Leave reasons

enum
{
    LEAVR_DISCO,        // 0 - none
    LEAVR_TIMEOUT,      // 1 - timed out
    LEAVR_LOSTCONN,     // 2 - lost connection
    LEAVR_KICKED,       // 3 - kicked
    LEAVR_BANNED,       // 4 - banned
    LEAVR_SECURITY,     // 5 - security
    LEAVR_CPW,          // 6 - cheat protection wrong
    LEAVR_OOS,          // 7 - out of sync with host
    LEAVR_JOOS,         // 8 - join OOS (initial sync failed)
    LEAVR_HACK,         // 9 - invalid packet
    LEAVR_NUM
};

// Penalty values (VALID means the penalty can now be cleared)

enum
{
    PENALTY_NONE,       // 0        
    PENALTY_DT,         // 1
    PENALTY_DT_VALID,   // 2
    PENALTY_SG,         // 3
    PENALTY_SG_VALID,   // 4
    PENALTY_30,         // 5
    PENALTY_45,         // 6
    PENALTY_NUM
};

// Penalty reasons

enum
{
    PENR_UNKNOWN,       // 0 - unknown or cleared penalty
    PENR_ADMIN,         // 1 - penalty given by admin
    PENR_WRONG_WAY,     // 2 - wrong way driving
    PENR_FALSE_START,   // 3 - starting before green light
    PENR_SPEEDING,      // 4 - speeding in pit lane
    PENR_STOP_SHORT,    // 5 - stop-go pit stop too short
    PENR_STOP_LATE,     // 6 - compulsory stop is too late
    PENR_NUM
};

// Player flags

#define PIF_SWAPSIDE        1
#define PIF_RESERVED_2      2
#define PIF_RESERVED_4      4
#define PIF_AUTOGEARS       8
#define PIF_SHIFTER         16
#define PIF_RESERVED_32     32
#define PIF_HELP_B          64
#define PIF_AXIS_CLUTCH     128
#define PIF_INPITS          256
#define PIF_AUTOCLUTCH      512
#define PIF_MOUSE           1024
#define PIF_KB_NO_HELP      2048
#define PIF_KB_STABILISED   4096
#define PIF_CUSTOM_VIEW     8192

// Tyre compounds (4 byte order : rear L, rear R, front L, front R)

enum
{
    TYRE_R1,            // 0
    TYRE_R2,            // 1
    TYRE_R3,            // 2
    TYRE_R4,            // 3
    TYRE_ROAD_SUPER,    // 4
    TYRE_ROAD_NORMAL,   // 5
    TYRE_HYBRID,        // 6
    TYRE_KNOBBLY,       // 7
    TYRE_NUM
};

const int NOT_CHANGED = 255;

// Confirmation flags

#define CONF_MENTIONED      1
#define CONF_CONFIRMED      2
#define CONF_PENALTY_DT     4
#define CONF_PENALTY_SG     8
#define CONF_PENALTY_30     16
#define CONF_PENALTY_45     32
#define CONF_DID_NOT_PIT    64

#define CONF_DISQ   (CONF_PENALTY_DT | CONF_PENALTY_SG | CONF_DID_NOT_PIT)
#define CONF_TIME   (CONF_PENALTY_30 | CONF_PENALTY_45)

// Race flags

// HOSTF_CAN_VOTE       1
// HOSTF_CAN_SELECT     2
// HOSTF_MID_RACE       32
// HOSTF_MUST_PIT       64
// HOSTF_CAN_RESET      128
// HOSTF_FCV            256
// HOSTF_CRUISE         512

// Passengers byte

// bit 0 female
// bit 1 front
// bit 2 female
// bit 3 rear left
// bit 4 female
// bit 5 rear middle
// bit 6 female
// bit 7 rear right


// TRACKING PACKET REQUESTS
// ========================

// To request players, connections, results or a single NLP or MCI, send an IS_TINY

// In each case, ReqI must be non-zero, and will be returned in the reply packet

// SubT : TINT_NCN - request all connections
// SubT : TINY_NPL - request all players
// SubT : TINY_RES - request all results
// SubT : TINY_NLP - request a single IS_NLP
// SubT : TINY_MCI - request a set of IS_MCI


// OBJECT INFO - for autocross objects - used in some packets and the layout file
// ===========

struct ObjectInfo // Info about a single object - explained in the layout file format
{
    short   X;
    short   Y;

    byte    Zbyte;
    byte    Flags;
    byte    Index;
    byte    Heading;
};


// JOIN REQUEST - allows external program to decide if a player can join
// ============

// Set the ISF_REQ_JOIN flag in the IS_ISI to receive join requests
// A join request is seen as an IS_NPL packet with ZERO in the NumP field
// An immediate response (e.g. within 1 second) is required using an IS_JRR packet

// In this case, PLID must be zero and JRRAction must be JRR_REJECT or JRR_SPAWN
// If you allow the join and it is successful you will then get a normal IS_NPL with NumP set
// You can also specify the start position of the car using the StartPos structure

// IS_JRR can also be used to move an existing car to a different location
// In this case, PLID must be set, JRRAction must be JRR_RESET or higher and StartPos must be set

struct IS_JRR // Join Request Reply - send one of these back to LFS in response to a join request
{
    byte    Size;       // 16
    byte    Type;       // ISP_JRR
    byte    ReqI;       // 0
    byte    PLID;       // ZERO when this is a reply to a join request - SET to move a car

    byte    UCID;       // set when this is a reply to a join request - ignored when moving a car
    byte    JRRAction;  // 1 - allow / 0 - reject (should send message to user)
    byte    Sp2;
    byte    Sp3;

    ObjectInfo  StartPos; // 0 : use default start point / Flags = 0x80 : set start point
};

// To use default start point, StartPos should be filled with zero values

// To specify a start point, StartPos X, Y, Zbyte and Heading should be filled like an autocross
// start position, Flags should be 0x80 and Index should be zero

// Values for JRRAction byte

enum
{
    JRR_REJECT,
    JRR_SPAWN,
    JRR_2,
    JRR_3,
    JRR_RESET,
    JRR_RESET_NO_REPAIR,
    JRR_6,
    JRR_7,
};


// AUTOCROSS
// =========

// When all objects are cleared from a layout, LFS sends this IS_TINY :

// ReqI : 0
// SubT : TINY_AXC      (AutoX Cleared)

// You can request information about the current layout with this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_AXI      (AutoX Info)

// The information will be sent back in this packet (also sent when a layout is loaded) :

struct IS_AXI // AutoX Info
{
    byte    Size;       // 40
    byte    Type;       // ISP_AXI
    byte    ReqI;       // 0 unless this is a reply to an TINY_AXI request
    byte    Zero;

    byte    AXStart;    // autocross start position
    byte    NumCP;      // number of checkpoints
    word    NumO;       // number of objects

    char    LName[32];  // the name of the layout last loaded (if loaded locally)
};

// On false start or wrong route / restricted area, an IS_PEN packet is sent :

// False start : OldPen = 0 / NewPen = PENALTY_30 / Reason = PENR_FALSE_START
// Wrong route : OldPen = 0 / NewPen = PENALTY_45 / Reason = PENR_WRONG_WAY

// If an autocross object is hit (2 second time penalty) this packet is sent :

struct IS_AXO // AutoX Object
{
    byte    Size;       // 4
    byte    Type;       // ISP_AXO
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id
};


// CAR TRACKING - car position info sent at constant intervals
// ============

// IS_NLP - compact, all cars in 1 variable sized packet
// IS_MCI - detailed, max 8 cars per variable sized packet

// To receive IS_NLP or IS_MCI packets at a specified interval :

// 1) Set the Interval field in the IS_ISI (InSimInit) packet (40, 50, 60... 8000 ms)
// 2) Set one of the flags ISF_NLP or ISF_MCI in the IS_ISI packet

// If ISF_NLP flag is set, one IS_NLP packet is sent...

struct NodeLap // Car info in 6 bytes - there is an array of these in the NLP (below)
{
    word    Node;       // current path node
    word    Lap;        // current lap
    byte    PLID;       // player's unique id
    byte    Position;   // current race position : 0 = unknown, 1 = leader, etc...
};

struct IS_NLP // Node and Lap Packet - variable size
{
    byte    Size;       // 4 + NumP * 6 (PLUS 2 if needed to make it a multiple of 4)
    byte    Type;       // ISP_NLP
    byte    ReqI;       // 0 unless this is a reply to an TINY_NLP request
    byte    NumP;       // number of players in race

    NodeLap Info[40];   // node and lap of each player, 1 to 40 of these (NumP)
};

// If ISF_MCI flag is set, a set of IS_MCI packets is sent...

struct CompCar // Car info in 28 bytes - there is an array of these in the MCI (below)
{
    word    Node;       // current path node
    word    Lap;        // current lap
    byte    PLID;       // player's unique id
    byte    Position;   // current race position : 0 = unknown, 1 = leader, etc...
    byte    Info;       // flags and other info - see below
    byte    Sp3;
    int     X;          // X map (65536 = 1 metre)
    int     Y;          // Y map (65536 = 1 metre)
    int     Z;          // Z alt (65536 = 1 metre)
    word    Speed;      // speed (32768 = 100 m/s)
    word    Direction;  // car's motion if Speed > 0 : 0 = world y direction, 32768 = 180 deg
    word    Heading;    // direction of forward axis : 0 = world y direction, 32768 = 180 deg
    short   AngVel;     // signed, rate of change of heading : (16384 = 360 deg/s)
};

// NOTE 1) Info byte - the bits in this byte have the following meanings :

#define CCI_BLUE        1       // this car is in the way of a driver who is a lap ahead
#define CCI_YELLOW      2       // this car is slow or stopped and in a dangerous place

#define CCI_LAG         32      // this car is lagging (missing or delayed position packets)

#define CCI_FIRST       64      // this is the first compcar in this set of MCI packets
#define CCI_LAST        128     // this is the last compcar in this set of MCI packets

// NOTE 2) Heading : 0 = world y axis direction, 32768 = 180 degrees, anticlockwise from above
// NOTE 3) AngVel  : 0 = no change in heading,    8192 = 180 degrees per second anticlockwise

struct IS_MCI // Multi Car Info - if more than 8 in race then more than one of these is sent
{
    byte    Size;       // 4 + NumC * 28
    byte    Type;       // ISP_MCI
    byte    ReqI;       // 0 unless this is a reply to an TINY_MCI request
    byte    NumC;       // number of valid CompCar structs in this packet

    CompCar Info[8];    // car info for each player, 1 to 8 of these (NumC)
};

// You can change the rate of NLP or MCI after initialisation by sending this IS_SMALL :

// ReqI : 0
// SubT : SMALL_NLI     (Node Lap Interval)
// UVal : interval      (0 means stop, otherwise time interval : 40, 50, 60... 8000 ms)


// CONTACT - reports contacts between two cars if the closing speed is above 0.25 m/s
// =======

// Set the ISF_CON flag in the IS_ISI to receive car contact reports

struct CarContact // 16 bytes : one car in a contact - two of these in the IS_CON (below)
{
    byte    PLID;
    byte    Info;       // like Info byte in CompCar (CCI_BLUE / CCI_YELLOW / CCI_LAG)
    byte    Sp2;        // spare
    char    Steer;      // front wheel steer in degrees (right positive)
    
    byte    ThrBrk;     // high 4 bits : throttle    / low 4 bits : brake (0 to 15)
    byte    CluHan;     // high 4 bits : clutch      / low 4 bits : handbrake (0 to 15)
    byte    GearSp;     // high 4 bits : gear (15=R) / low 4 bits : spare
    byte    Speed;      // m/s

    byte    Direction;  // car's motion if Speed > 0 : 0 = world y direction, 128 = 180 deg
    byte    Heading;    // direction of forward axis : 0 = world y direction, 128 = 180 deg
    char    AccelF;     // m/s^2 longitudinal acceleration (forward positive)
    char    AccelR;     // m/s^2 lateral acceleration (right positive)

    short   X;          // position (1 metre = 16)
    short   Y;          // position (1 metre = 16)
};

struct IS_CON // CONtact - between two cars (A and B are sorted by PLID)
{
    byte    Size;       // 40
    byte    Type;       // ISP_CON
    byte    ReqI;       // 0
    byte    Zero;

    word    SpClose;    // high 4 bits : reserved / low 12 bits : closing speed (10 = 1 m/s)
    word    Time;       // looping time stamp (hundredths - time since reset - like TINY_GTH)

    CarContact  A;
    CarContact  B;
};

// Set the ISF_OBH flag in the IS_ISI to receive object contact reports

struct CarContOBJ // 8 bytes : car in a contact with an object
{
    byte    Direction;  // car's motion if Speed > 0 : 0 = world y direction, 128 = 180 deg
    byte    Heading;    // direction of forward axis : 0 = world y direction, 128 = 180 deg
    byte    Speed;      // m/s
    byte    Zbyte;

    short   X;          // position (1 metre = 16)
    short   Y;          // position (1 metre = 16)
};

struct IS_OBH // OBject Hit - car hit an autocross object or an unknown object
{
    byte    Size;       // 24
    byte    Type;       // ISP_OBH
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    word    SpClose;    // high 4 bits : reserved / low 12 bits : closing speed (10 = 1 m/s)
    word    Time;       // looping time stamp (hundredths - time since reset - like TINY_GTH)

    CarContOBJ  C;

    short   X;          // as in ObjectInfo
    short   Y;          // as in ObjectInfo

    byte    Zbyte;      // if OBH_LAYOUT is set : Zbyte as in ObjectInfo
    byte    Sp1;
    byte    Index;      // AXO_x as in ObjectInfo or zero if it is an unknown object
    byte    OBHFlags;   // see below
};

// OBHFlags byte

#define OBH_LAYOUT      1       // an added object
#define OBH_CAN_MOVE    2       // a movable object
#define OBH_WAS_MOVING  4       // was moving before this hit
#define OBH_ON_SPOT     8       // object in original position

// Set the ISF_HLV flag in the IS_ISI to receive reports of incidents that would violate HLVC

struct IS_HLV // Hot Lap Validity - off track / hit wall / speeding in pits / out of bounds
{
    byte    Size;       // 16
    byte    Type;       // ISP_HLV
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    byte    HLVC;       // 0 : ground / 1 : wall / 4 : speeding / 5 : out of bounds
    byte    Sp1;
    word    Time;       // looping time stamp (hundredths - time since reset - like TINY_GTH)

    CarContOBJ  C;
};


// CONTROL - reports crossing an InSim checkpoint / entering an InSim circle (from layout)
// =======

struct IS_UCO // User Control Object
{
    byte    Size;       // 28
    byte    Type;       // ISP_UCO
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    byte    Sp0;
    byte    UCOAction;
    byte    Sp2;
    byte    Sp3;

    unsigned    Time;   // hundredths of a second since start (as in SMALL_RTP)
    
    CarContOBJ  C;

    ObjectInfo  Info;   // Info about the checkpoint or circle (see below)
};

// UCOAction byte

enum
{
    UCO_CIRCLE_ENTER,   // entered a circle
    UCO_CIRCLE_LEAVE,   // left a circle
    UCO_CP_FWD,         // crossed cp in forward direction
    UCO_CP_REV,         // crossed cp in reverse direction
};

// Identifying an InSim checkpoint from the ObjectInfo :

// Index is 252.  Checkpoint index (seen in the autocross editor) is stored in Flags bits 0 and 1

// 00 = finish line
// 01 = 1st checkpoint
// 10 = 2nd checkpoint
// 11 = 3rd checkpoint

// Note that the checkpoint index has no meaning in LFS and is provided only for your convenience.
// If you use many InSim checkpoints you may need to identify them with the X and Y values.

// Identifying an InSim circle from the ObjectInfo :

// Index is 253.  The circle index (seen in the autocross editor) is stored in the Heading byte.

struct IS_CSC // Car State Changed - reports a change in a car's state (currently start or stop)
{
    byte    Size;       // 20
    byte    Type;       // ISP_CSC
    byte    ReqI;       // 0
    byte    PLID;       // player's unique id

    byte    Sp0;
    byte    CSCAction;
    byte    Sp2;
    byte    Sp3;

    unsigned    Time;   // hundredths of a second since start (as in SMALL_RTP)

    CarContOBJ  C;
};

// CSCAction byte

enum
{
    CSC_STOP,
    CSC_START,
};


// OBJECT CONTROL - currently used for switching start lights
// ==============

struct IS_OCO // Object COntrol
{
    byte    Size;       // 8
    byte    Type;       // ISP_OCO
    byte    ReqI;       // 0
    byte    Zero;

    byte    OCOAction;  // see below
    byte    Index;      // see below
    byte    Identifier; // identify particular start lights objects (0 to 63 or 255 = all)
    byte    Data;       // see below
};

// OCOAction byte

enum
{
    OCO_ZERO,           // reserved
    OCO_1,              //
    OCO_2,              //
    OCO_3,              //
    OCO_LIGHTS_RESET,   // give up control of all lights
    OCO_LIGHTS_SET,     // use Data byte to set the bulbs
    OCO_LIGHTS_UNSET,   // give up control of the specified lights
    OCO_NUM
};

// Index byte specifies which lights you want to override

// Currently the following values are supported :

// AXO_START_LIGHTS (149)   // overrides temporary start lights in the layout
#define OCO_INDEX_MAIN 240  // special value to override the main start light system

// Identifier byte can be used to override groups of temporary start lights

// It refers to the temporary lights identifier (0 to 63) seen in the layout editor

// Data byte specifies particular bulbs using the low 4 bits

// Bulb bit values for the currently available lights :

// OCO_INDEX_MAIN       AXO_START_LIGHTS

// bit 0 (1) : red1     bit 0 (1) : red
// bit 1 (2) : red2     bit 1 (2) : amber
// bit 2 (4) : red3     -
// bit 3 (8) : green    bit 3 (8) : green


// AUTOCROSS OBJECTS - reporting / adding / removing
// =================

// Set the ISF_AXM_LOAD flag in the IS_ISI for info about objects when a layout is loaded.
// Set the ISF_AXM_EDIT flag in the IS_ISI for info about objects edited by user or InSim.

// You can also add or remove objects by sending IS_AXM packets.
// Some care must be taken with these - please read the notes below.

// You can also get (TTC_SEL) or set (PMO_SELECTION) the current editor selection.

struct IS_AXM // AutoX Multiple objects - variable size
{
    byte    Size;       // 8 + NumO * 8
    byte    Type;       // ISP_AXM
    byte    ReqI;       // 0 unless this is a reply to a TINY_AXM request
    byte    NumO;       // number of objects in this packet

    byte    UCID;       // unique id of the connection that sent the packet
    byte    PMOAction;  // see below
    byte    PMOFlags;   // see below
    byte    Sp3;

    ObjectInfo  Info[30];   // info about each object, 0 to 30 of these
};

// Values for PMOAction byte

enum
{
    PMO_LOADING_FILE,   // 0 - sent by the layout loading system only
    PMO_ADD_OBJECTS,    // 1 - adding objects (from InSim or editor)
    PMO_DEL_OBJECTS,    // 2 - delete objects (from InSim or editor)
    PMO_CLEAR_ALL,      // 3 - clear all objects (NumO must be zero)
    PMO_TINY_AXM,       // 4 - a reply to a TINY_AXM request
    PMO_TTC_SEL,        // 5 - a reply to a TTC_SEL request
    PMO_SELECTION,      // 6 - set a connection's layout editor selection
    PMO_NUM
};

// Info about the PMOFlags byte :

#define PMO_FILE_END            1
#define PMO_SUPPRESS_WARNINGS   2

// If PMO_FILE_END is set in a PMO_LOADING_FILE packet, LFS has reached the end of
// a layout file which it is loading.  The added objects will then be optimised.

// Optimised in this case means that static vertex buffers will be created for all
// objects, to greatly improve the frame rate.  The problem with this is that when
// there are many objects loaded, optimisation causes a significant glitch which can
// be long enough to cause a driver who is cornering to lose control and crash.

// PMO_FILE_END can also be set in an IS_AXM with PMOAction of PMO_ADD_OBJECTS.
// This causes all objects to be optimised.  It is important not to set PMO_FILE_END
// in every packet you send to add objects or you will cause severe glitches on the
// clients computers.  It is ok to have some objects on the track which are not
// optimised.  So if you have a few objects that are being removed and added
// occasionally, the best advice is not to request optimisation at all.  Only
// request optimisation (by setting PMO_FILE_END) if you have added so many objects
// that it is needed to improve the frame rate.

// NOTE 1) LFS makes sure that all objects are optimised when the race restarts.
// NOTE 2) In the 'more' section of SHIFT+U there is info about optimised objects.

// If you are using InSim to send many packets of objects (for example loading an
// entire layout through InSim) then you must take care of the bandwidth and buffer
// overflows.  You must not try to send all the objects at once.  It's probably good
// to use LFS's method of doing this : send the first packet of objects then wait for
// the corresponding IS_AXM that will be output when the packet is processed.  Then
// you can send the second packet and again wait for the IS_AXM and so on.

// To request IS_AXM packets for all layout objects and circles send this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_AXM      (request IS_AXM packets for the entire layout)

// LFS will send as many IS_AXM packets as needed to describe the whole layout.
// If there are no objects or circles, there will be one IS_AXM with zero NumO.
// The final IS_AXM packet will have the PMO_FILE_END flag set.

// To request an IS_AXM for a connection's layout editor selection send this IS_TTC :

// ReqI : non-zero      (returned in the reply)
// SubT : TTC_SEL       (request an IS_AXM for the current selection)
// UCID : connection    (0 = local / non-zero = guest)


// CAR POSITION PACKETS (Initialising OutSim from InSim - See "OutSim" below)
// ====================

// To request Car Positions from the currently viewed car, send this IS_SMALL :

// ReqI : 0
// SubT : SMALL_SSP     (Start Sending Positions)
// UVal : interval      (time between updates - zero means stop sending)

// If OutSim has not been setup in cfg.txt, the SSP packet makes LFS send UDP packets
// if in game, using the OutSim system as documented near the end of this text file.

// You do not need to set any OutSim values in LFS cfg.txt - OutSim is fully
// initialised by the SSP packet.

// The OutSim packets will be sent to the UDP port specified in the InSimInit packet.

// NOTE : OutSim packets are not InSim packets and don't have a 4-byte header.


// DASHBOARD PACKETS (Initialising OutGauge from InSim - See "OutGauge" below)
// =================

// To request Dashboard Packets from the currently viewed car, send this IS_SMALL :

// ReqI : 0
// SubT : SMALL_SSG     (Start Sending Gauges)
// UVal : interval      (time between updates - zero means stop sending)

// If OutGauge has not been setup in cfg.txt, the SSG packet makes LFS send UDP packets
// if in game, using the OutGauge system as documented near the end of this text file.

// You do not need to set any OutGauge values in LFS cfg.txt - OutGauge is fully
// initialised by the SSG packet.

// The OutGauge packets will be sent to the UDP port specified in the InSimInit packet.

// NOTE : OutGauge packets are not InSim packets and don't have a 4-byte header.


// CAMERA CONTROL
// ==============

// IN GAME camera control
// ----------------------

// You can set the viewed car and selected camera directly with a special packet
// These are the states normally set in game by using the TAB and V keys

struct IS_SCC // Set Car Camera - Simplified camera packet (not SHIFT+U mode)
{
    byte    Size;       // 8
    byte    Type;       // ISP_SCC
    byte    ReqI;       // 0
    byte    Zero;

    byte    ViewPLID;   // Unique ID of player to view
    byte    InGameCam;  // InGameCam (as reported in StatePack)
    byte    Sp2;
    byte    Sp3;
};

// NOTE : Set InGameCam or ViewPLID to 255 to leave that option unchanged.

// DIRECT camera control
// ---------------------

// A Camera Position Packet can be used for LFS to report a camera position and state.
// An InSim program can also send one to set LFS camera position in game or SHIFT+U mode.

// Type : "Vec" : 3 ints (X, Y, Z) - 65536 means 1 metre

struct IS_CPP // Cam Pos Pack - Full camera packet (in car OR SHIFT+U mode)
{
    byte    Size;       // 32
    byte    Type;       // ISP_CPP
    byte    ReqI;       // instruction : 0 / or reply : ReqI as received in the TINY_SCP
    byte    Zero;

    Vec     Pos;        // Position vector

    word    H;          // heading - 0 points along Y axis
    word    P;          // pitch
    word    R;          // roll

    byte    ViewPLID;   // Unique ID of viewed player (0 = none)
    byte    InGameCam;  // InGameCam (as reported in StatePack)

    float   FOV;        // 4-byte float : FOV in degrees

    word    Time;       // Time in ms to get there (0 means instant)
    word    Flags;      // ISS state flags (see below)
};

// The ISS state flags that can be set are :

// ISS_SHIFTU           - in SHIFT+U mode
// ISS_SHIFTU_FOLLOW    - FOLLOW view
// ISS_VIEW_OVERRIDE    - override user view

// On receiving this packet, LFS will set up the camera to match the values in the packet,
// including switching into or out of SHIFT+U mode depending on the ISS_SHIFTU flag.

// If ISS_VIEW_OVERRIDE is set, the in-car view Heading Pitch and Roll (but not FOV) will
// be taken from the values in this packet.  Otherwise normal in game control will be used.

// Position vector (Vec Pos) - in SHIFT+U mode, Pos can be either relative or absolute.

// If ISS_SHIFTU_FOLLOW is set, it's a following camera, so the position is relative to
// the selected car.  Otherwise, the position is absolute, as used in normal SHIFT+U mode.

// NOTE : Set InGameCam or ViewPLID to 255 to leave that option unchanged.

// SMOOTH CAMERA POSITIONING
// --------------------------

// The "Time" value in the packet is used for camera smoothing.  A zero Time means instant
// positioning.  Any other value (milliseconds) will cause the camera to move smoothly to
// the requested position in that time.  This is most useful in SHIFT+U camera modes or
// for smooth changes of internal view when using the ISS_VIEW_OVERRIDE flag.

// NOTE : You can use frequently updated camera positions with a longer Time value than
// the update frequency.  For example, sending a camera position every 100 ms, with a
// Time value of 1000 ms.  LFS will make a smooth motion from the rough inputs.

// If the requested camera mode is different from the one LFS is already in, it cannot
// move smoothly to the new position, so in this case the "Time" value is ignored.

// GETTING A CAMERA PACKET
// -----------------------

// To GET a CamPosPack from LFS, send this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_SCP      (Send Cam Pos)

// LFS will reply with a CamPosPack as described above.  You can store this packet
// and later send back exactly the same packet to LFS and it will try to replicate
// that camera position.


// TIME CONTROL
// ============

// Request the current time at any point with this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_GTH      (Get Time in Hundredths)

// The time will be sent back in this IS_SMALL :

// ReqI : non-zero      (as received in the request packet)
// SubT : SMALL_RTP     (Race Time Packet)
// UVal : Time          (hundredths of a second since start of race or replay)

// You can stop or start time in LFS and while it is stopped you can send packets to move
// time in steps.  Time steps are specified in hundredths of a second.
// Warning : unlike pausing, this is a "trick" to LFS and the program is unaware of time
// passing so you must not leave it stopped because LFS is unusable in that state.
// This packet is not available in live multiplayer mode.

// Stop and Start with this IS_SMALL :

// ReqI : 0
// SubT : SMALL_TMS     (TiMe Stop)
// UVal : stop          (1 - stop / 0 - carry on)

// When STOPPED, make time step updates with this IS_SMALL :

// ReqI : 0
// SubT : SMALL_STP     (STeP)
// UVal : number        (number of hundredths of a second to update)


// REPLAY CONTROL
// ==============

// You can load a replay or set the position in a replay with an IS_RIP packet.
// Replay positions and lengths are specified in hundredths of a second.
// LFS will reply with another IS_RIP packet when the request is completed.

struct IS_RIP // Replay Information Packet
{
    byte    Size;       // 80
    byte    Type;       // ISP_RIP
    byte    ReqI;       // request : non-zero / reply : same value returned
    byte    Error;      // 0 or 1 = OK / other values are listed below

    byte    MPR;        // 0 = SPR / 1 = MPR
    byte    Paused;     // request : pause on arrival / reply : paused state
    byte    Options;    // various options - see below
    byte    Sp3;

    unsigned    CTime;  // (hundredths) request : destination / reply : position
    unsigned    TTime;  // (hundredths) request : zero / reply : replay length

    char    RName[64];  // zero or replay name - last byte must be zero
};

// NOTE about RName :
// In a request, replay RName will be loaded.  If zero then the current replay is used.
// In a reply, RName is the name of the current replay, or zero if no replay is loaded.

// You can request an IS_RIP packet at any time with this IS_TINY :

// ReqI : non-zero      (returned in the reply)
// SubT : TINY_RIP      (Replay Information Packet)

// Error codes returned in IS_RIP replies :

enum
{
    RIP_OK,             //  0 - OK : completed instruction
    RIP_ALREADY,        //  1 - OK : already at the destination
    RIP_DEDICATED,      //  2 - can't run a replay - dedicated host
    RIP_WRONG_MODE,     //  3 - can't start a replay - not in a suitable mode
    RIP_NOT_REPLAY,     //  4 - RName is zero but no replay is currently loaded
    RIP_CORRUPTED,      //  5 - IS_RIP corrupted (e.g. RName does not end with zero)
    RIP_NOT_FOUND,      //  6 - the replay file was not found
    RIP_UNLOADABLE,     //  7 - obsolete / future / corrupted
    RIP_DEST_OOB,       //  8 - destination is beyond replay length
    RIP_UNKNOWN,        //  9 - unknown error found starting replay
    RIP_USER,           // 10 - replay search was terminated by user
    RIP_OOS,            // 11 - can't reach destination - SPR is out of sync
};

// Options byte : some options

#define RIPOPT_LOOP         1       // replay will loop if this bit is set
#define RIPOPT_SKINS        2       // set this bit to download missing skins
#define RIPOPT_FULL_PHYS    4       // use full physics when searching an MPR

// NOTE : RIPOPT_FULL_PHYS makes MPR searching much slower so should not normally be used.
// This flag was added to allow high accuracy MCI packets to be output when fast forwarding.


// SCREENSHOTS
// ===========

// You can instuct LFS to save a screenshot in data\shots using the IS_SSH packet.
// It will be saved as bmp / jpg / png as set in Misc Options.
// Name can be a filename (excluding extension) or zero - LFS will create a name.
// LFS will reply with another IS_SSH when the request is completed.

struct IS_SSH // ScreenSHot
{
    byte    Size;       // 40
    byte    Type;       // ISP_SSH
    byte    ReqI;       // request : non-zero / reply : same value returned
    byte    Error;      // 0 = OK / other values are listed below

    byte    Sp0;        // 0
    byte    Sp1;        // 0
    byte    Sp2;        // 0
    byte    Sp3;        // 0

    char    Name[32];   // name of screenshot file - last byte must be zero
};

// Error codes returned in IS_SSH replies :

enum
{
    SSH_OK,             //  0 - OK : completed instruction
    SSH_DEDICATED,      //  1 - can't save a screenshot - dedicated host
    SSH_CORRUPTED,      //  2 - IS_SSH corrupted (e.g. Name does not end with zero)
    SSH_NO_SAVE,        //  3 - could not save the screenshot
};


// BUTTONS
// =======

// You can make up to 240 buttons appear on the host or guests (ID = 0 to 239).
// You should set the ISF_LOCAL flag (in IS_ISI) if your program is not a host control
// system, to make sure your buttons do not conflict with any buttons sent by the host.

// LFS can display normal buttons in these four screens :

// - main entry screen
// - race setup screen
// - in game
// - SHIFT+U mode

// The recommended area for most buttons is defined by :

#define IS_X_MIN 0
#define IS_X_MAX 110

#define IS_Y_MIN 30
#define IS_Y_MAX 170

// If you draw buttons in this area, the area will be kept clear to
// avoid overlapping LFS buttons with your InSim program's buttons.
// Buttons outside that area will not have a space kept clear.
// You can also make buttons visible in all screens - see below.

// To delete one button or a range of buttons or clear all buttons, send this packet :

struct IS_BFN // Button FunctioN - delete buttons / receive button requests
{
    byte    Size;       // 8
    byte    Type;       // ISP_BFN
    byte    ReqI;       // 0
    byte    SubT;       // subtype, from BFN_ enumeration (see below)

    byte    UCID;       // connection to send to or received from (0 = local / 255 = all)
    byte    ClickID;    // if SubT is BFN_DEL_BTN : ID of single button to delete or first button in range
    byte    ClickMax;   // if SubT is BFN_DEL_BTN : ID of last button in range (if greater than ClickID)
    byte    Inst;       // used internally by InSim
};

enum // the fourth byte of IS_BFN packets is one of these
{
    BFN_DEL_BTN,        //  0 - instruction     : delete one button or range of buttons (must set ClickID)
    BFN_CLEAR,          //  1 - instruction     : clear all buttons made by this insim instance
    BFN_USER_CLEAR,     //  2 - info            : user cleared this insim instance's buttons
    BFN_REQUEST,        //  3 - user request    : SHIFT+B or SHIFT+I - request for buttons
};

// NOTE : BFN_REQUEST allows the user to bring up buttons with SHIFT+B or SHIFT+I

// SHIFT+I clears all host buttons if any - or sends a BFN_REQUEST to host instances
// SHIFT+B is the same but for local buttons and local instances

// To send a button to LFS, send this variable sized packet

struct IS_BTN // BuTtoN - button header - followed by 0 to 240 characters
{
    byte    Size;       // 12 + TEXT_SIZE (a multiple of 4)
    byte    Type;       // ISP_BTN
    byte    ReqI;       // non-zero (returned in IS_BTC and IS_BTT packets)
    byte    UCID;       // connection to display the button (0 = local / 255 = all)

    byte    ClickID;    // button ID (0 to 239)
    byte    Inst;       // some extra flags - see below
    byte    BStyle;     // button style flags - see below
    byte    TypeIn;     // max chars to type in - see below

    byte    L;          // left   : 0 - 200
    byte    T;          // top    : 0 - 200
    byte    W;          // width  : 0 - 200
    byte    H;          // height : 0 - 200

//  char    Text[TEXT_SIZE]; // 0 to 240 characters of text
};

// ClickID byte : this value is returned in IS_BTC and IS_BTT packets.

// Host buttons and local buttons are stored separately, so there is no chance of a conflict between
// a host control system and a local system (although the buttons could overlap on screen).

// Programmers of local InSim programs may wish to consider using a configurable button range and
// possibly screen position, in case their users will use more than one local InSim program at once.

// TypeIn byte : if set, the user can click this button to type in text.

// Lowest 7 bits are the maximum number of characters to type in (0 to 95)
// Highest bit (128) can be set to initialise dialog with the button's text

// On clicking the button, a text entry dialog will be opened, allowing the specified number of
// characters to be typed in.  The caption on the text entry dialog is optionally customisable using
// Text in the IS_BTN packet.  If the first character of IS_BTN's Text field is zero, LFS will read
// the caption up to the second zero.  The visible button text then follows that second zero.

// Text : 65-66-67-0 would display button text "ABC" and no caption

// Text : 0-65-66-67-0-68-69-70-71-0-0-0 would display button text "DEFG" and caption "ABC"

// Inst byte : mainly used internally by InSim but also provides some extra user flags

#define INST_ALWAYS_ON  128     // if this bit is set the button is visible in all screens

// NOTE : You should not use INST_ALWAYS_ON for most buttons.  This is a special flag for buttons
// that really must be on in all screens (including the garage and options screens).  You will
// probably need to confine these buttons to the top or bottom edge of the screen, to avoid
// overwriting LFS buttons.  Most buttons should be defined without this flag, and positioned
// in the recommended area so LFS can keep a space clear in the main screens.

// BStyle byte : style flags for the button

#define ISB_C1          1       // you can choose a standard
#define ISB_C2          2       // interface colour using
#define ISB_C4          4       // these 3 lowest bits - see below
#define ISB_CLICK       8       // click this button to send IS_BTC
#define ISB_LIGHT       16      // light button
#define ISB_DARK        32      // dark button
#define ISB_LEFT        64      // align text to left
#define ISB_RIGHT       128     // align text to right

// colour 0 : light grey        (not user editable)
// colour 1 : title colour      (default:yellow)
// colour 2 : unselected text   (default:black)
// colour 3 : selected text     (default:white)
// colour 4 : ok                (default:green)
// colour 5 : cancel            (default:red)
// colour 6 : text string       (default:pale blue)
// colour 7 : unavailable       (default:grey)

// NOTE : If width or height are zero, this would normally be an invalid button.  But in that case if
// there is an existing button with the same ClickID, all the packet contents are ignored except the
// Text field.  This can be useful for updating the text in a button without knowing its position.
// For example, you might reply to an IS_BTT using an IS_BTN with zero W and H to update the text.

// Replies : If the user clicks on a clickable button, this packet will be sent :

struct IS_BTC // BuTton Click - sent back when user clicks a button
{
    byte    Size;       // 8
    byte    Type;       // ISP_BTC
    byte    ReqI;       // ReqI as received in the IS_BTN
    byte    UCID;       // connection that clicked the button (zero if local)

    byte    ClickID;    // button identifier originally sent in IS_BTN
    byte    Inst;       // used internally by InSim
    byte    CFlags;     // button click flags - see below
    byte    Sp3;
};

// CFlags byte : click flags

#define ISB_LMB         1       // left click
#define ISB_RMB         2       // right click
#define ISB_CTRL        4       // ctrl + click
#define ISB_SHIFT       8       // shift + click

// If the TypeIn byte is set in IS_BTN the user can type text into the button
// In that case no IS_BTC is sent - an IS_BTT is sent when the user presses ENTER

struct IS_BTT // BuTton Type - sent back when user types into a text entry button
{
    byte    Size;       // 104
    byte    Type;       // ISP_BTT
    byte    ReqI;       // ReqI as received in the IS_BTN
    byte    UCID;       // connection that typed into the button (zero if local)

    byte    ClickID;    // button identifier originally sent in IS_BTN
    byte    Inst;       // used internally by InSim
    byte    TypeIn;     // from original button specification
    byte    Sp3;

    char    Text[96];   // typed text, zero to TypeIn specified in IS_BTN
};


// OutSim - MOTION SIMULATOR SUPPORT
// ======

// The user's car in multiplayer or the viewed car in single player or
// single player replay can output information to a motion system while
// viewed from an internal view.

// This can be controlled by 5 lines in the cfg.txt file :

// OutSim Mode 0        :0-off 1-driving 2-driving+replay
// OutSim Delay 1       :minimum delay between packets (100ths of a sec)
// OutSim IP 0.0.0.0    :IP address to send the UDP packet
// OutSim Port 0        :IP port
// OutSim ID 0          :if not zero, adds an identifier to the packet

// Each update sends the following UDP packet :

struct OutSimPack
{
    unsigned    Time;       // time in milliseconds (to check order)

    Vector      AngVel;     // 3 floats, angular velocity vector
    float       Heading;    // anticlockwise from above (Z)
    float       Pitch;      // anticlockwise from right (X)
    float       Roll;       // anticlockwise from front (Y)
    Vector      Accel;      // 3 floats X, Y, Z
    Vector      Vel;        // 3 floats X, Y, Z
    Vec         Pos;        // 3 ints   X, Y, Z (1m = 65536)

    int         ID;         // optional - only if OutSim ID is specified
};

// NOTE 1) X and Y axes are on the ground, Z is up.

// NOTE 2) Motion simulators can be dangerous.  The Live for Speed developers do
// not support any motion systems in particular and cannot accept responsibility
// for injuries or deaths connected with the use of such machinery.


// OutGauge - EXTERNAL DASHBOARD SUPPORT
// ========

// The user's car in multiplayer or the viewed car in single player or
// single player replay can output information to a dashboard system
// while viewed from an internal view.

// This can be controlled by 5 lines in the cfg.txt file :

// OutGauge Mode 0        :0-off 1-driving 2-driving+replay
// OutGauge Delay 1       :minimum delay between packets (100ths of a sec)
// OutGauge IP 0.0.0.0    :IP address to send the UDP packet
// OutGauge Port 0        :IP port
// OutGauge ID 0          :if not zero, adds an identifier to the packet

// Each update sends the following UDP packet :

struct OutGaugePack
{
    unsigned    Time;           // time in milliseconds (to check order)

    char        Car[4];         // Car name
    word        Flags;          // Info (see OG_x below)
    byte        Gear;           // Reverse:0, Neutral:1, First:2...
    byte        PLID;           // Unique ID of viewed player (0 = none)
    float       Speed;          // M/S
    float       RPM;            // RPM
    float       Turbo;          // BAR
    float       EngTemp;        // C
    float       Fuel;           // 0 to 1
    float       OilPressure;    // BAR
    float       OilTemp;        // C
    unsigned    DashLights;     // Dash lights available (see DL_x below)
    unsigned    ShowLights;     // Dash lights currently switched on
    float       Throttle;       // 0 to 1
    float       Brake;          // 0 to 1
    float       Clutch;         // 0 to 1
    char        Display1[16];   // Usually Fuel
    char        Display2[16];   // Usually Settings

    int         ID;             // optional - only if OutGauge ID is specified
};

// OG_x - bits for OutGaugePack Flags

#define OG_SHIFT        1       // key
#define OG_CTRL         2       // key

#define OG_TURBO        8192    // show turbo gauge
#define OG_KM           16384   // if not set - user prefers MILES
#define OG_BAR          32768   // if not set - user prefers PSI

// DL_x - bits for OutGaugePack DashLights and ShowLights

enum
{
    DL_SHIFT,           // bit 0    - shift light
    DL_FULLBEAM,        // bit 1    - full beam
    DL_HANDBRAKE,       // bit 2    - handbrake
    DL_PITSPEED,        // bit 3    - pit speed limiter
    DL_TC,              // bit 4    - TC active or switched off
    DL_SIGNAL_L,        // bit 5    - left turn signal
    DL_SIGNAL_R,        // bit 6    - right turn signal
    DL_SIGNAL_ANY,      // bit 7    - shared turn signal
    DL_OILWARN,         // bit 8    - oil pressure warning
    DL_BATTERY,         // bit 9    - battery warning
    DL_ABS,             // bit 10   - ABS active or switched off
    DL_SPARE,           // bit 11
    DL_NUM
};

//////
#endif
```