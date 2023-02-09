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



/*****************************************************************************
 ******************** YOU SHLOUD NOT MODIFY CODE UNDER THIS COMMENT **********
 *****************************************************************************/

/************************ CONSTANTS **************************/

#constant MAX_CLIENTS 1000
#constant NGP_SLOT_SIZE 12

#constant POS_X 1
#constant POS_Y 2
#constant POS_Z 3
#constant ANG_X 4
#constant ANG_Y 5
#constant ANG_Z 6
#constant POS_X2 7
#constant POS_Y2 8
#constant POS_Z2 9
#constant ANG_X2 10
#constant ANG_Y2 11
#constant ANG_Z2 12

/************************ TYPES **************************/

type NGP_Input
	SequenceNumber as integer
	Slot as integer
	Velocity as float
	Speed as float
endtype

type NGP_Slot
		Slot as float[NGP_SLOT_SIZE]
endtype

type NGP_ClientDataBackup
		Connected as integer
		QueueSlot as NGP_Slot[]
		QueueMS as integer[]
		QueueLocalMS as integer[]
		QueueInProgress as integer[]
endtype

/************************ GLOBAL VARS **************************/

global NGP_connectTime as float
global NGP_NetworkState as integer
global NGP_clientNum as integer

global NGP_PendingInputs as NGP_Input[]
global NGP_ClientData as NGP_ClientDataBackup[MAX_CLIENTS]
global NGP_LocalSequenceNumber as integer
global NGP_LocalSlotState as NGP_Slot
global NGP_tmpSlotState as NGP_Slot

global WORLD_STEP_MS as integer



/************************ INTERNAL FUNCTIONS **************************/

function NGP_SendMovement(iNetID as integer,Slot as integer, Velocity as integer,Speed as float) 
	NGP_LocalSequenceNumber=NGP_LocalSequenceNumber+1
	
	tmpInput as NGP_Input
	tmpInput.Slot=Slot
	tmpInput.Velocity = Velocity
	tmpInput.Speed=Speed 
	tmpInput.SequenceNumber = NGP_LocalSequenceNumber
	
	NGP_sendNetworkInput(iNetID,tmpInput)
	
	NGP_ApplyMovement(tmpInput)
	
	NGP_PendingInputs.insert(tmpInput)
	
endfunction

function NGP_sendNetworkInput(iNetID as integer,input as NGP_Input)
	
	iMsgID = CreateNetworkMessage()
	
		AddNetworkMessageInteger(iMsgID,6666)
		AddNetworkMessageInteger(iMsgID,input.SequenceNumber)
		AddNetworkMessageFloat( iMsgID, input.Speed )
		AddNetworkMessageInteger(iMsgID,input.Slot)
		AddNetworkMessageFloat( iMsgID, input.Velocity )
		AddNetworkMessageInteger(iMsgID,GetMilliseconds())
		
	
	SendNetworkMessage( iNetID, 1, iMsgID ) // Send the time request to AGKServer (always ID 1)
	
endfunction

function NGP_ApplyMovement(input as NGP_Input)
	
	NGP_LocalSlotState.Slot[input.Slot] = NGP_LocalSlotState.Slot[input.Slot]+ (input.Velocity * input.Speed)

endfunction

function NGP_CloseNetwork(iNetID as integer)
	
	for i=0 to MAX_CLIENTS
		NGP_ClientData[i].Connected=0
		NGP_EmptyClientData(i)
	next i
	NGP_NetworkState=-1
	CloseNetwork(iNetID)
	NGP_onNetworkClosed(iNetID)
endfunction

function NGP_JoinNetwork(ServerHost as string,ServerPort as integer, Nickname as string, NetworkLatency as integer)
	// Define default ServerStep Interval (will be overriden by the 6665 Server Command)
	WORLD_STEP_MS = 100	
	
	// current connection state
	// 0 = connecting, 1 = connected, -1 = network closed, -2 = connection failed
	NGP_NetworkState=0	
	// save the time that the connection attempt was made so that we can set a time out for connection failure
	NGP_connectTime = Timer()	
	iNetID = JoinNetwork(ServerHost,ServerPort, Nickname)
	SetNetworkLatency( iNetID, NetworkLatency )

endfunction iNetID

function NGP_GetNetworkState()
	
endfunction NGP_NetworkState

function NGP_UpdateNetwork(iNetID as integer)

