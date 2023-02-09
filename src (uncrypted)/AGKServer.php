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

define("SERVER_DEBUG", false); // Display server Debug informations (true/false)
define("MAX_PING_TIMEOUT_SECONDS", 30); // Over MAX_PING_TIMEOUT_SECONDS Seconds, consider Client as TimeOuted

/*****************************************************************************
 *****************************************************************************
 *****************************************************************************
 *********************                               *************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 ***************************                   *******************************
 *********************                               *************************
 *****************************************************************************
 *****************************************************************************
 *****************************************************************************/
set_time_limit(0); // no limit

// Then, specify on what IP address and ports to listen for use later in
// initializing the class.  It is easier to do this in one section reserved
// for global script settings.

// It's not a bad idea to initialize the class early so you don't try to
// referrence it before it's created accidentily.
$AGKSocket = new floSocket(SERVER_BIND_ADDRESS, SERVER_PORT, SERVER_REFRESH_INTERVAL);

// We don't want this to timeout on us, we want to use our own timeout system.
// For this example, 60 seconds should be sufficient.

//$AGKSocket->set_timeout(60);
/*
You may also want to set the max_execution_time directive in your php.ini file
if you are running in safe mode and you want to run your script indefinitely.

Additionally, your webserver can have other timeouts. E.g. Apache has Timeout
directive, IIS has CGI timeout function, both default to 300 seconds. See the
webserver documentation for meaning of it.
 */

$_AGK_Players = Array();
$_AGK_Players_PingState = Array();

$_AGK_PlayersIP = Array();
$_AGK_PlayersVariables = Array();
$_AGK_ServerVariables = Array();

$_AGK_subChannel = Array();

$StopPropagation = false;

// Let's assign some event capturing functions.
// In the event fired by a new connection, one variable is passed (in this case
// named $channel) which contains an integer index of the floSocket channel
// referrence.
function _agk_new_connection($channel) {
    // We have to reference $AGKSocket because it is not local in this
    // function and we are planning on using it in this example.
    global $AGKSocket, $_AGK_PlayersIP, $_AGK_Players_PingState;

    $_AGK_Players_PingState[$channel] = time();

    // Like I said, we are going to use it.  get_remote_address and
    // get_remote_port are pretty straight forward functions.
    $remote_address = $AGKSocket->get_remote_address($channel);
    $report_port = $AGKSocket->get_remote_port($channel);

    $_AGK_PlayersIP[$channel] = $remote_address;

    // Simple echo lets us know at our console someone connected.
    if (SERVER_DEBUG) {
        writeLog("New connection ($channel) from $remote_address on port $report_port" . PHP_EOL);
    }

}
// The event fired by new data passes the channel as well as the current buffer
// one byte at a time.
function _agk_data_received($channel, $buffer) {
    global $AGKSocket;

    _AGK_HandleReceivedData($channel, $buffer);
    /*
if ($buffer == chr(27)) {
if (SERVER_DEBUG) {
writeLog("STOPPING SERVER!!!  ESC DETECTED");
}

$AGKSocket->stop("Server stopped by ESC detection.");
}

// Or, you can just close the one connection, here with "q" (or "Q").
if ($buffer == "q" || $buffer == "Q") {
$AGKSocket->close($channel, "Channel closed by Q detection.");
}
 */

}

