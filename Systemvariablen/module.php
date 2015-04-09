<?php
	class HMSysVar extends IPSModule
	{
		private $THMSysVarsList;
		private $HMAddress;
                
		public function __construct($InstanceID)
		{
			//Never delete this line!
			parent::__construct($InstanceID);
			
			//These lines are parsed on Symcon Startup or Instance creation
			//You cannot use variables here. Just static values.
			$this->RegisterPropertyInteger("EventID", 0);
			$this->RegisterPropertyInteger("Interval", 0);
			$this->RegisterPropertyBoolean("EmulateStatus", false);
//open			$this->RegisterTimer("ReadHMSysVar", 0);
		}
                
                public function __destruct()
                {
//open                    $this->SetTimerInterval('ReadHMSysVar', 0);
                    parent::__destruct();                    
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
//open                                        if ($this->ReadPropertyInteger('Interval') >= 5) $this->SetTimerInterval('ReadHMSysVar', $this->ReadPropertyInteger('Interval'));
                                        $this->ReadSysVars();
                                    }
                                }
                            } else {
                                $this->HMAddress='';
//open                                if ($this->ReadPropertyInteger('Interval') >= 5) $this->SetTimerInterval('ReadHMSysVar', 0);
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
                    }
                    if ($this->ReadPropertyInteger('Interval') >= 5)	
                    {
//open                        $this->SetTimerInterval('ReadHMSysVar',$this->ReadPropertyInteger('Interval'));
                    } else {
//open                        $this->SetTimerInterval('ReadHMSysVar',0);
                    }
                }	
                
                private function CheckConfig()
                {
                    if ($this->GetPropertyInteger('Interval') < 0)
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
                                    if (strpos('BidCoS-RF:',IPS_GetProperty($parent,'Address'))  === false )
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
                        $this->HMAddress=IPS_GetProperty($ObjID,'Host');
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
		
	
	}
?>