// if a network is running, find the number of clients
    NGP_clientNum = 0
    if NGP_NetworkState >= 0
        NGP_clientNum = GetNetworkNumClients(iNetID)
    endif
	if NGP_NetworkState = 0
			
			Print("Connection Pending...")
			// if the number of clients is greater than 1, the connection has succeeded
			// this is because once connected, there must be a minimum of 2 clients (this machine and the host)
			if NGP_clientNum > 1
				NGP_NetworkState = 1 // indicate that we are now connected
				
				NGP_onNetworkJoined(iNetID,GetNetworkMyClientId(iNetID))
			// if the connection has not yet succeeded, check for a time out or an error
			// if it takes longer than 5 seconds to connect, it is safe to assume that it has failed
			// any time isnetworkactive returns 0 also indicates a failure
			// reasons for failure might include there being no such network as ExampleNetwork
			elseif Timer() - NGP_connectTime > 15.0 or IsNetworkActive(iNetID) = 0
				NGP_NetworkState = -2 // indicate that the connection failed
				// close the network so that we are free to attempt another connection
				// CloseNetwork(networkId)
				NGP_CloseNetwork(networkId)
			endif
	elseif NGP_NetworkState = -1
			//NGP_NotifyClientDisconnect(myClientId)
			Print("Network Closed")
    // if the network connection has failed, display an error message
    elseif NGP_NetworkState = -2
			Print("Network Connection Failed")
	
	// if we are currently connected
	elseif NGP_NetworkState = 1

			if IsNetworkActive(iNetID)

				// Check for network messages
				isMsg=GetNetworkMessage(iNetID)
				while isMsg>0
					ServerCommand = GetNetworkMessageInteger(isMsg)
					
					if ServerCommand=6665 // This command is the notification of the WORLD_STEP_MS (World Step interval)
						
					WORLD_STEP_MS = GetNetworkMessageInteger( isMsg )	
					
					
					elseif ServerCommand=6667 // This Command is a World State notification  
						ClientID=GetNetworkMessageInteger( isMsg )
						
						LastProcessedInput=GetNetworkMessageInteger( isMsg )
						LocalTime = GetNetworkMessageInteger( isMsg )
						
						for i=1 to NGP_SLOT_SIZE
							NGP_tmpSlotState.Slot[i] = GetNetworkMessageFloat( isMsg )
						next i
						
						change=0
						QueueSize=NGP_ClientData[ClientID].QueueSlot.length
						if NGP_ClientData[ClientID].QueueSlot.length>-1
							
							for i=1 to NGP_SLOT_SIZE
								if NGP_tmpSlotState.Slot[i]<>NGP_ClientData[ClientID].QueueSlot[QueueSize].Slot[i] 
								change=1
								exit
								endif
							next i
						else
							change=1
						endif
						
						if change = 0
											NGP_ClientData[ClientID].QueueSlot.remove(QueueSize)
											NGP_ClientData[ClientID].QueueMS.remove(QueueSize)
											NGP_ClientData[ClientID].QueueLocalMS.remove(QueueSize)
											NGP_ClientData[ClientID].QueueInProgress.remove(QueueSize)
											
						endif
							
								NGP_ClientData[ClientID].QueueSlot.insert(NGP_tmpSlotState)
								NGP_ClientData[ClientID].QueueMS.insert(LocalTime)
								NGP_ClientData[ClientID].QueueLocalMS.insert(GetMilliseconds())
								NGP_ClientData[ClientID].QueueInProgress.insert(0)


						///// Reconciliation
						if ClientID=GetNetworkMyClientID(iNetID)
						
							// Local Display
							
							NGP_LocalSlotState = NGP_tmpSlotState
							i=0
								while (i<=NGP_PendingInputs.length)
									
									if NGP_PendingInputs[i].SequenceNumber<=LastProcessedInput
										NGP_PendingInputs.remove(i)
									else
										NGP_ApplyMovement(NGP_PendingInputs[i])
										inc i,1
									endif
								endwhile
						endif
					else // Pass to OnNetworkMessage Event If other message command received ( != 6667 )
						NGP_onNetworkMessage(ServerCommand,isMsg)
					endif
				isMsg=GetNetworkMessage(iNetID)
				endwhile
				
				// Call The Player Entity Update Local Function after Reconciliation 
				NGP_onLocalPlayerMoveUpdate(iNetID, NGP_LocalSlotState)
				
				
				//////// ECHO INTERPOLATION ////////////
				
				tmpSlot as NGP_Slot
				//for ClientID=2 to GetNetworkNumClients( iNetID ) // ID 1 is the server... so... start at ID 2 :) !
				ClientID = GetNetworkFirstClient( iNetID )
				while ClientID<>0 	
					
					if GetNetworkClientDisconnected(iNetID,ClientID) // Client is Disconnected ?
						// Clean the client and handle the Disconnect Event
						NGP_ClientData[ClientID].Connected=0
						 // Empty Client Slot Variables
						NGP_onNetworkPlayerDisconnect(iNetID,ClientID)
						// Finally delete the Client
						DeleteNetworkClient(iNetID,ClientID)
					else // Client is connected
					
						if NGP_ClientData[ClientID].Connected=0
							NGP_ClientData[ClientID].Connected=1
							NGP_EmptyClientData(ClientID)
							NGP_onNetworkPlayerConnect(iNetID,ClientID)
						endif
					 
						if NGP_ClientData[ClientID].QueueSlot.length>0				
							if NGP_ClientData[ClientID].QueueInProgress[0] <> 1 
								NGP_ClientData[ClientID].QueueInProgress[0]=1
								NGP_ClientData[ClientID].QueueLocalMS[0]=GetMilliseconds()
							endif
							
							Delta#=(NGP_ClientData[ClientID].QueueMS[1]-NGP_ClientData[ClientID].QueueMS[0]) // A/R
							
							if Delta#>WORLD_STEP_MS*1.5 then Delta#=WORLD_STEP_MS
							
							CurrentDelta#=GetMilliseconds()-NGP_ClientData[ClientID].QueueLocalMS[0] + 25*Tween# //- NGP_ClientData[ClientID].LastStepLocal // 25 ms
							
								DivDelta# = (Delta#/CurrentDelta#) // A-R
								
								Progress# = 1.0/DivDelta#
								
								if (Progress#<1.0)
								
									for i=1 to NGP_SLOT_SIZE
										if (i=POS_X or i=POS_Y or i=POS_Z or i=POS_X2 or i=POS_Y2 or i=POS_Z2)
											tmpSlot.Slot[i] = Lerp(NGP_ClientData[ClientID].QueueSlot[0].Slot[i],NGP_ClientData[ClientID].QueueSlot[1].Slot[i],Progress#)
										endif
										if (i=ANG_X or i=ANG_Y or i=ANG_Z or i=ANG_X2 or i=ANG_Y2 or i=ANG_Z2)
											tmpSlot.Slot[i] = ALerp(NGP_ClientData[ClientID].QueueSlot[0].Slot[i],NGP_ClientData[ClientID].QueueSlot[1].Slot[i],Progress#)
										endif
										
									next i
									
									// Call The other Players Entity Update Function after Interpolation
									NGP_onNetworkPlayerMoveUpdate(iNetID, ClientID, tmpSlot)
								else
									// Call The other Players Entity Update Function after ending interpolation
									NGP_onNetworkPlayerMoveUpdate(iNetID, ClientID, NGP_ClientData[ClientID].QueueSlot[1])

									// Remove the processed Queue item
									NGP_ClientData[ClientID].QueueSlot.remove(0)
									NGP_ClientData[ClientID].QueueMS.remove(0)
									NGP_ClientData[ClientID].QueueLocalMS.remove(0)
									NGP_ClientData[ClientID].QueueInProgress.remove(0)
								endif
						
							//Delta#=0.0
						else
							
							if NGP_ClientData[ClientID].QueueSlot.length = 0
								
								NGP_onNetworkPlayerMoveUpdate(iNetID, ClientID, NGP_ClientData[ClientID].QueueSlot[0])
									
								if NGP_ClientData[ClientID].QueueLocalMS[0]<GetMilliseconds()-500
									NGP_ClientData[ClientID].QueueSlot.remove(0)
									NGP_ClientData[ClientID].QueueMS.remove(0)
									NGP_ClientData[ClientID].QueueLocalMS.remove(0)
									NGP_ClientData[ClientID].QueueInProgress.remove(0)
								endif
							endif
					
						endif
					
					endif
				ClientID = GetNetworkNextClient( iNetID )
				endwhile
			endif

    endif
endfunction



function NGP_EmptyClientData(ClientID as integer)
	
	for i=0 to NGP_ClientData[ClientID].QueueSlot.length
		NGP_ClientData[ClientID].QueueSlot.remove(i)
		NGP_ClientData[ClientID].QueueMS.remove(i)
		NGP_ClientData[ClientID].QueueLocalMS.remove(i)
		NGP_ClientData[ClientID].QueueInProgress.remove(i)
	next i
		
endfunction



//// Interpolation functions ///////////////////

function Lerp( startValue as float, endValue as float , value as float )
    result# = ((1.0 - value) * startValue) + (value * endValue)
endfunction result#


function ALerp( startValue as float, endValue as float , value as float )

  // put between 0 and 360
   a# = fmod(startValue, 360.0)
   b# = fmod(endValue, 360.0)

   // The smallest arc doesn't cross the 0/360 point?
   if (abs(b#-a#) <= 180.0)
		returnValue#=(a# + (b#-a#)*value)
		exitfunction returnValue#
   endif


   if (a# > b#)

		delta# = 360.0 - a#
		a# = 0.0 // equivalent to a += delta
		b# = b# + delta#

   else // b > a

		delta# = 360.0 - b#
		b# = 0.0 // equivalent to b += delta
		a# = a# + delta#
   endif

   returnValue#=fmod( ( a# + (b#-a#)*value ) - delta#, 360.0 )
endfunction returnValue#