function _AGK_HandleReceivedData($channel, $buffer) {
    global $AGKSocket, $_AGK_Players, $_AGK_PlayersVariables, $_AGK_subChannel, $StopPropagation, $_AGK_Players_PingState;

    // DEBUG
    if (SERVER_DEBUG) {
        writeLog("-----------BEGIN Global Data (" . $channel . " / length: " . strlen($buffer) . ") -----------");
        $tmpLog = "";
        for ($i = 0; $i <= strlen($buffer) - 1; $i++) {
            $tmpLog .= ord($buffer[$i]) . " ";
        }
        writeLog($tmpLog);
        writeLog("-----------END Global Message-----------");
        // END DEBUG
    }
    //writeLog(strlen($buffer) . " octects");
    //////////////////////////////////////// NEW CLIENT JOINS /////////////////////////////////////////////////////////////

    if (!isset($_AGK_Players[$channel])) {

        $ClientNameLength = bin2int(substr($buffer, 0, 4));
        $ClientName = substr($buffer, 4, $ClientNameLength);

        if (SERVER_DEBUG) {
            writeLog("Client " . $ClientName . " has just joined channel " . $channel);
        }

        $AGKSocket->write($channel, int2bin(1));

        ///////////// PLAYERS NAMES
        // CountPlayer+le serveur+le nouveau = +2 ( celui qui vient de se connecter)
        $newPlayerID = $channel + 2;

        //   $ClientName = trim($buffer);

        $AGKSocket->write($channel, int2bin($newPlayerID));

        // CountPlayer avant celui la  (+1 pour le serveur)
        $nbMainRoomPlayers = 0;
        foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {
            if ($_AGK_subChannel[$PlayerChannel] == 0) {
                $nbMainRoomPlayers++;
            }}
        $AGKSocket->write($channel, int2bin(($nbMainRoomPlayers + 1)));

        // Number 1 => The Server
        /////////////////////////////////////

        // Trame de variables serveur
        $ServerVars = _AGK_sendServerNetworkAllVariables(0, $channel, 1);
        // Trame Numero du  serveur (toujours 1) + variables
        $AGKSocket->write($channel, int2bin(1) . int2bin(strlen(SERVER_NAME)) . SERVER_NAME . int2bin($ServerVars['CountVars']) . $ServerVars['Vars']);

        //$AGKSocket->write($channel, $ServerVars['CountVars'] . $ServerVars['Vars']);

        // Send the Clients List to the new client
        ///////////////////////////////////////////
        // $NumPlayer = 2;
        foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {
            if ($_AGK_subChannel[$PlayerChannel] != 0) {
                continue;
            }

            // Trame de variables client
            $ClientVars = _AGK_sendNetworkAllVariables($PlayerChannel, $channel, 1);
            // Trame declaration joueur existant + variables
            $AGKSocket->write($channel, int2bin($PlayerChannel + 2) . int2bin(strlen($PlayerName)) . $PlayerName . int2bin($ClientVars['CountVars']) . $ClientVars['Vars']);

        }

        // Notify Other Clients of the join (MSGID 1 )
        /////////////////////////////////////////////
        foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {

            // If Users are not in the main channel, => continue
            if ($_AGK_subChannel[$PlayerChannel] != 0) {
                continue;
            }

            // Alert client
            //$AGKSocket->write($PlayerChannel, chr(1).chr(00).chr(00).chr(00));
            $AGKSocket->write($PlayerChannel, int2bin(1) . int2bin($newPlayerID) . int2bin(strlen($ClientName)) . $ClientName);

        }

        // Enregistrement du  nouveau joueur
        $_AGK_Players[$channel] = $ClientName;
        $_AGK_subChannel[$channel] = 0;

        if (SERVER_DEBUG) {
            writeLog("<= Player Joined : " . $ClientName);
        }

        onAGKClientJoin($newPlayerID);
        onAGKClientChangeChannel($channel + 2, 0, 0);

        return (1);
    }

//////////////////////////////////////////////////////////////////// COMMANDS ///////////////////////////////////////////////////////
    //while (strlen($buffer) > 0) {
    // Consommer le buffer !
    $AGKCommand = bin2int(substr($buffer, 0, 4));
    //$AGKCommand = $AGKCommand[1];

    //writeLog("--------\nAGK Command : " . $AGKCommand . "\n-------------");

    // New variables
    if ($AGKCommand == 2) {
        //    (2) (NB Variable) [(VariableName Length) (Variable Name) (reset = 0/1) (Integer =0 / Float = 1) (Value)]

        // DEBUG
        if (SERVER_DEBUG) {
            writeLog("-----------BEGIN Unit New Variable-----------");
            $tmpLog = "";
            for ($i = 0; $i <= strlen($buffer) - 1; $i++) {
                $tmpLog .= ord($buffer[$i]) . " ";
            }
            writeLog($tmpLog);
            writeLog("-----------END Unit New Variable-----------");
        }

        $bkpBuffer = $buffer;

        /// On stock les variables en local pour utilisation ultérieure.
        $Variable = array();

        $NBVariable = bin2int(substr($buffer, 4, 4));
        $NoVariable = 0;
        $buffer = substr($buffer, 8);
        while (strlen($buffer) > 0) {
            $NoVariable++;
            if ($NoVariable > $NBVariable) {
                break;
            }

            $VariableNameLength = bin2int(substr($buffer, 0, 4));

            //if (strlen($buffer) < (4 + $VariableNameLength + 4 + 4 + 4)) {
            //    writeLog("Message Breaks !!!");
            //}

            $Variable["Name"] = substr($buffer, 4, $VariableNameLength);
            $Variable["Reset"] = bin2int(substr($buffer, (4 + $VariableNameLength), 4)); // 0 : no reset / 1 : reset
            $Variable["Type"] = bin2int(substr($buffer, 4 + $VariableNameLength + 4, 4)); // 0 : integer / 1 : Float
            if ($Variable["Type"] == 0) {
                $Variable["Value"] = bin2uint(substr($buffer, 4 + $VariableNameLength + 4 + 4, 4));
            } elseif ($Variable["Type"] == 1) {
                $Variable["Value"] = bin2float(substr($buffer, 4 + $VariableNameLength + 4 + 4, 4));
            }
            $Variable["ServerValue"] = $Variable["Value"];

            $StopPropagation = false;
            onAGKClientUpdateVariable($channel + 2, $Variable["Name"], $Variable["Value"]);

            $_AGK_PlayersVariables[$channel][] = $Variable;

            if (SERVER_DEBUG) {
                writeLog("<= NewVariable (" . $_AGK_Players[$channel] . ") : " . $Variable['Name']);
            }

            if ($Variable["Name"] == "SERVER_CHANNEL" && $_AGK_subChannel[$channel] != $Variable["Value"]) {
                $previousChannel = $_AGK_subChannel[$channel];
                $_AGK_subChannel[$channel] = $Variable["Value"];
                _AGK_notifyNewChannelToUser($channel, $previousChannel, $_AGK_subChannel[$channel]);
            }

            $buffer = substr($buffer, 4 + $VariableNameLength + 4 + 4 + 4);

        }

        if (!$StopPropagation) {
            // On termine par transmettre la/les nouvelles variables aux autres clients
            foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {
                if ($PlayerChannel == $channel || $_AGK_subChannel[$channel] != $_AGK_subChannel[$PlayerChannel]) {
                    continue;
                }

                $AGKSocket->write($PlayerChannel, int2bin(2) . int2bin($channel + 2) . substr($bkpBuffer, 4, strlen($bkpBuffer) - 4 - strlen($buffer)));

            }
        }

        if (strlen($buffer) > 0) {
            // Si il reste du buffer, on continue le traitement
            _AGK_HandleReceivedData($channel, $buffer);
            return (1);
        }

        //  var_dump($_AGK_PlayersVariables);

    }

    if ($AGKCommand == 3) {
        //   (3) (NB Variable) [(IndexVariable) (Value)]

        // DEBUG
        if (SERVER_DEBUG) {
            writeLog("-----------BEGIN Unit Updated Variable-----------");
            $tmpLog = "";
            for ($i = 0; $i <= strlen($buffer) - 1; $i++) {
                $tmpLog .= ord($buffer[$i]) . " ";
            }
            writeLog($tmpLog);
            writeLog("-----------END Unit Updated Variable-----------");
        }

        $bkpBuffer = $buffer;

        // On met à jour les variables

        /// On stock les variables en local pour utilisation ultérieure.
        $Variable = array();

        $NBVariable = bin2int(substr($buffer, 4, 4));
        $NoVariable = 0;
        $buffer = substr($buffer, 8);
        while (strlen($buffer) > 0) {

            $NoVariable++;
            if ($NoVariable > $NBVariable) {
                break;
            }

            $VariableIndex = bin2int(substr($buffer, 0, 4));

            if ($_AGK_PlayersVariables[$channel][$VariableIndex]["Type"] == 0) {
                $_AGK_PlayersVariables[$channel][$VariableIndex]["Value"] = bin2uint(substr($buffer, 4, 4));
            } elseif ($_AGK_PlayersVariables[$channel][$VariableIndex]["Type"] == 1) {
                $_AGK_PlayersVariables[$channel][$VariableIndex]["Value"] = bin2float(substr($buffer, 4, 4));
            }
            $_AGK_PlayersVariables[$channel][$VariableIndex]["ServerValue"] = $_AGK_PlayersVariables[$channel][$VariableIndex]["Value"];
            $StopPropagation = false;
            onAGKClientUpdateVariable($channel + 2, $_AGK_PlayersVariables[$channel][$VariableIndex]["Name"], $_AGK_PlayersVariables[$channel][$VariableIndex]["Value"]);

            if (SERVER_DEBUG) {
                writeLog("<= UpdateVariable (" . $_AGK_Players[$channel] . ") : " . $_AGK_PlayersVariables[$channel][$VariableIndex]['Name']);
            }

            if ($_AGK_PlayersVariables[$channel][$VariableIndex]["Name"] == "SERVER_CHANNEL" && $_AGK_subChannel[$channel] != $_AGK_PlayersVariables[$channel][$VariableIndex]["Value"]) {

                $previousChannel = $_AGK_subChannel[$channel];
                $_AGK_subChannel[$channel] = $_AGK_PlayersVariables[$channel][$VariableIndex]["Value"];
                _AGK_notifyNewChannelToUser($channel, $previousChannel, $_AGK_subChannel[$channel]);
            }

            $buffer = substr($buffer, 8);

        }

        if (!$StopPropagation) {

            // On transmet la/les nouvelles variables aux autres clients
            foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {
                if ($PlayerChannel == $channel || $_AGK_subChannel[$channel] != $_AGK_subChannel[$PlayerChannel]) {
                    continue;
                }

                $AGKSocket->write($PlayerChannel, int2bin(3) . int2bin($channel + 2) . substr($bkpBuffer, 4, strlen($bkpBuffer) - 4 - strlen($buffer)));

            }
        }
        if (strlen($buffer) > 0) {
            // Si il reste du buffer, on continue le traitement
            _AGK_HandleReceivedData($channel, $buffer);
            return (1);
        }
        //  var_dump($_AGK_PlayersVariables);

    }

    /////// Network Message
    if ($AGKCommand == 5) {

        while (substr($buffer, 0, 4) == int2bin(5)) {
            //$DataChunk = substr( $DataChunk, 4 );

            $ClientID = bin2int(substr($buffer, 4, 4));
            $DestID = bin2int(substr($buffer, 8, 4));
            $MsgLength = bin2int(substr($buffer, 12, 4));

            $IncomingMessage = substr($buffer, 16);

            // DEBUG
            if (SERVER_DEBUG) {
                writeLog("-----------BEGIN Unit Message-----------");
                $tmpLog = "";
                for ($i = 0; $i <= strlen($IncomingMessage) - 1; $i++) {
                    $tmpLog .= ord($IncomingMessage[$i]) . " ";
                }
                writeLog($tmpLog);
                writeLog("-----------END Unit Message-----------");
            }

            // Callback d'evenement d'arrivée d'un message
            $StopPropagation = false;
            onAGKClientNetworkMessage($ClientID, $DestID, $IncomingMessage);

            if (!$StopPropagation) {
                //echo "forward";
                // Forward du message
                if ($DestID == 0) {
                    foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {

                        if ($PlayerChannel == $channel || $_AGK_subChannel[$channel] != $_AGK_subChannel[$PlayerChannel]) {
                            continue;
                        }
                        $AGKSocket->write($PlayerChannel, int2bin(5) . int2bin($ClientID) . substr($buffer, 12, 4 + $MsgLength));
                    }
                } else {
                    if ($DestID != 1) {
                        // Si le destinataire n'est pas le serveur
                        if ($_AGK_subChannel[$channel] == $_AGK_subChannel[$DestID - 2]) {
                            // Si le destinataire se trouve sur le meme serveur que l'expediteur
                            $AGKSocket->write($DestID - 2, int2bin(5) . int2bin($ClientID) . substr($buffer, 12, 4 + $MsgLength));
                        }
                    }

                }
            }

            //parseData($data = $IncomingMessage, false);

            $buffer = substr($buffer, 16 + $MsgLength);
        }
        if (strlen($buffer) > 0) {
            // Si il reste du buffer, on continue le traitement
            _AGK_HandleReceivedData($channel, $buffer);
            return (1);
        }

        //return (1);
    }

    /////// Check Connection / (KeepAlive) <= CMD 7

    if ($AGKCommand == 7) {

        if (SERVER_DEBUG) {
            //writeLog("<= PING REQUEST " . $_AGK_Players[$channel]);
            // Answer to client ( => CMD 6)
            //writeLog("=> PING RESPONSE");
        }

        $_AGK_Players_PingState[$channel] = time();

        $AGKSocket->write($channel, int2bin(6));

        $buffer = substr($buffer, 4);

        if (strlen($buffer) > 0) {
            // Si il reste du buffer, on continue le traitement
            _AGK_HandleReceivedData($channel, $buffer);
            return (1);
        }

        //return (1);
    }

    //}

}

