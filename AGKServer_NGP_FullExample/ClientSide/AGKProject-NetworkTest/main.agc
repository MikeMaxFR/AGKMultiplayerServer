
// Project: NetworkTest 
// Created: 2017-05-11

#include "NetGamePlugin.agc"
// show all errors
SetErrorMode(1)

/****************************************************************/
/*                  DISPLAY PROPERTIES							*/
/****************************************************************/

// set window properties

SetWindowTitle( "NetworkTest with AGK Server PHP" )
SetWindowSize( 1280,800, 0 )
SetWindowAllowResize( 0 ) // allow the user to resize the window

// set display properties
SetVirtualResolution( 1280,800 ) // doesn't have to match the window
SetOrientationAllowed( 1, 1, 1, 1 ) // allow both portrait and landscape on mobile devices
SetSyncRate( 60, 1 ) // 30fps instead of 60 to save battery
SetScissor( 0,0,0,0 ) // use the maximum available screen space, no black borders
UseNewDefaultFonts( 1 ) // since version 2.0.22 we can use nicer default fonts
SetPrintSize( 24 )
SetAntialiasMode(1)
SetPrintColor(255,255,255)
SetClearColor( 0, 0, 0 )

/****************************************************************/
/*                  DECLARATIONS								*/
/****************************************************************/

global Tween#


/****************************************************************/
/*          LOCAL SPRITE PROPERTIES AND INIT FUNCTION			*/
/****************************************************************/

global displayGhost as integer

global TrackSprite as integer

global localSprite as integer
global localNickLabel as integer

global OwnSpriteColorChosen as integer

global carMaxSpeed as float
global carAngle as float
global carSpeed as float
global carAngleFactor as float
global UseBoost as integer

carMaxSpeed = 4.0

function CreateLocalCircuit()
	// Create Circuit Sprite
	TrackSprite = LoadSprite("track.jpg")
	SetSpriteSize(TrackSprite,1280,800)
endfunction

function CreateLocalCarSprite()
	// Local Player Sprite
	localSprite=CreateSprite(LoadImage("red_car.png"))
	SetSpriteSize(localSprite,56,28)
	SetSpriteOffset(localSprite,28,14)

	// Local Text Label for Player's sprite
	localNickLabel = CreateText("Local")
	SetTextAlignment( localNickLabel, 1 )
	SetTextSize(localNickLabel,25)
	carAngle = 0
	carSpeed = 0
	carAngleFactor = 0
endfunction

/****************************************************************/
/*          		CHAT & TEXT INPUT FUNCTION						*/
/****************************************************************/

Global ChatMessages as string[]


