# AGK Multiplayer Server

AGK Multiplyer Server is a daemon written in PHP to allow easy customization and interactions between your games, websites and databases (MySQL for example). So the server could also work on MacOS X , and Windows in debug mode only (not as a daemon). You only need PHP-CLI and major modules installed.

The server is just... one PHP file and should be compatible PHP5>PHP7 (maybe PHP4 but not tested). You put it on your linux server. You chmod +x it, you launch it (even without any customization) and you're ready to go.

I've made the server fully compatible with almost all AppGameKit network commands.

However, I had to resolve one thing : supporting a lot of players without broadcasting all the local network variables to all the server-connected players. Players are generally "isolated" in "party","room" or "game" virtual spaces. or maybe in a totally different agk game !

So,I have added one important thing : Channels. Virtual channels (or rooms) can isolate group of clients/players as if they were on an independant host server. So, only one instance of the Server could host multiple Network games or apps.

To change default channel (channelNumber 0), your App Client only need to call the AppGameKit Command SetNetworkLocalInteger( networkId, "SERVER_CHANNEL", channel_number) and the server will do the rest and notify correctly each client which is concerned for join/quits/variables/updates/messages etc..


Of course, the AppGameKit Server is customizable interacting with network events as explained in the help documentation :

```
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
//
//
//                                _____ _  __   _____
//                          /\   / ____| |/ /  / ____|
//                         /  \ | |  __| ' /  | (___   ___ _ ____   _____ _ __
//                        / /\ \| | |_ |  <    \___ \ / _ \ '__\ \ / / _ \ '__|
//                       / ____ \ |__| | . \   ____) |  __/ |   \ V /  __/ |
//                      /_/    \_\_____|_|\_\ |_____/ \___|_|    \_/ \___|_|
//
//                                            By MikeMax
//                                       (mikemaxfr@gmail.com)
//
//
// * AGK Server Command-line options :
//   ---------------------------------
//
//    Please first ! ===>>   chmod +x AGKServer.php
//
//  and then :
//
//    ./AGKServer.php debug             => Start AGKServer in debug mode (direct terminal output ... CTRL + C to stop server)
//    ./AGKServer.php start             => Start AGKServer as a daemon
//    ./AGKServer.php stop              => Stop AGKServer daemon
//
//
//
// * Available functions for Events :
//   --------------------------------
//
// - onAGKClientJoin($iClientID) => Triggered when a client connects to this server
// - onAGKClientQuit($iClientID) => Triggered when a client disconnects from this server
// - onAGKClientChangeChannel($iClientID, $iOldChannelNumber, $iNewChannelNumber) => Triggered when client is joining a new channel
// - onAGKClientNetworkMessage($iSenderID, $iDestinationID, $Message) => Triggered when a client has sent a message to any client(s)
// - onAGKClientUpdateVariable($iClientID,$sVariableName,$mVariableValue) => Triggered when a client has updated a variable.
// - onAGKServerRefreshTimer() => Triggered every SERVER_REFRESH_INTERVAL nanoseconds.
//
// * Available Methods/Commands within Events :
//   ------------------------------------------
//
// - writeLog($sString) => echoes $string in terminal in debug mode or in a log file (AGKServer.php.log beside the php file) in daemon mode.
//
// - GetClientName($iClientID) => (string) Retrieve name of the client
// - GetClientIP($iClientID) => (string) Retrieve client's IP Address
// - GetClientChannelNumber($iClientID) => (integer) Retrieve current client channel number
// - GetNetworkClientVariable($iClientID, $sVariableName) => (variant) Get Local variable (integer or float) from client (Respect the AGK Network ResetMode)
// - GetNetworkClientInteger($iClientID, $sVariableName) => (integer) Alias of GetNetworkClientVariable command
// - GetNetworkClientFloat($iClientID, $sVariableName) => (float) Alias of GetNetworkClientVariable command
// - SetNetworkLocalInteger($ChannelNumber, $sVariableName, $iValue [, $ResetMode = 0]) => Sets/updates a server local integer variable which will be transmitted to clients of $iChannelNumber
// - SetNetworkLocalFloat($ChannelNumber, $sVariableName, $fValue [, $ResetMode = 0]) => Sets/updates a server local float variable which will be transmitted to clients of $iChannelNumber
//
// - GetChannelClientsCount($iChannelNumber) => (integer) Retrieve number of clients in $iChannelNumber
// - GetChannelClientsList($iChannelNumber) => (array of ("ID","Name","ChannelNumber")) Retrieve Clients Names,IDs,ChannelNumber in $iChannelNumber ($iChannelNumber = -1 to retrieve ALL connected Clients with respective ChannelNumber)
// - GetChannelsList() => (array of('ChannelNumber')) => Retrieve IDs of nonempty channels
// - forceChannelJoin($PlayerID, $ChannelNumber) => Make client to join a specific channel number (warning : doesn't not update his SERVER_CHANNEL variable !)
//
// - GetNetworkMessageString($Message) => (string) Extract expected String from the message and advances the message pointer
// - GetNetworkMessageInteger($Message) => (integer) Extract expected Integer from the message and advances the message pointer
// - GetNetworkMessageFloat($Message) => (float) Extract expected Float from the message and advances the message pointer
//
// - StopPropagation() => Tell explicitely to the server to DO NOT transmit received message or variables to targetted client(s). (Very special cases)
//
// - Class NetworkMessage() => You can create one or more messages with differents data types (string, integer, float)
//
//            Methods :
//            ---------
//
//            - AddNetworkMessageString(string)         => Add a string into the message instance buffer
//            - AddNetworkMessageInteger(integer)     => Add an integer value into the message instance buffer
//            - AddNetworkMessageFloat(float)         => Add an float value into the message instance buffer
//            - Send(ClientID)                        => Send the message to ClientID and clear the buffer (ClientID=0 to send to ALL clients connected to the server (over the Channel isolation))
//            - SendChannel(ChannelNumber)            => Send the message to all Clients present in ChannelNumber and clear the buffer
//
//            Usage Example 1 (Usual Way) :
//            -----------------------------
//
//            $msg1 = new NetworkMessage(); // Can be instantiated once and be used multiple times in events to avoid recreating for each message.
//
//            $msg1->AddNetworkMessageString("This is an example message for $ClientID1 with a string and integer and float");
//            $msg1->AddNetworkMessageInteger(123456);
//            $msg1->AddNetworkMessageFloat(123.456);
//            $msg1->Send($ClientID1); // Send the message and clear the buffer automatically if you want to reuse the same instance for another message
//
//            $msg1->AddNetworkMessageString("This is an example message for $ClientID1 with a string and integer and float");
//            $msg1->AddNetworkMessageInteger(123456);
//            $msg1->AddNetworkMessageFloat(123.456);
//            $msg1->Send($ClientID2); // Send Message and Clear buffer...
//
//
//
//            Usage Example 2 (Fluent Interface Way) :
//            ----------------------------------------
//
//             $msg2 = new NetworkMessage();
//             $msg2->AddNetworkMessageString("This is an example message with a string and integer and float")
//                  ->AddNetworkMessageInteger(123456)
//                  ->AddNetworkMessageFloat(123.456)
//                  ->Send($ClientID1)
//                  ->AddNetworkMessageString("This is another example message with a string and Float")
//                  ->AddNetworkMessageFloat(123456789)
//                  ->AddNetworkMessageFloat(123.456)
//                  ->Send($ClientID2);
//
//
//
//* TODO :
//  ------
//
// - Commands to create Virtual Client/Players (bots) and interact with.
// - Make the server able to work in a cluster (with mirrors to prevent server failures)
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

```