function _AGK_notifyNewChannelToUser($channel, $oldChannelNumber, $newChannelNumber) {

    global $AGKSocket, $_AGK_Players, $_AGK_subChannel;

    //echo $_AGK_Players[$channel] . " is changeing from channel " . $oldChannelNumber . " to " . $newChannelNumber . PHP_EOL;

    // Notify the user of the virtual QUIT message of the user no longer in the same room

    foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {

        if ($PlayerChannel == $channel) {
            continue;
        }

        if ($_AGK_subChannel[$PlayerChannel] == $oldChannelNumber) {
            //Notify the user of the quit of others clients
            //echo "notify " . $PlayerName . " of the quitting of " . $_AGK_Players[$channel] . " on channel " . $oldChannelNumber . PHP_EOL;
            $AGKSocket->write($channel, int2bin(4) . int2bin($PlayerChannel + 2));
            //Notify others clients of the quit of the current client
            $AGKSocket->write($PlayerChannel, int2bin(4) . int2bin($channel + 2));

        }
    }

    // Notify the user of the virtual JOIN messages of the users in the same new room

    foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {

        if ($PlayerChannel == $channel) {
            continue;
        }

        if ($_AGK_subChannel[$PlayerChannel] == $newChannelNumber) {

            //echo "notify new arrivant " . $_AGK_Players[$channel] . "(" . $channel . ") of the virtual joining of " . $PlayerName . "(" . $PlayerChannel . ")" . PHP_EOL;
            // notify the current client of the join of the others already in the room
            $AGKSocket->write($channel, int2bin(1) . int2bin($PlayerChannel + 2) . int2bin(strlen($PlayerName)) . $PlayerName);

            // Boucle sur les variable du $PlayerChannel pour l'envoyer au nouveau venu
            _AGK_sendNetworkAllVariables($PlayerChannel, $channel);

            //echo "notify " . $PlayerName . "(" . $PlayerChannel . ") of the joiningggg of " . $_AGK_Players[$channel] . "(" . $channel . ")" . PHP_EOL;
            // notify others clients already in the room of the join of this users
            $AGKSocket->write($PlayerChannel, int2bin(1) . int2bin($channel + 2) . int2bin(strlen($_AGK_Players[$channel])) . $_AGK_Players[$channel]);

            // Boucle sur les variable du $channel pour l'envoyer aux users déjà en room
            _AGK_sendNetworkAllVariables($channel, $PlayerChannel);

            //$AGKSocket->write($PlayerChannel, int2bin(0));

            // Send Server Variable of the new channel to the new Client of this room
            _AGK_sendServerNetworkAllVariables($newChannelNumber, $channel);

        }
    }

    onAGKClientChangeChannel($channel + 2, $oldChannelNumber, $newChannelNumber);

}

function _AGK_sendNetworkAllVariables($fromChannel, $toChannel, $returnBinary = 0) {
    global $AGKSocket, $_AGK_PlayersVariables, $_AGK_Players;
    //echo "nb variables:" . count($_AGK_PlayersVariables[$fromChannel]) . PHP_EOL;

    if (count($_AGK_PlayersVariables[$fromChannel]) > 0) {
        $binHeadVarMsg = int2bin(2) . int2bin($fromChannel + 2) . int2bin(count($_AGK_PlayersVariables[$fromChannel]));
        $binVarMsg = "";
        foreach ($_AGK_PlayersVariables[$fromChannel] as $indexVariable => $Variable) {
            //echo "variable envoyée à " . $_AGK_Players[$toChannel] . ": " . $Variable['Name'] . PHP_EOL;
            if ($returnBinary == 0) {
                $binVarMsg .= int2bin(strlen($Variable['Name'])) . $Variable['Name'] . int2bin($Variable['Reset']) . int2bin($Variable['Type']);
            } else {
                $binVarMsg .= int2bin(strlen($Variable['Name'])) . $Variable['Name'] . int2bin($Variable['Type']) . int2bin($Variable['Reset']);
            }

            if ($Variable["Type"] == 0) {
                $binVarMsg .= uint2bin($Variable["Value"]);
            } elseif ($Variable["Type"] == 1) {
                $binVarMsg .= float2bin($Variable["Value"]);
            }
        }
        if ($returnBinary == 0) {
            $AGKSocket->write($toChannel, $binHeadVarMsg . $binVarMsg);
        } else {
            return array("CountVars" => count($_AGK_PlayersVariables[$fromChannel]), "Vars" => $binVarMsg);
        }

    }
}

