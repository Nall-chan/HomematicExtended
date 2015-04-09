<?php
 // --- BASE MESSAGE
 define('IPS_BASE', 10000);                             //Base Message

 define('IPS_KERNELSHUTDOWN',IPS_BASE + 1);            //Pre Shutdown Message, Runlevel UNINIT Follows
 define('IPS_KERNELSTARTED', IPS_BASE + 2);             //Post Ready Message
        
 // --- KERNEL
 define('IPS_KERNELMESSAGE', IPS_BASE + 100);           //Kernel Message
 define('KR_CREATE', IPS_KERNELMESSAGE + 1);            //Kernel is beeing created
 define('KR_INIT', IPS_KERNELMESSAGE + 2);              //Kernel Components are beeing initialised, Modules loaded, Settings read
 define('KR_READY', IPS_KERNELMESSAGE + 3);             //Kernel is ready and running
 define('KR_UNINIT', IPS_KERNELMESSAGE + 4);            //Got Shutdown Message, unloading all stuff
 define('KR_SHUTDOWN', IPS_KERNELMESSAGE + 5);          //Uninit Complete, Destroying Kernel Inteface
 define('IPS_LOGMESSAGE', IPS_BASE + 200);              //Logmessage Message
 define('KL_MESSAGE', IPS_LOGMESSAGE + 1);              //Normal Message                      | FG: Black | BG: White  | STLYE : NONE
 define('KL_SUCCESS', IPS_LOGMESSAGE + 2);              //Success Message                     | FG: Black | BG: Green  | STYLE : NONE
 define('KL_NOTIFY', IPS_LOGMESSAGE + 3);               //Notiy about Changes                 | FG: Black | BG: Blue   | STLYE : NONE
 define('KL_WARNING', IPS_LOGMESSAGE + 4);              //Warnings                            | FG: Black | BG: Yellow | STLYE : NONE
 define('KL_ERROR', IPS_LOGMESSAGE + 5);                //Error Message                       | FG: Black | BG: Red    | STLYE : BOLD
 define('KL_DEBUG', IPS_LOGMESSAGE + 6);                //Debug Informations + Script Results | FG: Grey  | BG: White  | STLYE : NONE
 define('KL_CUSTOM', IPS_LOGMESSAGE + 7);               //User Message                        | FG: Black | BG: White  | STLYE : NONE

 // --- MODULE LOADER
 define('IPS_MODULEMESSAGE', IPS_BASE + 300);           //ModuleLoader Message
 define('ML_LOAD', IPS_MODULEMESSAGE + 1);              //Module loaded
 define('ML_UNLOAD', IPS_MODULEMESSAGE + 2);            //Module unloaded

 // --- OBJECT MANAGER
 define('IPS_OBJECTMESSAGE', IPS_BASE + 400);
 define('OM_REGISTER', IPS_OBJECTMESSAGE + 1);          //Object was registered
 define('OM_UNREGISTER', IPS_OBJECTMESSAGE + 2);        //Object was unregistered
 define('OM_CHANGEPARENT', IPS_OBJECTMESSAGE + 3);      //Parent was Changed
 define('OM_CHANGENAME', IPS_OBJECTMESSAGE + 4);        //Name was Changed
 define('OM_CHANGEINFO', IPS_OBJECTMESSAGE + 5);        //Info was Changed
 define('OM_CHANGETYPE', IPS_OBJECTMESSAGE + 6);        //Type was Changed
 define('OM_CHANGESUMMARY', IPS_OBJECTMESSAGE + 7);     //Summary was Changed
 define('OM_CHANGEPOSITION', IPS_OBJECTMESSAGE + 8);    //Position was Changed
 define('OM_CHANGEREADONLY', IPS_OBJECTMESSAGE + 9);    //ReadOnly was Changed
 define('OM_CHANGEHIDDEN', IPS_OBJECTMESSAGE + 10);     //Hidden was Changed
 define('OM_CHANGEICON', IPS_OBJECTMESSAGE + 11);       //Icon was Changed
 define('OM_CHILDADDED', IPS_OBJECTMESSAGE + 12);       //Child for Object was added
 define('OM_CHILDREMOVED', IPS_OBJECTMESSAGE + 13);     //Child for Object was removed
 define('OM_CHANGEIDENT', IPS_OBJECTMESSAGE + 14);      //Ident was Changed

 // --- INSTANCE MANAGER
 define('IPS_INSTANCEMESSAGE', IPS_BASE + 500);         //Instance Manager Message
 define('IM_CREATE', IPS_INSTANCEMESSAGE + 1);          //Instance created
 define('IM_DELETE', IPS_INSTANCEMESSAGE + 2);          //Instance deleted
 define('IM_CONNECT', IPS_INSTANCEMESSAGE + 3);         //Instance connectged
 define('IM_DISCONNECT', IPS_INSTANCEMESSAGE + 4);      //Instance disconncted
 define('IM_CHANGESTATUS', IPS_INSTANCEMESSAGE + 5);    //Status was Changed
 define('IM_CHANGESETTINGS', IPS_INSTANCEMESSAGE + 6);  //Settings were Changed
 define('IM_CHANGESEARCH', IPS_INSTANCEMESSAGE + 7);    //Searching was started/stopped
 define('IM_SEARCHUPDATE', IPS_INSTANCEMESSAGE + 8);    //Searching found new results
 define('IM_SEARCHPROGRESS', IPS_INSTANCEMESSAGE + 9);  //Searching progress in %
 define('IM_SEARCHCOMPLETE', IPS_INSTANCEMESSAGE + 10); //Searching is complete

 // --- VARIABLE MANAGER
 define('IPS_VARIABLEMESSAGE', IPS_BASE + 600);              //Variable Manager Message
 define('VM_CREATE', IPS_VARIABLEMESSAGE + 1);               //Variable Created
 define('VM_DELETE', IPS_VARIABLEMESSAGE + 2);               //Variable Deleted
 define('VM_UPDATE', IPS_VARIABLEMESSAGE + 3);               //On Variable Update
 define('VM_CHANGEPROFILENAME', IPS_VARIABLEMESSAGE + 4);    //On Profile Name Change
 define('VM_CHANGEPROFILEACTION', IPS_VARIABLEMESSAGE + 5);  //On Profile Action Change

 // --- SCRIPT MANAGER
 define('IPS_SCRIPTMESSAGE', IPS_BASE + 700);           //Script Manager Message
 define('SM_CREATE', IPS_SCRIPTMESSAGE + 1);            //On Script Create
 define('SM_DELETE', IPS_SCRIPTMESSAGE + 2);            //On Script Delete
 define('SM_CHANGEFILE', IPS_SCRIPTMESSAGE + 3);        //On Script File changed
 define('SM_BROKEN', IPS_SCRIPTMESSAGE + 4);            //Script Broken Status changed

 // --- EVENT MANAGER
 define('IPS_EVENTMESSAGE', IPS_BASE + 800);             //Event Scripter Message
 define('EM_CREATE', IPS_EVENTMESSAGE + 1);             //On Event Create
 define('EM_DELETE', IPS_EVENTMESSAGE + 2);             //On Event Delete
 define('EM_UPDATE', IPS_EVENTMESSAGE + 3);
 define('EM_CHANGEACTIVE', IPS_EVENTMESSAGE + 4);
 define('EM_CHANGELIMIT', IPS_EVENTMESSAGE + 5);
 define('EM_CHANGESCRIPT', IPS_EVENTMESSAGE + 6);
 define('EM_CHANGETRIGGER', IPS_EVENTMESSAGE + 7);
 define('EM_CHANGETRIGGERVALUE', IPS_EVENTMESSAGE + 8);
 define('EM_CHANGETRIGGEREXECUTION', IPS_EVENTMESSAGE + 9);
 define('EM_CHANGECYCLIC', IPS_EVENTMESSAGE + 10);
 define('EM_CHANGECYCLICDATEFROM', IPS_EVENTMESSAGE + 11);
 define('EM_CHANGECYCLICDATETO', IPS_EVENTMESSAGE + 12);
 define('EM_CHANGECYCLICTIMEFROM', IPS_EVENTMESSAGE + 13);
 define('EM_CHANGECYCLICTIMETO', IPS_EVENTMESSAGE + 14);

 // --- MEDIA MANAGER
 define('IPS_MEDIAMESSAGE', IPS_BASE + 900);           //Media Manager Message
 define('MM_CREATE', IPS_MEDIAMESSAGE + 1);             //On Media Create
 define('MM_DELETE', IPS_MEDIAMESSAGE + 2);             //On Media Delete
 define('MM_CHANGEFILE', IPS_MEDIAMESSAGE + 3);         //On Media File changed
 define('MM_AVAILABLE', IPS_MEDIAMESSAGE + 4);          //Media Available Status changed
 define('MM_UPDATE', IPS_MEDIAMESSAGE + 5);

 // --- LINK MANAGER
 define('IPS_LINKMESSAGE', IPS_BASE + 1000);           //Link Manager Message
 define('LM_CREATE', IPS_LINKMESSAGE + 1);             //On Link Create
 define('LM_DELETE', IPS_LINKMESSAGE + 2);             //On Link Delete
 define('LM_CHANGETARGET', IPS_LINKMESSAGE + 3);       //On Link TargetID change
  
 // --- DATA HANDLER
 define('IPS_DATAMESSAGE', IPS_BASE + 1100);             //Data Handler Message
 define('DM_CONNECT', IPS_DATAMESSAGE + 1);             //On Instance Connect
 define('DM_DISCONNECT', IPS_DATAMESSAGE + 2);          //On Instance Disconnect

 // --- SCRIPT ENGINE
 define('IPS_ENGINEMESSAGE', IPS_BASE + 1200);           //Script Engine Message
 define('SE_UPDATE', IPS_ENGINEMESSAGE + 1);             //On Library Refresh
 define('SE_EXECUTE', IPS_ENGINEMESSAGE + 2);            //On Script Finished execution
 define('SE_RUNNING', IPS_ENGINEMESSAGE + 3);            //On Script Started execution

 // --- PROFILE POOL
 define('IPS_PROFILEMESSAGE', IPS_BASE + 1300);
 define('PM_CREATE', IPS_PROFILEMESSAGE + 1);
 define('PM_DELETE', IPS_PROFILEMESSAGE + 2);
 define('PM_CHANGETEXT', IPS_PROFILEMESSAGE + 3);
 define('PM_CHANGEVALUES', IPS_PROFILEMESSAGE + 4);
 define('PM_CHANGEDIGITS', IPS_PROFILEMESSAGE + 5);
 define('PM_CHANGEICON', IPS_PROFILEMESSAGE + 6);
 define('PM_ASSOCIATIONADDED', IPS_PROFILEMESSAGE + 7);
 define('PM_ASSOCIATIONREMOVED', IPS_PROFILEMESSAGE + 8);
 define('PM_ASSOCIATIONCHANGED', IPS_PROFILEMESSAGE + 9);

 // --- TIMER POOL
 define('IPS_TIMERMESSAGE', IPS_BASE + 1400);            //Timer Pool Message
 //TM_REGISTER = IPS_TIMERMESSAGE + 1;
 //TM_UNREGISTER = IPS_TIMERMESSAGE + 2;
 //TM_SETINTERVAL = IPS_TIMERMESSAGE + 3;
 //TM_UPDATE = IPS_TIMERMESSAGE + 4;
 //TM_RUNNING = IPS_TIMERMESSAGE + 5;
 
 // --- TInstanceStatus Constants

 // --- STATUS CODES
 define('IS_SBASE', 100);
 define('IS_CREATING',IS_SBASE + 1); //module is being created
 define('IS_ACTIVE', IS_SBASE + 2); //module created and running
 define('IS_DELETING', IS_SBASE + 3); //module us being deleted
 define('IS_INACTIVE',IS_SBASE + 4); //module is not beeing used

 // --- ERROR CODES
 define('IS_EBASE',200);          //default errorcode
 define('IS_NOTCREATED',IS_EBASE + 1); //instance could not be created

 // --- Search Handling
 define('FOUND_UNKNOWN',0);     //Undefined value
 define('FOUND_NEW',1);         //Device is new and not configured yet
 define('FOUND_OLD',2);         //Device is already configues (InstanceID should be set)
 define('FOUND_CURRENT',3);     //Device is already configues (InstanceID is from the current/searching Instance)
 define('FOUND_UNSUPPORTED',4); //Device is not supported by Module

        
        class HMSysVar extends IPSModule
	{
		private $THMSysVarsList;
		private $HMAddress;
                
                //Dummy
                private $fKernelRunlevel;
                

                
		public function __construct($InstanceID)
		{
			//Never delete this line!
			parent::__construct($InstanceID);
			
			//These lines are parsed on Symcon Startup or Instance creation
			//You cannot use variables here. Just static values.
			$this->RegisterPropertyInteger("EventID", 0);
			$this->RegisterPropertyInteger("Interval", 0);
			$this->RegisterPropertyBoolean("EmulateStatus", false);
			$this->RegisterTimer("ReadHMSysVar", 0);
                        $this->fKernelRunlevel=KR_READY;
		}
                
                public function __destruct()
                {
                    $this->SetTimerInterval('ReadHMSysVar', 0);
//unnötig ?                    parent::__destruct();                    
                }
                
                public function ProcessInstanceStatusChange($InstanceID, $Status)
                {
                    if ($this->fKernelRunlevel == KR_READY)
                    {
                        if (($InstanceID == @IPS_GetInstanceParentID($this->InstanceID)) or ($InstanceID == 0))
                        {
                            if ($this->HasActiveParent())
                            {
                                if  ($this->CheckConfig())
                                {
                                    $this->GetParentData();
                                    if ($this->HMAddress <> '')
                                    {
                                        if ($this->ReadPropertyInteger('Interval') >= 5) $this->SetTimerInterval('ReadHMSysVar', $this->ReadPropertyInteger('Interval'));
                                        $this->ReadSysVars();
                                    }
                                }
                            } else {
                                $this->HMAddress='';
                                if ($this->ReadPropertyInteger('Interval') >= 5) $this->SetTimerInterval('ReadHMSysVar', 0);
                            }
                        }
                    }
                    parent::ProcessInstanceStatusChange($InstanceID,$Status);                    
                }

                public function ApplyChanges()
		{
                    //Never delete this line!
                    parent::ApplyChanges();
                    if ($this->fKernelRunlevel == KR_INIT)
                    {
                        foreach (IPS_GetChildrenIDs($this->InstanceID) as $Child)
                        {
                            $Objekt = IPS_GetObject($Child);
                            if ($Objekt['ObjectType'] <> 2) continue;
                            $Var = IPS_GetVariable($Child);
                            $this->MaintainVariable($Objekt['ObjectIdent'], $Objekt['ObjectName'], $Var['ValueType'], 'HM.SysVar'.$this->InstanceID.'.'.$Objekt['ObjectIdent'], $Objekt['ObjectPosition'], true);        
                    //        MaintainVariable(true,Ident,Name,cVariable.VariableValue.ValueType,'HM.SysVar'+ IntToStr(fInstanceID) +'.'+Ident,ActionHandler);
                            $this->THMSysVarsList[$Child]=$Objekt['ObjectIdent'];
                        }
                    } else {
                        if ($this->CheckConfig())
                        {
                            if ($this->ReadPropertyInteger('Interval') >= 5)	
                            {
                                $this->SetTimerInterval('ReadHMSysVar',$this->ReadPropertyInteger('Interval'));
                            } else {
                                $this->SetTimerInterval('ReadHMSysVar',0);
                            }
                        } else {
                                $this->SetTimerInterval('ReadHMSysVar',0);                    
                        }
                    }
                }	
                
                private function CheckConfig()
                {
                    if ($this->ReadPropertyInteger('Interval') < 0)
                    {
                      $this->SetStatus(202); //Error Timer is Zero
                      return false;
                    }
                    elseif ($this->ReadPropertyInteger('Interval') >= 5)
                    {
                        if ($this->ReadPropertyInteger('EventID') == 0)
                        {
                          $this->SetStatus(IS_ACTIVE); //OK
                        } else {
                            $this->SetStatus(106); //Trigger und Timer aktiv                      
                        }
                    }
                    elseif ($this->ReadPropertyInteger('Interval') == 0)
                    {
                        if ($this->ReadPropertyInteger('EventID') == 0)
                        {
                            $this->SetStatus(IS_INACTIVE); // kein Trigger und Timer aktiv
                        } else {
                            if ($this->ReadPropertyBoolean('EmulateStatus') == true)
                            {
                                        $this->SetStatus(105); //Status emulieren nur empfohlen bei Interval.
                            } else {
                                $parent = IPS_GetParent($this->ReadPropertyInteger('EventID'));
                                if (IPS_GetInstance($parent)['ModuleID'] <> '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
                                {
                                    $this->SetStatus(107);  //Warnung vermutlich falscher Trigger                        
                                } else {  //ist HM Device
                                    if (strpos('BidCoS-RF:',IPS_ReadProperty($parent,'Address'))  === false )
                                    {
                                        $this->SetStatus(107);  //Warnung vermutlich falscher Trigger                        
                                    }else {
                                        $this->SetStatus(IS_ACTIVE); //OK
                                    }
                                }
                            }
                        }
                    } else {
                        $this->SetStatus(108);  //Warnung Trigger zu klein                  
                    }
                    return true;
                }
                
                private function TimerFire()
                {
                    if ($this->HasActiveParent()) $this->ReadSysVars();                
                }
                
                private function GetParentData()
                {
                    $ObjID = @IPS_GetInstanceParentID($this->InstanceID);
                    if ($ObjID <> 0)
                    {
                        $this->HMAddress=IPS_ReadProperty($ObjID,'Host');
                        $this->SetSummary(HMAddress);                        
                    } else {
                        $this->HMAddress='';
                        $this->SetSummary('');                                                
                    }
                }
                
                private function LoadHMScript ($url, $HMScript)
                {
                    if ($this->HMAddress == '')
                    {
                        $this->SendData('Error','CCU Address not set.');
                        $this->IPS_LogMessage(KL_ERROR,'CCU Address not set.');
                        return false;
                    }
                    $ch = curl_init('http://'.$this->HMAddress.':8181/'.$url);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_FAILONERROR,true);
                    curl_setopt($ch, CURLOPT_POST,true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $HMScript);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS,500);        
                    curl_setopt($ch, CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
                    curl_setopt($ch, CURLOPT_TIMEOUT_MS,500);
                    $this->SendData('Request','http://'+ $this->HMAddress + ':8181/'+$url);
                    $this->SendData('Request',$HMScript);
                    $result = curl_exec($ch);
                    curl_close($ch);
                    if ($result === false)
                    {
                        $this->SendData('Response','Failed');
                        $this->LogMessage(KL_WARNING,'CCU unreachable');
                        return false;
                    } else {
                        $this->SendData('Response',$result);
                        return $result;
                    }
                }
                
		private function ReadSysVars()
		{
                    IPS_LogMessage("HomeMaticSystemvariablen", "Dummy-Module");
                }

                /**
		* This function will be available automatically after the module is imported with the module control.
		* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		*/
                public function ReadSystemVariables()
                {
                    if (!$this->HasActiveParent()) throw new Exception("Instance has no active Parent Instance!"); 
                    else $this->ReadSysVars();
                }
                /*
			
			$deviceID = $this->CreateInstanceByIdent($this->InstanceID, $this->ReduceGUIDToIdent($_POST['device']), "Device");
			SetValue($this->CreateVariableByIdent($deviceID, "Latitude", "Latitude", 2), floatval($_POST['latitude']));
			SetValue($this->CreateVariableByIdent($deviceID, "Longitude", "Longitude", 2), floatval($_POST['longitude']));
			SetValue($this->CreateVariableByIdent($deviceID, "Timestamp", "Timestamp", 1, "~UnixTimestamp"), intval(strtotime($_POST['date'])));
			SetValue($this->CreateVariableByIdent($deviceID, $this->ReduceGUIDToIdent($_POST['id']), utf8_decode($_POST['name']), 0, "~Presence"), intval($_POST['entry']) > 0);
			
		}*/
		/*
		private function ReduceGUIDToIdent($guid) {
			return str_replace(Array("{", "-", "}"), "", $guid);
		}
		
		private function CreateCategoryByIdent($id, $ident, $name)
		 {
			 $cid = @IPS_GetObjectIDByIdent($ident, $id);
			 if($cid === false)
			 {
				 $cid = IPS_CreateCategory();
				 IPS_SetParent($cid, $id);
				 IPS_SetName($cid, $name);
				 IPS_SetIdent($cid, $ident);
			 }
			 return $cid;
		}
		
		private function CreateVariableByIdent($id, $ident, $name, $type, $profile = "")
		 {
			 $vid = @IPS_GetObjectIDByIdent($ident, $id);
			 if($vid === false)
			 {
				 $vid = IPS_CreateVariable($type);
				 IPS_SetParent($vid, $id);
				 IPS_SetName($vid, $name);
				 IPS_SetIdent($vid, $ident);
				 if($profile != "")
					IPS_SetVariableCustomProfile($vid, $profile);
			 }
			 return $vid;
		}
		
		private function CreateInstanceByIdent($id, $ident, $name, $moduleid = "{485D0419-BE97-4548-AA9C-C083EB82E61E}")
		 {
			 $iid = @IPS_GetObjectIDByIdent($ident, $id);
			 if($iid === false)
			 {
				 $iid = IPS_CreateInstance($moduleid);
				 IPS_SetParent($iid, $id);
				 IPS_SetName($iid, $name);
				 IPS_SetIdent($iid, $ident);
			 }
			 return $iid;
		}
                */
                // DUMMYS
                private function SetStatus($data)
                {
                    
                }
                private function RegisterTimer($data,$cata)
                {
                    
                }
                private function SetTimerInterval($data,$cata)
                {
                    
                }
		
	
	}
?>