**Edit 03/06/2017** : AGK Server has now a Plugin called "NetGamePlugin" for realtime Network games which needs Client-Side Prediction, Reconciliation and Interpolation to bring smooth animations !

For these kind of realtime game, The server is authoritative. Client simply sends movements (direction and Velocity but not the global position !) and Server will calculates World Position of each client and send them back to clients (with updatePlayer events). To prevent local latencies, Local player has its own event which use prediction and reconciliation. Thje plugin have been made to be basically 2D/3D Compatible.

As for the AppGameKit Server, everything is made in a "Event Driven Architecture". The plugin consists of two files : a server side plugin (AGKServer_NetGamePlugin.php to include in AppGameKit Server Core file) and a AppGameKit Client Plugin ( NetGamePlugin.agc )

Here is the documentation of the Server-side plugin :

```

////////////////////////////////////////////////////////////////////////////////////////////////
//
//
//          _   _      _   _____                       ______ _             _
//         | \ | |    | | |  __ \                      | ___ \ |           (_)
//         |  \| | ___| |_| |  \/ __ _ _ __ ___   ___  | |_/ / |_   _  __ _ _ _ __
//         | . ` |/ _ \ __| | __ / _` | '_ ` _ \ / _ \ |  __/| | | | |/ _` | | '_ \
//         | |\  |  __/ |_| |_\ \ (_| | | | | | |  __/ | |   | | |_| | (_| | | | | |
//         \_| \_/\___|\__|\____/\__,_|_| |_| |_|\___| \_|   |_|\__,_|\__, |_|_| |_|
//                                                                     __/ |
//                                                                    |___/
//
//                                   For AGK Server
//
//                                     By MikeMax
//                               (mikemaxfr@gmail.com)
//
//
//
//
//
//
//
// * Available Methods/Commands within Events :
//   ------------------------------------------
//
//      * Relative event's methods :
//        --------------------------
//
//      - NGP_sendWorldState($ChannelNumber) => Send WorlState (Players positions) to all clients in a channel (should always be in Server Timer 'onAGKServerRefreshTimer' event)
//
//      - NGP_initClient($iClientID) => Initialize new client state and variables and Send WORLD_STEP_MS to client (very important) (should always be in the AGK Server 'onAGKClientJoin' event)
//      - NGP_destroyClient($iClientID) => Free the client state and variables (should always be in the AGK Server 'onAGKClientQuit' event)
//
//      - NGP_CheckForReceivedMovements($iSenderID, $iDestinationID, $Message) => Forward received movement message and process them. (should always be in beginning of the 'onAGKClientNetworkMessage' event)
//
//      * Client Properties methods :
//        ---------------------------
//
//      - NGP_SetClientState($iClientID, $SlotNumber, $Value) => Force client to specific state defining SLOT Values (positions/angles)
//      - NGP_GetClientState($iClientID) => Retrieve Slot values of current State (positions/angles) of a Client
//
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////

```