function _AGK_sendServerNetworkAllVariables($ChannelNumber, $toChannel, $returnBinary = 0) {
    global $AGKSocket, $_AGK_ServerVariables, $_AGK_Players;
    //echo "nb variables:" . count($_AGK_PlayersVariables[$fromChannel]) . PHP_EOL;

    if (!empty($_AGK_ServerVariables[$ChannelNumber]) > 0) {
        $binHeadVarMsg = int2bin(2) . int2bin(1) . int2bin(count($_AGK_ServerVariables[$ChannelNumber]));
        $binVarMsg = "";
        foreach ($_AGK_ServerVariables[$ChannelNumber] as $indexVariable => $Variable) {
            //echo "variable envoyée à " . $_AGK_Players[$toChannel] . ": " . $Variable['Name'] . PHP_EOL;
            if ($returnBinary == 0) {
                $binVarMsg .= int2bin(strlen($Variable['Name'])) . $Variable['Name'] . int2bin($Variable['Reset']) . int2bin($Variable['Type']);
            } else {
                $binVarMsg .= int2bin(strlen($Variable['Name'])) . $Variable['Name'] . int2bin($Variable['Type']) . int2bin($Variable['Reset']);
            }

            if ($Variable["Type"] == 0) {
                $binVarMsg .= uint2bin($Variable["Value"]);
            } elseif ($Variable["Type"] == 1) {
                $binVarMsg .= float2bin($Variable["Value"]);
            }
        }
        if ($returnBinary == 0) {
            $AGKSocket->write($toChannel, $binHeadVarMsg . $binVarMsg);
        } else {
            return array("CountVars" => count($_AGK_ServerVariables[$ChannelNumber]), "Vars" => $binVarMsg);
        }
    }
}

/*
tick's event
 */
$LastClientsPingCheck = time();
function _agk_tick() {
    global $AGKSocket;
    global $LastClientsPingCheck;
    global $_AGK_Players_PingState;
    global $_AGK_Players;
    // Event after check buffer
    //return 1;
    //if (isset($_AGK_Players[$channel])) // une fois que le player est authentifié
    //{
    //@SendString($channel,-1,"SUPERMEGATROP COOL");
    //}
    if (time() - $LastClientsPingCheck > 15) {
        // Check Clients every 15 seconds
        //writeLog("Check Clients Ping");
        $LastClientsPingCheck = time();
        foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {
            if ($LastClientsPingCheck - $_AGK_Players_PingState[$PlayerChannel] > MAX_PING_TIMEOUT_SECONDS) {
                $AGKSocket->close($PlayerChannel, "Ping Timeout !");

            }
        }

    }

    //system("clear");
    //var_dump($_AGK_Players);
    //echo time() . PHP_EOL;
    onAGKServerRefreshTimer();
}

/*
Connection lost's event
 */

function _agk_connection_lost($channel, $error, $reason) {
    global $_AGK_Players, $AGKSocket, $_AGK_PlayersVariables, $_AGK_subChannel, $_AGK_PlayersIP;
    if (SERVER_DEBUG) {
        writeLog("Connection lost (" . $_AGK_Players[$channel] . "): $error / " . $reason);
    }

    onAGKClientQuit($channel + 2, $reason);
    unset($_AGK_Players[$channel]);
    unset($_AGK_PlayersVariables[$channel]);
    unset($_AGK_PlayersIP[$channel]);
    unset($_AGK_subChannel[$channel]);

    // Notify Other Clients of the quit (MSGID 4 )
    /////////////////////////////////////////////
    foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {
        //echo "alerting $PlayerName";
        // Alert client
        // Network Command 4
        $AGKSocket->write($PlayerChannel, int2bin(4) . int2bin($channel + 2));
    }

    //_agk_tick($channel);

}

/*
events declaration
 */
/*
$AGKSocket->set_handler(SOCKET_EVENT_CREATED, "_agk_new_connection");
$AGKSocket->set_handler(SOCKET_EVENT_NEWDATA, "_agk_data_received");
$AGKSocket->set_handler(SOCKET_EVENT_EXPIREY, "_agk_connection_lost");
$AGKSocket->set_handler(SOCKET_EVENT_TICKERS, "_agk_tick");
 */
$ARGS = parseArgs($argv);

//var_dump($ARGS);
//exit;

if (empty(@$ARGS)) // Direct mode (no daemon)
{
    echo "\n****************\n** AGK Server **\n****************\n\nArguments are : start, stop, direct\n---------------\n\n" .
    basename($_SERVER["argv"][0]) . " start  " . chr(9) . "Start the AGK Server Daemon\n" .
    basename($_SERVER["argv"][0]) . " stop   " . chr(9) . "Stop the AGK Server Daemon\n" .
    basename($_SERVER["argv"][0]) . " debug  " . chr(9) . "Start the AGK Server in \"Direct / Debug Mode\" (no Daemon => CTRL+C to stop)\n";

    exit;
}

if (@$ARGS[0] == "stop") // (stop daemon)
{
    //echo "---------------------------------------------" . PHP_EOL . "| AGK Server \"" . SERVER_NAME . "\" : Started." . PHP_EOL . "| Listening for connections on port " . SERVER_PORT . "..." . PHP_EOL . "---------------------------------------------" . PHP_EOL;
    //$AGKSocket->start();
    exec("killall -9 " . basename($_SERVER["argv"][0]), $output, $return);
    exit(0);

}

//echo basename($_SERVER["argv"][0]);
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    exec("pgrep -f " . basename($_SERVER["argv"][0]), $output, $return);
//var_dump($output);

    if (count($output) > 2) {
        die("Process " . basename($_SERVER["argv"][0]) . " (" . $output[0] . ") is already running\n");
    }
}

if (@$ARGS[0] == "debug") // Direct mode (no daemon)
{
    error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE | ~E_STRICT);
    ini_set('display_errors', true);

    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system("clear");
    }

    writeLog("------------------------------------------------------------");
    writeLog("| AGK Server \"" . SERVER_NAME . "\" : Started.");
    writeLog("| Listening for connections on port " . SERVER_PORT . "...");
    writeLog("| Global Server Refresh Interval : " . SERVER_REFRESH_INTERVAL . "ns ...");
    if (defined("NETGAMEPLUGIN_WORLDSTATE_INTERVAL")) {
        writeLog("| NetGamePlugin WorldState Broadcast Interval : " . NETGAMEPLUGIN_WORLDSTATE_INTERVAL . "ns ...");
    }
    writeLog("------------------------------------------------------------");
    $AGKSocket->start();
    exit(0);

}

