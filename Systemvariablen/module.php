<?
	class HMSysVar extends IPSModule
	{
		private $THMSysVarsList;
		
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
		}
	
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			
			$class_methods = get_class_methods('IPSModule');
			foreach ($class_methods as $method_name) {
				IPS_LogMessage("CLASS",$method_name);
			}
			function format($array)
			{
				return implode('|', array_keys($array)) . "\r\n";
			}
			IPS_LogMessage("VARS",format(get_class_vars('TestCase')));
			
			$vars = get_object_vars($this);
			foreach ($vars as $var) {
				IPS_LogMessage("ObjectVar",$var);
			}
			
			
		/*	$sid = $this->RegisterScript("Hook", "Hook", "<? //Do not delete or modify.\ninclude(IPS_GetKernelDirEx().\"scripts/__ipsmodule.inc.php\");\ninclude(\"../modules/SymconMisc/Geofency/module.php\");\n(new Geofency(".$this->InstanceID."))->ProcessHookData();");
			$this->RegisterHook("/hook/geofency", $sid);*/
		}
		
		
		/**
		* This function will be available automatically after the module is imported with the module control.
		* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		*
		* GEO_ProcessHookData($id);
		*
		*/
		/*public function ReadSysVars()
		{
			IPS_LogMessage("HomeMaticSystemvariablen", "Dummy-Module");
		}*/
		/*
		public function ProcessHookData()
		{
			//workaround for bug
			if(!isset($_IPS))
				global $_IPS;
			if($_IPS['SENDER'] == "Execute") {
				echo "This script cannot be used this way.";
				return;
			}
			if(!isset($_POST['device']) || !isset($_POST['id']) || !isset($_POST['name'])) {
				IPS_LogMessage("Geofency", "Malformed data: ".print_r($_POST, true));
				return;
			}
			
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