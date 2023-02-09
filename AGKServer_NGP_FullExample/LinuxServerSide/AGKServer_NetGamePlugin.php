<?php

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

/***** Configuration *****/

define("NETGAMEPLUGIN_WORLDSTATE_INTERVAL", 100000); // Interval to send World State (Players/Entity/NPC Positions) in nanoseconds => 500ms

/*****************************************************************************
 ******************** YOU SHLOUD NOT MODIFY CODE UNDER THIS COMMENT **********
 *****************************************************************************/

/***** Internal Constants *****/

$NGP_SlotConstants = array("POS_X", "POS_Y", "POS_Z", "ANG_X", "ANG_Y", "ANG_Z", "POS_X2", "POS_Y2", "POS_Z2", "ANG_X2", "ANG_Y2", "ANG_Z2");

foreach ($NGP_SlotConstants as $Index => $Name) {
	define($Name, $Index + 1);
}

/***** Declare Global Variables *****/
$PlayersPosition = array();
$PlayerMoves = array();
$SendNetworkStateTimer = array();

/***** Plugin Functions *****/

function NGP_sendWorldState($ChannelNumber) {

	global $SendNetworkStateTimer;
	global $PlayersPosition;
	global $NGP_SlotConstants;
	//writeLog("Notify Channel WorldState : " . $ChannelNumber);
	if (microtime(true)-@$SendNetworkStateTimer[$ChannelNumber] > NETGAMEPLUGIN_WORLDSTATE_INTERVAL / 1000000) {

		$SendNetworkStateTimer[$ChannelNumber] = microtime(true);
		//writeLog("Refresh Network State");

		$msg = new NetworkMessage();

		foreach (GetChannelClientsList($ChannelNumber) as $Player) {
			// for data of each client
			if ($Player['ID'] == 1) {
				// Do not send to Server itself !
				continue;
			}

			$msg->AddNetworkMessageInteger(6667) // Notify Everyone from every position
				->AddNetworkMessageInteger($Player['ID'])
				->AddNetworkMessageInteger(@$PlayersPosition[$Player['ID']]["LastProcessedInput"]) // used for self client-side prediction
				->AddNetworkMessageInteger(@$PlayersPosition[$Player['ID']]["LocalTime"]); // used for others clients interpolation

			for ($i = 1; $i <= count($NGP_SlotConstants); $i++) {
				// send all slots
				$msg->AddNetworkMessageFloat(@$PlayersPosition[$Player['ID']]["SLOT_" . $i]);
			}

			$msg->SendChannel($ChannelNumber);
		}
	}
}

function NGP_initClient($iClientID) {
	global $PlayersPosition;
	global $NGP_SlotConstants;

	for ($i = 1; $i <= count($NGP_SlotConstants); $i++) {
		$PlayersPosition[$iClientID]["SLOT_" . $i] = 0;
	}

	$PlayersPosition[$iClientID]["LastProcessedInput"] = 0;
	$PlayersPosition[$iClientID]["LocalTime"] = 0;

	writeLog("Send WORLD_STEP_MS To client " . $iClientID . " => " . (NETGAMEPLUGIN_WORLDSTATE_INTERVAL / 1000) . "ms");

	// Send WORLD_STEP_MS to Client !

	$msgWSInterval = new NetworkMessage();
	$msgWSInterval->AddNetworkMessageInteger(6665) // Message identifier to send WORLD_STEP_INTERVAL
		->AddNetworkMessageInteger(NETGAMEPLUGIN_WORLDSTATE_INTERVAL / 1000)
		->Send($iClientID);

}

function NGP_destroyClient($iClientID) {
	global $PlayersPosition, $PlayerMoves;

	unset($PlayersPosition[$iClientID]);
	unset($PlayerMoves[$iClientID]);
}

function NGP_SetClientState($iClientID, $SlotNumber, $Value) {
	global $PlayersPosition;
	$PlayersPosition[$iClientID]["SLOT_" . $SlotNumber] = $Value;

}

function NGP_GetClientState($iClientID) {
	global $PlayersPosition, $NGP_SlotConstants;

	$tmpState = array();
	for ($i = 0; $i < count($NGP_SlotConstants); $i++) {
		$tmpState[$NGP_SlotConstants[$i]] = $PlayersPosition[$iClientID]["SLOT_" . ($i + 1)];
	}

	return $tmpState;

}

function NGP_CheckForReceivedMovements($iSenderID, $iDestinationID, $Message) {

	global $PlayerMoves, $PlayersPosition;
	$msg = new NetworkMessage(); // instantiate once for all future message in this event.

	// If message is sent to AGKServer (always ID 1)
	if ($iDestinationID == 1) {

		$iCommand = GetNetworkMessageInteger($Message);

		if ($iCommand == 6666) {
			// Movements

			$tmpPlayerMove = array(
				'SeqNumber' => GetNetworkMessageInteger($Message),
				'Speed' => GetNetworkMessageFloat($Message),
				'Slot' => GetNetworkMessageInteger($Message),
				'Velocity' => GetNetworkMessageFloat($Message),
				'LocalTime' => GetNetworkMessageInteger($Message),

			);

			//writeLog(print_r($tmpPlayerMove, true));
			$PlayerMoves[$iSenderID][] = $tmpPlayerMove;
			NGP_ApplyMovement($iSenderID, $tmpPlayerMove);

		}

	}

}

/***** Internal Functions *****/

function NGP_ApplyMovement($iClientID, $aInput) {

	global $PlayerMoves, $PlayersPosition;

	$PlayersPosition[$iClientID]["SLOT_" . $aInput['Slot']] = $PlayersPosition[$iClientID]["SLOT_" . $aInput['Slot']] + ($aInput['Velocity'] * $aInput['Speed']);

	$PlayersPosition[$iClientID]["LastProcessedInput"] = $aInput['SeqNumber'];
	$PlayersPosition[$iClientID]["LocalTime"] = $aInput['LocalTime'];

	//writeLog(print_r($PlayersPosition[$iClientID], true));

}