function writeLog($string) {
    global $ARGS, $myDaemon;
    if (@$ARGS[0] == "debug") // Direct mode (no daemon)
    {
        echo date("d/m/Y H:i:s") . " -> " . $string . PHP_EOL;
    } else {
        $myDaemon->writeLog($string);
    }
}

///////////////////////  DAEMON ///////////////////////////////////

function sig_handler($signal) {
    global $myDaemon, $AGKSocket;

    switch ($signal) {
    case SIGTERM:
    case SIGHKILL:

        $AGKSocket->closeAll();
        $myDaemon->writeLog("ShutDown asked");
        $myDaemon->shutdown();
        exit(0);

        break;

    case SIGHUP:
        break;
    }
}

function start() {
    global $AGKSocket, $myDaemon;
    $myDaemon->writeLog("AGK Server \"" . SERVER_NAME . "\" : Started.");
    $myDaemon->writeLog("Listening for connections on port " . SERVER_PORT . "...");
    $myDaemon->writeLog("Refresh interval : " . SERVER_REFRESH_INTERVAL . "ns ...");
    if (defined("NETGAMEPLUGIN_WORLDSTATE_INTERVAL")) {
        writeLog("NetGamePlugin WorldState Broadcast Interval : " . NETGAMEPLUGIN_WORLDSTATE_INTERVAL . "ns ...");
    }
    $AGKSocket->start();
}
if (@$ARGS[0] == "start") // Direct mode (no daemon)
{
    error_reporting(0);
    $myDaemon = new clsDaemonize(); // Creates an instance of clsDaemonize
    $myDaemon->setup_sighandler(SIGTERM, "sig_handler"); // Sets various signal handlers
    $myDaemon->setup_sighandler(SIGHUP, "sig_handler");
    //$myDaemon->setup_sighandler(SIGHKILL, "sig_handler");
    $myDaemon->fork("start");
}
//////////////////////////////////////////////////////////////////////////////

/////////////////////////// INTERNAL LIBRARIES / FUNCTIONS ////////////////////////////////////

function bin2int($data) {
    $value = unpack("i", ($data));
    $value = $value[1];
    return $value;
}
function bin2uint($data) {
    $value = unpack("l", ($data));
    $value = $value[1];
    return $value;
}
function bin2float($data) {
    $value = unpack("f", ($data));
    $value = $value[1];
    return $value;
}

function int2bin($data) {
    return pack("i", ($data));
}
function uint2bin($data) {
    return pack("l", ($data));
}
function float2bin($data) {
    return pack("f", ($data));
}

//////////////////////////////// SERVER LOGIC FUNCTIONS ////////////////////////////////////////

function GetChannelClientsCount($ChannelNumber) {
    global $_AGK_subChannel;
    $ChannelsCount = array_count_values($_AGK_subChannel);

    if (!isset($ChannelsCount[$ChannelNumber])) {
        return 0;
    } else {
        return $ChannelsCount[$ChannelNumber] + 1; // + 1 for Server
    }

}

function GetChannelsList() {
    global $_AGK_subChannel;

    $channelsList = array();

    foreach ($_AGK_subChannel as $PlayerChannel => $ChannelNb) {
        if (!in_array($ChannelNb, $channelsList)) {
            $channelsList[] = $ChannelNb;
        }

    }

    return $channelsList;
}

function GetChannelClientsList($ChannelNumber) {
    global $_AGK_subChannel, $_AGK_Players;

    $Member = array();
    $MembersList = array();

    if ($ChannelNumber == -1) {
        $channelServer = 0;
    } else {
        $channelServer = $ChannelNumber;
    }

    $MembersList[] = array("ID" => 1, "Name" => SERVER_NAME, "ChannelNumber" => $channelServer);

    foreach ($_AGK_subChannel as $PlayerChannel => $ChannelNb) {
        if ($ChannelNb == $ChannelNumber || $ChannelNumber == -1) {
            $Member["ID"] = $PlayerChannel + 2;
            $Member["Name"] = $_AGK_Players[$PlayerChannel];
            $Member["ChannelNumber"] = $ChannelNb;

            $MembersList[] = $Member;
        }
    }
    return $MembersList;
}

function GetClientChannelNumber($PlayerID) {
    global $_AGK_subChannel;
    return @$_AGK_subChannel[$PlayerID - 2];
}
function GetClientIP($PlayerID) {

    global $_AGK_PlayersIP;
    return @$_AGK_PlayersIP[$PlayerID - 2];
}

function GetClientName($PlayerID) {
    if ($PlayerID == 0) {
        return "ALL PLAYERS";
    } else {
        global $_AGK_Players;
        return @$_AGK_Players[$PlayerID - 2];
    }
}

function forceChannelJoin($PlayerID, $ChannelNumber) {
    global $_AGK_subChannel;
    $previousChannel = $_AGK_subChannel[$PlayerID - 2];
    $_AGK_subChannel[$PlayerID - 2] = $ChannelNumber;
    _AGK_notifyNewChannelToUser($PlayerID - 2, $previousChannel, $ChannelNumber);

}

function GetNetworkClientInteger($PlayerID, $VariableName) {
    return GetNetworkClientVariable($PlayerID, $VariableName);
}

function GetNetworkClientFloat($PlayerID, $VariableName) {
    return GetNetworkClientVariable($PlayerID, $VariableName);
}

function GetNetworkClientVariable($PlayerID, $VariableName) {
    global $_AGK_PlayersVariables;

    if (!isset($_AGK_PlayersVariables[$PlayerID - 2])) {
        return 0;
    }

    foreach ($_AGK_PlayersVariables[$PlayerID - 2] as &$Variable) {
        if ($Variable["Name"] == $VariableName) {
            $ValueToReturn = $Variable["ServerValue"];
            if ($Variable["Reset"] == 1 && $Variable["ServerValue"] != 0) {
                $Variable["ServerValue"] = 0;
            }

            return $ValueToReturn;
        }
    }

    return 0;
}

function GetNetworkMessageString(&$IncomingMessage) {

    $stringLength = unpack("i", substr($IncomingMessage, 0, 4));
    $String = substr($IncomingMessage, 4, $stringLength[1]);
    $IncomingMessage = substr($IncomingMessage, 4 + $stringLength[1]);
    return $String;

}

function GetNetworkMessageInteger(&$IncomingMessage) {

    $Integer = unpack("l", substr($IncomingMessage, 0, 4));
    $Integer = (int) $Integer[1];
    $IncomingMessage = substr($IncomingMessage, 4);
    return $Integer;
}

function GetNetworkMessageFloat(&$IncomingMessage) {

    $Float = unpack("f", substr($IncomingMessage, 0, 4));
    $Float = (float) $Float[1];
    $IncomingMessage = substr($IncomingMessage, 4);
    return $Float;
}

function SetNetworkLocalInteger($ChannelNumber, $VariableName, $Value, $ResetMode = 0) {
    _SetServerNetworkLocalVariable($ChannelNumber, $VariableName, $Value, $ResetMode, 0);
}