here is the documentation of the Client-side plugin :

```

////////////////////////////////////////////////////////////////////////////////////////////////
//
//
//          _   _      _   _____                       ______ _             _
//         | \ | |    | | |  __ \                      | ___ \ |           (_)
//         |  \| | ___| |_| |  \/ __ _ _ __ ___   ___  | |_/ / |_   _  __ _ _ _ __
//         | . ` |/ _ \ __| | __ / _` | '_ ` _ \ / _ \ |  __/| | | | |/ _` | | '_ \
//         | |\  |  __/ |_| |_\ \ (_| | | | | | |  __/ | |   | | |_| | (_| | | | | |
//         \_| \_/\___|\__|\____/\__,_|_| |_| |_|\___| \_|   |_|\__,_|\__, |_|_| |_|
//                                                                     __/ |
//                                                                    |___/
//
//                                   For AGK Client
//
//                                     By MikeMax
//                               (mikemaxfr@gmail.com)
//
//
//
//
//
//	* Available Methods :
//	  -------------------
//
//  - NGP_JoinNetwork(ServerHost as string,ServerPort as integer, Nickname as string, NetworkLatency as integer)
//						=> Joins network, initialize Datas and Returns Network ID and should replace the original "JoinNetwork" AGK Command.
//
//  - NGP_UpdateNetwork(iNetID as integer) 	
//						=> Handles the Server communication and update the WorldState Slot of all users and Call Plugin Events
//						=> !!!! Should always be in Main Program Loop !!!
//
//  - NGP_CloseNetwork(iNetID as integer) 
//						=> Close the network and free all datas and should replace the original "CloseNetwork" AGK Command.
//
//  - NGP_SendMovement(iNetID as integer,Slot as integer, Velocity as integer,Speed as float)
//						=> Send to the server the movement to apply to a specific Slot (defined by a velocity in negative or positive depending on the direction you want)
//						=> 12 Slots are available to use freely : 
//								- 3 Slots for Position (2D/3D) : POS_X, POS_Y, POS_Z  => Will be linear interpolated automatically in Player's update event (see available events below)
//								- 3 Slots for Rotation (2D/3D) : ANG_X, ANG_Y, ANG_Z  => Will be angular interpolated automatically in Player's update event (see available events below)
//
//								And 6 others similar slots if you need to handle a second local player or others thing linked to your player (for example a tank (defined by 6 first slots) with rotating canon (defined by 6 other slots or only rotations slots) or something like that)
//			
//						- Constants Slots are defined as follow :
//
//	 						POS_X => 1
// 							POS_Y => 2
// 							POS_Z => 3
// 							ANG_X => 4
// 							ANG_Y => 5
// 							ANG_Z => 6
//
//	 						POS_X2 => 7
// 							POS_Y2 => 8
// 							POS_Z2 => 9
// 							ANG_X2 => 10
// 							ANG_Y2 => 11
// 							ANG_Z2 => 12			
//
//
//	* Available Events :
//	  ------------------
//
// - NGP_onNetworkJoined(iNetID, localClientID) 
//						=> Triggered when your client is connected, bringing you the network ID and the local Client ID
//
// - NGP_onNetworkClosed(iNetID) 
//						=> Triggered when your client is disonnected
//
// - NGP_onNetworkPlayerConnect(iNetID, ClientID) 
//						=> Triggered when a client has just connected the server/channel (includes your own join).
//
// - NGP_onNetworkPlayerDisconnect(iNetID, ClientID)
//						=> Triggered when other client has just quitted the server/channel.
//
// - NGP_onLocalPlayerMoveUpdate(iNetID, UpdatedMove as NGP_Slot)
//						=> Triggered when your own Slots are updated (after automatic prediction and reconciliation)
//
// - NGP_onNetworkPlayerMoveUpdate(iNetID, ClientID as integer, UpdatedMove as NGP_Slot)
//						=> Triggered when other client's Slots are updated (after automatic interpolation)
//
// - NGP_onNetworkMessage(ServerCommand as integer,idMessage as integer)
//						=> Triggered when a network message arrive (other than NPG specific messages)
//						=> Note that all your other network messages should integrate a command identifier as an integer at the beginning of each message
//
////////////////////////////////////////////////////////////////////////////////////////////////

```



You can simply compile and try the Sample AppGameKit Tier1 Game (very basic race game :p), it is configured by default to connect to my 24/7 AppGameKit Server Linux Box (**edit: now Down**)) and contains a lot of comments. Use the key 'G' to make your network ghost appears.
Works Also on mobile devices (added a little Virtual Joystick

The plugin is surely not perfect and there is a lot of variant negotiations for Prediction/Reconciliation/Interpolation... (for example, Extrapolation !)

You can obviously write your own plugin or modify this one !

The basic AppGameKit Server PHP Core File is attached

The NetGamePlugin (AGK Server PHP Core + NGP Plugin + Sample AppGameKit App) is also Attached