function TextInput(default$,X#,Y#)

	tmpEditBox = CreateEditBox()
	SetEditBoxPosition(tmpEditBox,X#,Y#)
	SetEditBoxText(tmpEditBox,default$)
	
	SetEditBoxSize(tmpEditBox,350,50)
	SetEditBoxTextSize( tmpEditBox, 40 )
	//Enter 'quit' to End
	SetEditBoxFocus(tmpEditBox,1)

	Repeat
		Sync()
	Until GetEditBoxHasFocus( tmpEditBox ) =0

	ReturnValue$ = GetEditBoxText(tmpEditBox)

	DeleteEditBox(tmpEditBox)

endfunction ReturnValue$



/****************************************************************/
/*                  NETWORK SERVER PROPERTIES					*/
/****************************************************************/


global networkId
global myClientId

ServerHost$="163.172.175.19" // IP Of MikeMax Linux Box for testing :)
ServerPort=33333
NetworkLatency = 25 // Should always be less than the NETGAMEPLUGIN_WORLDSTATE_INTERVAL defined in the server plugin


/****************************************************************/
/*                  START OF THE PROGRAM						*/
/****************************************************************/

// Add Joystick
AddVirtualJoystick( 1, 800/6.0,800-800/6,800/3)
SetVirtualJoystickAlpha(1,100,125)

// Call the creation of the circuit
CreateLocalCircuit()

// Firstly ask for the nickname !

nickname$ = TextInput("NickName"+right(str(GetMilliseconds()),3),GetDeviceWidth()/2-175,GetDeviceHeight()/2-40)

global ChatBoxMessage,LabelMessage,chatEditBox

function createChatBox()
// Display the ChatBox with incoming messages
ChatBoxMessage = CreateText("Welcome to the Chat !")
SetTextMaxWidth( ChatBoxMessage, 520 )
//SetTextScissor
SetTextPosition(ChatBoxMessage,385,275)
SetTextSize(ChatBoxMessage,24)
SetTextColor(ChatBoxMessage,0,0,0,255)

// Display the Header Chat Input textbox
LabelMessage = CreateText("Chat : ")
SetTextPosition(LabelMessage,385,467)
SetTextSize(LabelMessage,26)
SetTextColor(LabelMessage,0,0,0,255)
SetTextVisible(LabelMessage,0)

chatEditBox = CreateEditBox()
SetEditBoxSize(chatEditBox,420,25)
SetEditBoxTextSize( chatEditBox, 24 )
SetEditBoxPosition(chatEditBox,GetDeviceWidth()/2-190,470)
SetEditBoxFocus(chatEditBox,0)
SetEditBoxVisible(chatEditBox,0)
ChatEditFocus = 0
endfunction

function destroyChatBox()
	DeleteEditBox(chatEditBox)
	DeleteText(LabelMessage)
	DeleteText(ChatBoxMessage)
endfunction


createChatBox()

global JoinSound
global QuitSound
JoinSound = LoadSound("cartoon007.wav")
QuitSound = LoadSound("cartoon006.wav")

// attempt to connect to ExampleNetwork as a client with the client name Client
networkId = NGP_JoinNetwork(ServerHost$,ServerPort, nickname$ , NetworkLatency)

/****************************************************************************************************/
/****************************************************************************************************/
/****************************************************************************************************/
/***************************************  MAIN LOOP	 ************************************************/
/****************************************************************************************************/
/****************************************************************************************************/
/****************************************************************************************************/



do
	//// TWEEN /////////
	FPS#=1/GetFrameTime()
	Tween#=60/FPS# // Movements Speed set at 60fps => it's now the reference for Tween Factor.
	//print(str(Tween#))
	
   
        if IsNetworkActive(networkId)
        
            if not OwnSpriteColorChosen
				SetNetworkLocalInteger(networkId,"colorR",random(100,200))
				SetNetworkLocalInteger(networkId,"colorG",random(100,200))
				SetNetworkLocalInteger(networkId,"colorB",random(100,200))
				OwnSpriteColorChosen = 1
			endif
			
            // print the network details and the details of this client
            
            Print("Keys:  'G' - Switch Ghost display / 'M' - Chat Message / Arrow Keys : Move Car / 'SPACE' : BOOST / 'C' - Change Channel / N - Open/Close Network")
            Print("AGKServer Example App - Network Active")
            //print("FPS : "+str(ScreenFPS()))
			Print("WorldStep Interval Configured : "+ str(WORLD_STEP_MS)+"ms"+" / Local Network Latency Configured : "+str(NetworkLatency)+"ms")
            print("Actual Members in Channel " + str(GetNetworkClientInteger( networkId, myClientId, "SERVER_CHANNEL" ))+" :")
			//Print("Network Clients Detected in Channel : " + Str(clientNum))
            
            //Print("Server Id: " + Str(GetNetworkServerId(networkId)))
            //Print("Client Id: " + Str(myClientId))
            //Print("Client Name: " + GetNetworkClientName(networkId, myClientId))

            id = GetNetworkFirstClient( networkId )
            while id<>0 
				
				
					
					//NGP_NotifyClientConnect(id)
					// Handle Connected client
					 if id = myClientId then you$="(You)" else you$="" 
					
					print( "- Client ID " + str(id) + " > "+GetNetworkClientName( networkId, id )+" "+you$)
						
					// ignore server ID for display (In Real Life, You should also ignore LocalPlayer => id = myClientId but we want the Server Ghost)
					if id = 1 // or id = myClientId 
						id = GetNetworkNextClient( networkId )
						continue
					endif
					
					
					
					// Update Sprite Color from Client
						SpriteToColorize = GetNetworkClientUserData( networkId, id, 1)
						colorR=GetNetworkClientInteger(networkId,id,"colorR")
						colorG=GetNetworkClientInteger(networkId,id,"colorG")
						colorB=GetNetworkClientInteger(networkId,id,"colorB")
						
						if id=myClientId 
							AlphaSprite=150 // This for the ghost !
							SetSpriteColor(localSprite,colorR,colorG,colorB,255) // Colorize Local sprite with full alpha 
							else
							AlphaSprite=255
						endif
						
						SetSpriteColor(SpriteToColorize,colorR,colorG,colorB,AlphaSprite) // semi transparent if my server ghost or fully opaque if it's another player
					
					
					
				
			id = GetNetworkNextClient( networkId )
			endwhile
          
           
            
        else
            Print("Network Inactive")
        endif
   
   
    // if we are currently connected, give the user the opportunity to leave the network
    if NGP_GetNetworkState() = 1
        Print("")
        Print("Press N Key To Close Network")
    // if we are not currently connected, or trying to connect, give the user the opportunity to join the network
    elseif NGP_GetNetworkState() < 0
        Print("")
        Print("Press N Key To Connect")
    endif




/****************************************************************/
/*                  CAR INPUTS & PHYSICS						*/
/****************************************************************/


	if carSpeed>carMaxSpeed then carSpeed=carMaxSpeed
	if carSpeed<-carMaxSpeed then carSpeed=-carMaxSpeed
	
	// Inertia
	if GetRawKeyState(38)=0 and GetRawKeyState(40)=0 and GetVirtualJoystickY( 1 )=0 and GetJoystickY()=0
		if carSpeed>0 then carSpeed=carSpeed-(0.1* Tween#)
		if carSpeed<0 then carSpeed=carSpeed+(0.1* Tween#)
		if carSpeed>-0.20 and carSpeed<0.20 then carSpeed=0
	endif



	if abs(carSpeed)>2.0
		carAngleFactor = (3/carSpeed) * Tween#
	else
		carAngleFactor=3/2
	endif

	// Send Movements only when Speed != 0 // Calculate the velocity vectors along X,Y with car rotation (carAngle variable)
	if carSpeed<>0 
		NGP_SendMovement(networkId, POS_X, 1, (cos(carAngle)* Tween#)*carSpeed*UseBoost)
		NGP_SendMovement(networkId, POS_Y, 1, (sin(carAngle)* Tween#)*carSpeed*UseBoost)	
		UseBoost=1	
	endif

	// Keys Movements

	if GetEditBoxHasFocus(chatEditBox) = 0
			if GetRawKeyState(38) or GetVirtualJoystickY( 1 )<0 or GetJoystickY()<0 // UP
				print("up!")
				carSpeed=carSpeed+(0.1* Tween#)
			endif
			
			if GetRawKeyState(37) or GetVirtualJoystickX( 1 )<0 or GetJoystickX()<0 // LEFT
				print("left!")
				carAngle=carAngle-2.0*carAngleFactor
				NGP_SendMovement(networkId, ANG_Y, -1,2.0*carAngleFactor)
			endif
			
			if GetRawKeyState(39) or GetVirtualJoystickX( 1 )>0 or GetJoystickX()>0 // RIGHT
				print("right!")
				carAngle=carAngle+2.0*carAngleFactor
				NGP_SendMovement(networkId, ANG_Y, 1,2.0*carAngleFactor)
			endif
			
			if GetRawKeyState(40) or GetVirtualJoystickY( 1 )>0 or GetJoystickY()>0 // BREAKs / Reverse
				print("down!")
				carSpeed=carSpeed-(0.1* Tween#)
			endif
			
			if GetRawKeyState(32) // SPACE
				print("Boost !")
				UseBoost=1.5
			endif
	endif	
	
	
/****************************************************************/
/*                  OTHER INPUTS								*/
/****************************************************************/	
 	if GetRawKeyReleased(27) // Escape => Quit
		
		if IsNetworkActive(networkId)
			NGP_CloseNetwork(networkId)
		endif
		
		end
	endif

	
	if GetRawKeyReleased(77) or ( GetPointerState() and  GetPointerX()>360 and GetPointerX()<916 and GetPointerY()>290 and GetPointerY()<505 ) // M => Send new message
		SetTextVisible(LabelMessage,1)
		SetEditBoxVisible(chatEditBox,1)
		SetEditBoxFocus(chatEditBox,1)
	endif
	
	if GetEditBoxHasFocus( chatEditBox ) = 1 and ChatEditFocus = 0
		//We Gain Focus
		ChatEditFocus = 1
	endif
	
	if GetEditBoxHasFocus( chatEditBox ) = 0 and ChatEditFocus = 1
		//We lost Focus, consider message validation with ENTER
		if len(TrimString(GetEditBoxText(chatEditBox)," "))>0
				// Add message to local queue
				ChatMessages.insert(GetNetworkClientName(networkId, myClientId)+" : "+GetEditBoxText(chatEditBox))
				////// Send Message //////
				newMsg=CreateNetworkMessage()
				AddNetworkMessageInteger(newMsg,6800) // Arbitrary server command for Chat Messages
				AddNetworkMessageString(newMsg,GetEditBoxText(chatEditBox))
				SendNetworkMessage(networkId,0,newMsg) // Send to all clients
		
		endif
		SetEditBoxText(chatEditBox,"")
		ChatEditFocus = 0
		SetTextVisible(LabelMessage,0)
		SetEditBoxVisible(chatEditBox,0)
	endif
	
	
		if GetEditBoxHasFocus(chatEditBox) = 0 // if we are not chatting, capture keys pressed
	
						if GetRawKeyState(187) 
							 NetworkLatency = NetworkLatency+1
							 SetNetworkLatency(networkId,NetworkLatency)
						endif
						
						if GetRawKeyState(189)
							 NetworkLatency = NetworkLatency-1
							  SetNetworkLatency(networkId,NetworkLatency)
						endif
					   
						
						
						
						if GetRawKeyReleased(71) // G to swith Ghost Mode
							
							displayGhost = not displayGhost
							
						endif
					   
						if GetRawKeyReleased(67) // C // Change Channel
							
								SetNetworkLocalInteger( networkId, "SERVER_CHANNEL", val(TextInput("Channel_number?",100,100)) )
						
						endif  
						if GetRawKeyReleased(78) // N // Network connect switch       
							CurrentNetState=NGP_GetNetworkState()
							
							if CurrentNetState = 1 // if connected
								// disconnect from the network
								NGP_CloseNetwork(networkId)
							elseif CurrentNetState < 0 // if NOT connected
								// join the network
								networkId = NGP_JoinNetwork(ServerHost$,ServerPort, nickname$, NetworkLatency)
							endif
						endif
		endif
    
    //// Update Messages ChatBox
    offset = ChatMessages.length
    if offset>5 then offset = 5
    ChatBox$=""
    for i=ChatMessages.length-offset to ChatMessages.length
		ChatBox$ = ChatBox$ + chr(10) + ChatMessages[i]
	next i
	SetTextString(ChatBoxMessage,ChatBox$)
    
    
    
    
    
/************************************************************************/
/*                    NETWORK UPDATE CALL								*/
/*                    -------------------								*/
/* VERY IMPORTANT ! Handles All NGP network Events ! (see Events below)	*/
/************************************************************************/
	NGP_UpdateNetwork(networkId) 
	
/****************************************************************/
/*                  SCREEN UPDATE								*/
/****************************************************************/
    Sync()
    
loop

/****************************************************************************************************/
/****************************************************************************************************/
/****************************************************************************************************/
/**************************************  END OF MAIN LOOP  ******************************************/
/****************************************************************************************************/
/****************************************************************************************************/
/****************************************************************************************************/





/****************************************************************/
/****************************************************************/
/*                   	NETWORK EVENTS							*/
/****************************************************************/
/****************************************************************/


/*******************************************/
/* When local client has joined the server */
/*******************************************/
function NGP_onNetworkJoined(iNetID, localClientID)
	myClientId=localClientID
	OwnSpriteColorChosen=0
	CreateLocalCircuit()
	createChatBox()
	CreateLocalCarSprite() // Create local direct sprite for the local client 
endfunction


/******************************************************/
/* When local client has disconnected from the server */
/******************************************************/
function NGP_onNetworkClosed(iNetID)
	destroyChatBox()
	DeleteAllSprites()
	DeleteAllText()
endfunction


/*************************************************************************************************************************************/
/* When a client has joined the server (includes the local client himself, comparable to the "NGP_onNetworkJoined" for local client) */
/*************************************************************************************************************************************/
function NGP_onNetworkPlayerConnect(iNetID, ClientID)

	
	PlaySound(JoinSound)


		if ClientID = 1 then exitfunction // Ignore Server Join (we only want players !)

	////// Create Sprite for New Client
	//Message("Client ID "+str(ClientID)+" has joined")
		newSprite=CreateSprite(LoadImage("red_car.png"))
		SetSpriteSize(newSprite,56,28)
		SetSpriteOffset(newSprite,28,14)
		// Affect the sprite ID to the ClientID at UserData Slot 1
		SetNetworkClientUserData(iNetID, ClientID, 1, newSprite)
		
		
	////// Creating a Text on the sprite
	
		// Choosing Text to show
		if ClientID = myClientId
			TextToShow$ = "Network GHOST" 
		else
			TextToShow$ = GetNetworkClientName( iNetID, ClientID )
		endif
		
		newNickLabel = CreateText(TextToShow$)
		SetTextAlignment( newNickLabel, 1 )
		SetTextSize(newNickLabel,25)
		// Affect the Text ID to the ClientID at UserData Slot 1
		SetNetworkClientUserData(iNetID, ClientID, 2, newNickLabel)

endfunction


/************************************************************************/
/* When a client has disconnected from the server (or "server channel") */ 
/************************************************************************/
function NGP_onNetworkPlayerDisconnect(iNetID, ClientID)
PlaySound(QuitSound)
		// Delete Player Sprite
		SpriteToDelete = GetNetworkClientUserData( iNetID, ClientID, 1)
		DeleteSprite(SpriteToDelete)
		SetNetworkClientUserData(iNetID, ClientID, 1, 0)
		// Delete Player Text Label
		TextToDelete = GetNetworkClientUserData( iNetID, ClientID, 2)
		DeleteText(TextToDelete)
		SetNetworkClientUserData(iNetID, ClientID, 2, 0)
		

endfunction


/********************************************************************************/
/* When the local client has moved (After Prediction and Server Reconciliation) */ 
/********************************************************************************/
function NGP_onLocalPlayerMoveUpdate(iNetID, UpdatedMove as NGP_Slot)
	
	SetSpritePositionByOffset(localSprite,UpdatedMove.Slot[POS_X] ,UpdatedMove.Slot[POS_Y])
	SetSpriteAngle(localSprite,UpdatedMove.Slot[ANG_Y])
	
	SetTextPosition(localNickLabel,UpdatedMove.Slot[POS_X] ,UpdatedMove.Slot[POS_Y]+20)	
		
endfunction


/**********************************************************/
/* When a client has moved (After internal interpolation) */ 
/**********************************************************/
function NGP_onNetworkPlayerMoveUpdate(iNetID, ClientID as integer, UpdatedMove as NGP_Slot)


		SpriteID = GetNetworkClientUserData( iNetID, ClientID, 1)
		LabelID = GetNetworkClientUserData( iNetID, ClientID, 2)
		SetSpritePositionByOffset(SpriteID,UpdatedMove.Slot[POS_X] ,UpdatedMove.Slot[POS_Y])	
		SetSpriteAngle(SpriteID,UpdatedMove.Slot[ANG_Y])
		
		SetTextPosition(LabelID,UpdatedMove.Slot[POS_X] ,UpdatedMove.Slot[POS_Y]+20)
		
			
		if ClientID = myClientId   
			 
			 // Hide Ghost Sprite and TextLabel
			 SetSpriteVisible(SpriteID,displayGhost) // <-- Key "G" to switch displayGhost
			 SetTextVisible(LabelID,displayGhost) // <-- Key "G" to switch displayGhost
			
			
				
		endif
		

	
		
endfunction

/********************************************************************************************************************************/
/* When a network message Arrived (associated with a Server Command already consumed inside idMessage)							*/
/* This does not include Move updates which are handles internally by NGP 														*/
/* (see these other specific events : NGP_onNetworkPlayerMoveUpdate, NGP_onLocalPlayerMoveUpdate) 								*/
/********************************************************************************************************************************/
function NGP_onNetworkMessage(ServerCommand as integer,idMessage as integer)
	
	// do anything here
	
	if ServerCommand = 6800 // It's a chat message !
						//message("ok")
						NewChatMessage$ = GetNetworkMessageString(idMessage)
						SenderName$ = GetNetworkClientName(networkId, GetNetworkMessageFromClient(idMessage))
						ChatMessages.insert( SenderName$+" : "+NewChatMessage$) // Add message to Messages array
						if ChatMessages.length>100 then ChatMessages.remove(0) // Do some cleaning
	endif
	
endfunction