function SetNetworkLocalFloat($ChannelNumber, $VariableName, $Value, $ResetMode = 0) {
    _SetServerNetworkLocalVariable($ChannelNumber, $VariableName, $Value, $ResetMode, 1);
}

function StopPropagation() {
    global $StopPropagation;
    $StopPropagation = true;
}

///// Internal

function _SetServerNetworkLocalVariable($ChannelNumber, $VariableName, $Value, $ResetMode, $Type) {
    global $AGKSocket, $_AGK_Players, $_AGK_ServerVariables;

    $ChannelServerVariables = $_AGK_ServerVariables[$ChannelNumber];
    $VariableAlreadyExists = false;
    for ($i = 1; $i <= count($ChannelServerVariables); $i++) {
        if ($ChannelServerVariables["Name"] == $VariableName) {
            // Variable already exists
            $VariableAlreadyExists = true;

            $ChannelServerVariables["Value"] = $Value;
            // Notify Update to all users in room

            //   (3) (sender) (NB Variable) [(IndexVariable) (Value)]
            foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {

                if ($_AGK_subChannel[$PlayerChannel] != $ChannelNumber) {
                    continue;
                }

                // Network Command 3 > update variable
                // (3) (sender) (NB Variable) [(IndexVariable) (Value)]
                if ($Type == 0) {
                    $AGKSocket->write($PlayerChannel, int2bin(3) . int2bin(1) . int2bin(1) . int2bin($i) . uint2bin($ChannelServerVariables["Value"]));
                }

                if ($Type == 1) {
                    $AGKSocket->write($PlayerChannel, int2bin(3) . int2bin(1) . int2bin(1) . int2bin($i) . float2bin($ChannelServerVariables["Value"]));
                }

            }

            break;
        }
    }

    if (!$VariableAlreadyExists) {
        // Variable not exists => create it !
        $Variable['Name'] = $VariableName;
        $Variable['Value'] = $Value;
        $Variable['Type'] = $Type; // Integer
        $Variable['Reset'] = $ResetMode; // 0 = noReset / 1 = reset
        $_AGK_ServerVariables[$ChannelNumber][] = $Variable;

        foreach ($_AGK_Players as $PlayerChannel => $PlayerName) {

            if ($_AGK_subChannel[$PlayerChannel] != $ChannelNumber) {
                continue;
            }

            // Network Command 2 > create variable
            // (2) (NB Variable) [(VariableName Length) (Variable Name) (reset = 0/1) (Integer =0 / Float = 1) (Value)]
            if ($Type == 0) {
                $AGKSocket->write($PlayerChannel, int2bin(2) . int2bin(1) . int2bin(1) . int2bin(strlen($VariableName)) . $VariableName . $ResetMode . int2bin(0) . uint2bin($Value));
            }

            if ($Type == 1) {
                $AGKSocket->write($PlayerChannel, int2bin(2) . int2bin(1) . int2bin(1) . int2bin(strlen($VariableName)) . $VariableName . $ResetMode . int2bin(1) . float2bin($Value));
            }

        }

    }

}

/////////////// CLASS NETWORK MESSAGE ///////////////////////////////////////////////

class NetworkMessage {

    private $messageData = array();

    // function __construct() {

    // }

    public function AddNetworkMessageString($stringToAdd) {
        $this->messageData[] = int2bin(strlen($stringToAdd)) . $stringToAdd;
        return $this;
    }

    public function AddNetworkMessageInteger($integerToAdd) {
        $this->messageData[] = uint2bin($integerToAdd);
        return $this;
    }

    public function AddNetworkMessageFloat($floatToAdd) {
        $this->messageData[] = float2bin($floatToAdd);
        return $this;
    }
    // public function Clear() {
    //     $this->messageData = array();
    //     return $this;
    // }
    public function Send($DestID = 0) {
        global $AGKSocket;
        global $_AGK_Players;

        $joinedDatas = join($this->messageData);
        $messageGlobalData = int2bin(5) . int2bin($DestID) . int2bin(strlen($joinedDatas)) . $joinedDatas;

        if ($DestID == 0) // Broadcast
        {

            foreach ($_AGK_Players as $channel => $PlayerName) {
                $AGKSocket->write($channel, $messageGlobalData);
            }

        } else {
            if (isset($_AGK_Players[$DestID - 2])) {
                // Alert client
                $AGKSocket->write($DestID - 2, $messageGlobalData);
            }
        }
        $this->messageData = array();
        return $this;
    }
    public function SendChannel($ChannelNumber = 0) {
        global $AGKSocket;
        global $_AGK_Players;
        global $_AGK_subChannel;

        $joinedDatas = join($this->messageData);
        $messageGlobalData = int2bin(5) . int2bin(0) . int2bin(strlen($joinedDatas)) . $joinedDatas;

        foreach ($_AGK_Players as $channel => $PlayerName) {
            if ($_AGK_subChannel[$channel] != $ChannelNumber) {
                continue;
            }

            $AGKSocket->write($channel, $messageGlobalData);
        }

        $this->messageData = array();
        return $this;
    }

}

/***************************************************************************
 *  Original floSocket copyright (C) 2005 by Joshua Hatfield.              *
 *                                                                         *
 *  In order to use any part of this floSocket Class, you must comply with *
 *  the license in 'license.doc'.  In particular, you may not remove this  *
 *  copyright notice.                                                      *
 *                                                                         *
 *  Much time and thought has gone into this software and you are          *
 *  benefitting.  We hope that you share your changes too.  What goes      *
 *  around, comes around.                                                  *
 ***************************************************************************

 ***************************************************************************************************
 ***************************************************************************************************
 *************                              ********************************************************
 *************                              ********************************************************
 *************        ******************************************************************************
 *************        ******************************************************************************
 *************        ***************************   *********************                      *****
 *************        ***************************   *********************   ****************   *****
 *************                      *************   *********************   ****************   *****
 *************                      *************   *********************   ****************   *****
 *************        ***************************   *********************   ****************   *****
 *************        ***************************   *********************   ****************   *****
 *************        ***************************   *********************   ****************   *****
 *************        ***************************   *********************   ****************   *****
 *************        ***************************   *********************   ****************   *****
 *************        ***************************   *********************   ****************   *****
 *************        ***************************   *********************   ****************   *****
 *************        ***************************   *********************   ****************   *****
 *************        ***************************                     ***   ****************   *****
 *************        ***************************                     ***                      *****
 ***************************************************************************************************

- Product name: floSocket
- Author: Joshua Hatfield (flobi@flobi.com)
- Release Version: 1.0.1
- Release Date: 2005-07-22

Update 1.0.1: 2005-07-22
New Features
Added event SOCKET_EVENT_TICKERS
 * Fires event after pushing buffer per channel.
Update to floSocket($address = false, $port = "9999", $tick = 250000)
 * Added $tick paramater.  Specifies how long to wait between buffer checks.

Bug Fixes
Moved sleeping to inside the loop where it is actually useful.

Changes
Default tick time changed to .25 seconds from 100 miliseconds or 0 if you
count the fact that the sleeping wasn't actually in the loop.

Original Release 1.0.0: 2005-07-01

Documentation:
First of all, please note that you must have the php installation configured
with --enable-sockets.  If you are using this with clsDaemonize, you must also
have set --enable-pcntl, though that wasn't in the clsDaemonize documentation
at the time of this release.  This version has been written to be compatible
with PHP 5.0 and later.

This class is meant to emulate a multi-thread TCP TEXT server.
As far as I'm concerned, there are only three events:

 */
define('SOCKET_EVENT_CREATED', -1); // Fires on new connection before any data
// is received.
define('SOCKET_EVENT_NEWDATA', 1); // Fires on arrival of data, returning
// buffer, 1 byte at a time.
define('SOCKET_EVENT_EXPIREY', 0); // Fires on socket closure after flushing
// data.
define('SOCKET_EVENT_TICKERS', 2); // Fires after every buffer check also
// after flushing buffer.
/*

Assuming you create the object as such:
$AGKSocket = new floSocket($address,$port);

You can set event handlers using
$AGKSocket->set_handler(SOCKET_EVENT_CREATED, "fun_new_connection");
$AGKSocket->set_handler(SOCKET_EVENT_NEWDATA, "fun_data_received");
$AGKSocket->set_handler(SOCKET_EVENT_EXPIREY, "fun_connection_lost");
$AGKSocket->set_handler(SOCKET_EVENT_TICKERS, "fun_tick");

And it will execute fun_new_connection(), fun_data_received() and
fun_connection_lost() respectively upon event firing.  If the event for
SOCKET_EVENT_NEWDATA is not defined, all data will be echoed.  Format for these
functions MUST coincide with these definitions:

fun_new_connection($channel) {}
fun_data_received($channel, $buffer) {}
fun_connection_lost($channel, $error) {}
fun_tick($channel) {}

NOTE: These are not actual functions, they are examples of how event triggered
functions should be formatted.  $channel is channel index as integer, not a
valid php socket resource.

The functions for use in this class are as follows:

floSocket($address = false, $port = "9999")
- Initialization.  If $address is omited, it will listen on all
frequencies (ip addresses).

start()
- Starts the listener based on the initialization information.

stop($disconnect_message = "Server shutdown.", $error = false)
- Cleanly disconnects everyone from the server triggering any
buffer flushing events and connection closure events that
need to be triggered.

set_handler($handler_id, $handler_function)
- Set the functions that process events as described above.

write($channel, $text)
- Send text to a channel.

close($channel, $disconnect_message = "", $error = false)
- Close a channel sending the disconnect message and returning
the error message if provided.

close_all($disconnect_message, $error = false)
- Close each connection just like stop() except doesn't stop the
listener.

get_remote_address($channel)
- Simple enough, returns the address.

get_remote_port($channel)
- Return the remote port.

get_socket_resource($channel)
- Returns the actual socket resource for the channel because the
$channel variable is just an integer floSocket uses to track
socket referrences.

set_echo($value = true)
- Tell the server whether or not to auto-echo the received
buffer back to the peer.  If the SOCKET_EVENT_NEWDATA event
is not set, there is nothing to do with the data so echo is
automatically turned ON.  To turn it off, you must specify a
SOCKET_EVENT_NEWDATA event.

function set_timeout($seconds = 60)
- Set timeout in seconds.  By default there is no timeout,
however, calling set_timeout without any value sets it to one
minute.

 */

class floSocket {
    // Set up class variables.
    var $socketAddress;
    var $socketPort;
    var $sockets = array();
    var $event_handler = array();
    var $echoback = false;
    var $listening = false;
    var $failsafe_timeout = -1;
    var $tick_length;

    // Class initialization.
    function floSocket($address = false, $port = "9999", $tick = 250000) {
        $this->socketAddress = $address;
        $this->socketPort = $port;
        $this->tick_length = $tick;
    }

    // Begin listening
    function start() {
        $mastersocket = false;
        // Check to see if already running listener.
        if (!$this->listening) {
            // Creating master socket...
            if ($this->socketAddress === false) {
                $mastersocket = socket_create_listen($this->socketPort);
            } else {
                $mastersocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                socket_connect($mastersocket, $dest, $port);
            }
            if ($mastersocket < 0) {
                return false;
            }
            if (@socket_bind($mastersocket, $this->socketAddress, $this->socketPort) < 0) {
                return false;
            }
            if (socket_listen($mastersocket) < 0) {
                return false;
            }
            if (!socket_set_option($mastersocket, SOL_SOCKET, SO_REUSEADDR, 1)) {
                return false;
            }
            if (!socket_set_nonblock($mastersocket)) {
                return false;
            }
            // End master socket creation.

            //Begin listening.
            $this->listening = true;
            $failsafe = 0;
            while ($this->listening && ($this->failsafe_timeout < 0 || $failsafe++ <= $this->failsafe_timeout)) {
                // Check for new socket arrival.
                if ($newsocket = @socket_accept($mastersocket)) {
                    // New connection, activate event and add channel.
                    socket_set_nonblock($newsocket);
                    $channel = $this->getFreeChannel();
//                  echo "Channel attribue : ".$channel."\n";
                    $this->sockets[$channel] = $newsocket;
                    //$channel = array_pop(array_keys($this->sockets));
                    //$channel=$this->getFreeChannel();

                    // $proc_event = $this->event_handler[SOCKET_EVENT_CREATED];
                    // if ($proc_event) {
                    //     $proc_event($channel, @$error);
                    // }
                    _agk_new_connection($channel, @$error);

                    $newsocket = false;
                }
                // Check for new data on existing (and new) sockets.
                foreach ($this->sockets as $channel => $socket) {
                    while ($status = @socket_recv($socket, $buffer, 4096, 0)) {
                        // Data received, activate event.
                        // $proc_event = $this->event_handler[SOCKET_EVENT_NEWDATA];
                        // if ($proc_event) {
                        //     $proc_event($channel, $buffer);
                        // }
                        _agk_data_received($channel, $buffer);

                        if ($this->echoback) {
                            $this->write($channel, $buffer);
                        }
                    }

                    if ($status === 0) {
                        // Socket error.  Remote disconnection.
                        $this->close($channel, "Remote connection has closed.");
                    }
                }

                // Handle the Tick Timer
                // $proc_event = $this->event_handler[SOCKET_EVENT_TICKERS];
                // if ($proc_event) {
                //     $proc_event();
                // }
                _agk_tick();
                // Sleep for a tad so other processes can get their proc time.
                usleep($this->tick_length);
            } // end while
        } else {
            // Return false if already running.
            return false;
        }
        // Shutdown listener as stop() has been detected or failsafe
        // timeout expired.
        socket_shutdown($mastersocket);
        socket_close($mastersocket);
        return true;
    }

    // Stop listener cleanly.
    function stop($disconnect_message = "Server shutdown.", $error = false) {
        $this->close_all($disconnect_message, $error = false);
        $this->listening = false;
    }

    // Set event handlers
    function set_handler($handler_id, $handler_function) {
        $this->event_handler[$handler_id] = $handler_function;
    }

    // Send data to active channel.
    function write($channel, $text) {
        if (isset($this->sockets[$channel]) && @socket_write($this->sockets[$channel], $text, strlen($text)) < 0) {
            // Close channel and return false on failure.
            $this->close($this->sockets[$channel], "", "Socket error while sending...");
            return false;
        }
        return true;
    }

    // Close channel.
    function close($channel, $disconnect_message = "", $error = false) {
        if (isset($this->sockets[$channel])) {
            while ($status = @socket_recv($socket, $buffer, 1, 0)) {
                // Activate event for any leftover buffer data.
                // $proc_event = $this->event_handler[SOCKET_EVENT_NEWDATA];
                // if ($proc_event) {
                //     $proc_event($channel, $buffer);
                // }
                _agk_data_received($channel, $buffer);

                if ($this->echoback) {
                    $this->write($channel, $buffer);
                }
            }
            // Send disconnect message.
            @socket_write($this->sockets[$channel], $disconnect_message, strlen($disconnect_message));

            // Activate closure event.
            // $proc_event = $this->event_handler[SOCKET_EVENT_EXPIREY];
            // if ($proc_event) {
            //     $proc_event($channel, $error);
            // }
            _agk_connection_lost($channel, $error, $disconnect_message);

            // Shutdown socket.
            @socket_shutdown($this->sockets[$channel]);
            @socket_close($this->sockets[$channel]);

            // Clear channel variable.
            unset($this->sockets[$channel]);
        }
        return $error;
    }

    function getFreeChannel() {

        if (count($this->sockets) > 0) {
            $tmpKeysSocket = array_keys($this->sockets);
            $NewSocket = array_pop($tmpKeysSocket) + 1;
        } else {
            $NewSocket = 0;
        }

        for ($i = 0; $i < $NewSocket - 1; $i++) {
            if (!isset($this->sockets[$i])) {
                return $i;
            }

        }
        return $NewSocket;
    }

    // Systematically close all conenctions.
    function close_all($disconnect_message, $error = false) {
        foreach ($this->sockets as $key => $value) {
            $this->close($key, $disconnect_message, $error = false);
        }
    }

    // Get the remote IP address.
    function get_remote_address($channel) {
        if (isset($this->sockets[$channel])) {
            socket_getpeername($this->sockets[$channel], $remoteaddress, $remoteport);
            return $remoteaddress;
        }
    }

    // Get the remote IP port.
    function get_remote_port($channel) {
        if (isset($this->sockets[$channel])) {
            socket_getpeername($this->sockets[$channel], $remoteaddress, $remoteport);
            return $remoteport;
        }
    }

    // Get php socket resource (for those who like more control).
    function get_socket_resource($channel) {
        return $this->sockets[$channel];
    }

    // Set echoback.
    function set_echo($value = true) {
        $this->echoback = $value;
    }

    // Set timeout.
    function set_timeout($seconds = 60) {
        $this->failsafe_timeout = $seconds * 1000000 / $this->tick_length;
    }
}

/*
 ***************************************************************************************************
 ***************************************************************************************************
 ***************************************************************************************************
 ***************************************************************************************************
 ***************************************************************************************************
 ***************************************************************************************************
 ***************************************************************************************************
 ***************************************************************************************************
 ***************************************************************************************************
 ***************************************************************************************************
 ***************************************************************************************************
 ***************************************************************************************************

Class Name    : clsDamonize
Author        : Daniel Marjos
Comments    : clsDaemonize is a class intended to automate the creation of system daemons using PHP.

 */

declare (ticks = 1);
class clsDaemonize {

    var $logFP; // Log file File Pointer
    var $sdProcess = ""; // Name of the Shutdown Process Call Back
    //var $pid_file; // Process ID file of the running daemon

    /*
    Function clsDaemonize
    Constructor to the class.
    This takes an optional parameter, which is the log file to be used by the class. If not provided,
    the log file will be /var/log/daemonname.log, being daemonname the actual filename for the daemon
     */
    function clsDaemonize($log_file = "") {

        $deamonname = basename($_SERVER["argv"][0]);
        //$this->pid_file = "/var/run/$deamonname.pid";
        // if (file_exists($this->pid_file)) {
        //     $pid = `cat $this->pid_file`;
        //     $pid = chop($pid);
        //     die("Daemon $deamonname ALREADY running ($pid)\n");
        // }
        if (empty($log_file)) {
            $log_file = "$deamonname.log";
        }

        $this->logFP = fopen($log_file, "a");
        $this->writeLog("Start daemon ($deamonname)");

    }

    /*
    Function writeLog
    Logging wrapper for the daemon
    This function adds a text line to the daemon's log, prefixing with date and time
     */
    function writeLog($logMSG) {
        fputs($this->logFP, date("d/m/Y H:i:s") . " -> $logMSG\n");
    }

    /*
    function setShutdownProc
    Sets the function callback for shutting down the daemon
     */
    function setShutdownProc($sdProc) {

        $this->sdProcess = $sdProc;

    }

    /*
    function shutdown
    Closes gracefully the daemon, removes pid file, closes log file and calls callback process.
     */
    function shutdown() {

        if (!empty($this->sdProc)) {
            $shutmeDown = $this->sdProc;
            $shutmeDown();
        }
        $this->writeLog("Shutdown daemon");
        fclose($this->logFP);
        //unlink($this->pid_file);
    }

    /*
    function fork
    Actual Daemonization process.
    Takes only one parameter, which will be the code to be executed on succesful fork.
     */
    function fork($child_proc) {

        $newpid = pcntl_fork();
        if ($newpid == -1) {
            $this->writeLog("Couldn't fork porcess");
            $this->shutdown();
            die("\n");
        } elseif ($newpid) {
            //fputs(fopen($this->pid_file, "w"), "$newpid\n");
            exit();
        }

        if (!posix_setsid()) {
            $this->writeLog("Couldn't dettach from terminal!");
            $this->shutdown();
            die("\n");
        }

        if (!empty($child_proc)) {
            while (true) {
                $child_proc();
            }
        }

    }

    /*
    function setup_sighandler
    Signal Handler callback function.
    Sets the function to be called when SIGNAL is received by daemon.
     */
    function setup_sighandler($signal, $function) {

        pcntl_signal($signal, $function);

    }

}

function parseArgs($argv) {
    array_shift($argv);
    $out = array();
    foreach ($argv as $arg) {
        if (substr($arg, 0, 2) == '--') {
            $eqPos = strpos($arg, '=');
            if ($eqPos === false) {
                $key = substr($arg, 2);
                $out[$key] = isset($out[$key]) ? $out[$key] : true;
            } else {
                $key = substr($arg, 2, $eqPos - 2);
                $out[$key] = substr($arg, $eqPos + 1);
            }
        } else if (substr($arg, 0, 1) == '-') {
            if (substr($arg, 2, 1) == '=') {
                $key = substr($arg, 1, 1);
                $out[$key] = substr($arg, 3);
            } else {
                $chars = str_split(substr($arg, 1));
                foreach ($chars as $char) {
                    $key = $char;
                    $out[$key] = isset($out[$key]) ? $out[$key] : true;
                }
            }
        } else {
            $out[] = $arg;
        }
    }
    return $out;
}

///////////////////////////////////////////// END CLASS
