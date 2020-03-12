<?php

//******************************************************************************
//	Name		:	LibreHardwareMonitor Modul
//	Info		:	
//
//******************************************************************************



	//******************************************************************************
	//	Klassendefinition
	//******************************************************************************
	class LibreHardwareMonitor extends IPSModule
		{

		//******************************************************************************
		//	Überschreibt die interne IPS_Create($id) Funktion
		//******************************************************************************        
		public function Create() 
			{

			$this->RegisterPropertyInteger("Intervall", 10);

			$this->RegisterPropertyString("URL", "");

			$this->RegisterTimer("LHM_UpdateTimer", 10, 'LHM_Update($_IPS["TARGET"]);');

            // Diese Zeile nicht löschen.
            parent::Create();

        	}

        
   		//**************************************************************************
		// Überschreibt die intere IPS_ApplyChanges($id) Funktion
		//**************************************************************************        
		public function ApplyChanges() 
			{

        	//Timer stellen
			$interval = $this->ReadPropertyInteger("Intervall") ;
			$this->SetTimerInterval("LHM_UpdateTimer", $interval*1000);

			$this->SetStatus(102);

			// Diese Zeile nicht löschen
            parent::ApplyChanges();
        	}

   		//**************************************************************************
		// manuelles Holen der Daten oder ueber Timer
		//**************************************************************************
		public function Update()
			{
	
			$apikey = $this->GetAPIKey();

			$deviceID = $this->GetDeviceID();

			$url = "https://home.sensibo.com/api/v2/pods/".$deviceID."?fields=*&apiKey=".$apikey;

			$this->SendDebug(__FUNCTION__,$url,0);

    		$resultcurl = $this->DoCurl($url);

    		$this->SendDebug(__FUNCTION__,"Result : " .$resultcurl,0);

			$result = json_decode($resultcurl,true);

			$ok = $this->CheckResult($result);	
			if ( $ok == false )
				return false;
				
			$this->DoDatas($result);	
				
			}

		//**************************************************************************
		//
		//**************************************************************************
		protected function GetAPIKey()
			{
			$apikey = $this->ReadPropertyString("APIKey") ;
			return $apikey;	
			}


		//**************************************************************************
		//
		//**************************************************************************
		protected function DoDatas($result)
			{
			
			$status = false;	
			if ( isset($result['result']))
					$result = $result['result'];
			else
				return false;
			

			$keys = array( 	array('macAddress',"MAC Adresse",0), 
							array('isGeofenceOnExitEnabled',"Geofency On Exit",0,"~Switch"),
							array("currentlyAvailableFirmwareVersion","Firmware verfuegbar",0),
							array("cleanFiltersNotificationEnabled","Filterbenachrichtigung",0,"~Switch"),
							array("id","Device ID",0),
							array("firmwareVersion","Aktuelle Firmware",0),
							array("roomIsOccupied","Raum ist belegt",0,"~Switch"),
							array("firmwareType","Firmware Type",0),
							array("productModel","Modell",0),
						);
			$this->DoKeys($result,$keys,"");
			
			$acstate = @$result['acState'];

			$keys = array( 	array('on','AC Status',0,"~Switch"),
							array('fanLevel','Luefter Level',0),
							array("temperatureUnit",'Temperatureinheit',0),
							array("targetTemperature",'Soll Temperatur',0),
							array("mode",'Modus',0),
							array("swing",'Swing',0),
							
						);
			$this->DoKeys($acstate,$keys,"acState");

			$connectionStatus = @$result['connectionStatus'];

			$keys = array( 	array('isAlive','Verbindung Status',0,"~Alert.Reversed"),
					
						);
			$this->DoKeys($connectionStatus,$keys,"connectionStatus");

			$connectionStatus = @$connectionStatus['lastSeen'];

			$keys = array( 	array('secondsAgo','Letzte Verbindung in Sekunden',0),
							array('time','Letzte Verbindung',1,"~UnixTimestamp"),
						);
			$this->DoKeys($connectionStatus,$keys,"connectionStatus");


			}	

		
		//******************************************************************************
		// Auswertung der Keys
		//******************************************************************************
		protected function DoKeys($result,$keys,$prefix)
			{

			foreach ($keys as $key) 
				{
				$value = $this->LookingForkey($result,$key[0],$status);

				if ( $status == true )
					{
					$profil = "";	
					$ident = $prefix.$key[0];
					$name = $key[1];

					if ( $key[2] == 1 )
						{
						$value = strtotime($value);	
						$profil = $key[3];
						}	

					if ( isset ($key[3]) )
						$profil = $key[3];

					$this->SetValueToVariable($name,$value,$ident,$profil);	
					}	
				else
					{
					$this->SendDebug(__FUNCTION__, "Key nicht gefunden : ".$key[0], 0);	
					}	
				}	
	
			}

		
		//******************************************************************************
		// Finde Keys im Result
		//******************************************************************************
		protected function LookingForKey($result,$key,&$status)
			{
		
			$s = @$result[$key];	
			if ( isset($s) ) 
				{
				$value = $s;	
				// $this->SendDebug(__FUNCTION__, "Key: ".$key . " Value: ".$value, 0);
				$status = true;
				return $value;
				}
			else
				{
				// $this->SendDebug(__FUNCTION__, "Nicht gefunden Key: ".$key, 0);
				$status = false ;
				}	
			}
			
			
		//******************************************************************************
		// Gefundene Werte in Variable schreiben (erstellen/Profil)
		//******************************************************************************
		protected function SetValueToVariable($name,$value,$ident,$profil=false)
			{
			$this->SendDebug(__FUNCTION__, "Name:" . $name ." Wert:".$value . " Ident: ".$ident." - ".$profil, 0);

			$VariableID = @IPS_GetObjectIDByIdent ($ident,$this->InstanceID);
			if ( $VariableID != false )	
				$this->SendDebug(__FUNCTION__, "Ident OK :" . $VariableID . " Ident: ".$ident, 0);
			else	
				$this->SendDebug(__FUNCTION__, "Ident NOK :" . $VariableID . " Ident: ".$ident, 0);
			
			$array = IPS_GetVariable ($VariableID);
			$aktprofil = $array['VariableCustomProfile'];
			
			if (is_string($value) == true) 
				{ 
				if ( $VariableID == false )
					$VariableID = $this->RegisterVariableString($ident, $name);
				SetValue($VariableID,$value);
				}
				
			if (is_bool($value) == true) 
				{
				if ( $VariableID == false )
					$VariableID = $this->RegisterVariableBoolean($ident,$name);

				if ( $profil != false )
					{	
					if ($profil != $aktprofil) 
						{
                        $this->SendDebug(__FUNCTION__, "Profilaenderung :" . $VariableID . " Profil: [".$profil."]", 0);
                        $status = IPS_SetVariableCustomProfile($VariableID, $profil);
						if ($status == false) 
							{
                            $this->SendDebug(__FUNCTION__, "Profilaenderung NOK :" . $VariableID . " Profil: ".$profil, 0);
                        	}
                    	}
					
					}	


					SetValue($VariableID,$value);
				}	

			if (is_integer($value) == true) 
				{ 
				if ( $VariableID == false )
					$VariableID = $this->RegisterVariableInteger($ident, $name);

				if ( $profil != false )
					{
					
					if ($profil != $aktprofil) 
						{
                        $this->SendDebug(__FUNCTION__, "Profilaenderung :" . $VariableID . " Profil: [".$profil."]", 0);
                        $status = IPS_SetVariableCustomProfile($VariableID, $profil);
						if ($status == false) 
							{
                            $this->SendDebug(__FUNCTION__, "Profilaenderung NOK :" . $VariableID . " Profil: ".$profil, 0);
                        	}
                   		}	
					}	

				SetValue($VariableID,$value);
				}


            }	

		//**************************************************************************
		// DeviceID von Instanz zurueck geben
		//**************************************************************************
		public function GetDeviceID()
			{

			return "PEyHtr9U";	
			}

			
		//**************************************************************************
		// ueberpruefe Result ob status auf success
		//**************************************************************************
		protected function CheckResult(array $result)
			{
			$status = false;	
			if ( isset($result['status']))
				$status = $result['status'];

			if ( $status != 'success' )
				{
				$this->SendDebug(__FUNCTION__,"Success NOK : " ,0);
				return false;	
				}	
			
			$this->SendDebug(__FUNCTION__,"Success OK : " ,0);
			return true;	

			}


		//**************************************************************************
		// Instanz loeschen
		//**************************************************************************
		public function Destroy()
			{
			$this->UnregisterTimer("SSB_UpdateTimer");

			//Never delete this line!
			parent::Destroy();
			}
		
		//**************************************************************************
		// Timer loeschen
		//**************************************************************************    
		protected function UnregisterTimer($Name)
			{
			$id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
			if ($id > 0)
				{
				if (!IPS_EventExists($id))
					throw new Exception('Timer not present', E_USER_NOTICE);
				IPS_DeleteEvent($id);
				}
			}


		//******************************************************************************
		//	AC Mode Umschalten
		//******************************************************************************
    	public function SetACMode(string $mode)
    		{
    			
    		}

		//******************************************************************************
		//	AC Fan Level Umschalten
		//******************************************************************************
    	public function SetACFaneLevel(string $mode)
    		{
    			
    		}

		//******************************************************************************
		//	AC Swing Umschalten
		//******************************************************************************
    	public function SetACSwing(string $mode)
    		{
    			
    		}

		//******************************************************************************
		//	AC Solltemperatur
		//******************************************************************************
    	public function SetACTemperatur(int $temperatur)
    		{
    			
    		}

		//******************************************************************************
		//	AC Ein/Ausschalten
		//******************************************************************************
    	public function SetACState(bool $status)
			{
			if ($status == true )
				$msg = "AN";
			else
				$msg = "AUS";

			$this->SendDebug(__FUNCTION__,": ".$msg ,0);
		
			$apikey = $this->GetAPIKey();
			$deviceID = $this->GetDeviceID();


			$postfields = json_encode(
								array(	'on'=>false,
										'mode'=> "auto",
										'fanLevel' => "auto",
										"targetTemperature" => 22,
									)
									);


			$postfields = json_encode(
								array(	'newValue'=>true
									)
									);

			$postfields = '"{acState": {"on": "true", "swing": "stopped", "mode": "fan", "fanLevel": "low", "targetTemperature":"22"}}';						

			$url = "https://home.sensibo.com/api/v2/pods/".$deviceID."/acStates/on?&apiKey=".$apikey;
			$url = "https://home.sensibo.com/api/v2/pods/".$deviceID."/acStates?&apiKey=".$apikey;
	
			// $resultcurl = $this->DoCurlPATCH($url,$postfields);
			$resultcurl = $this->DoCurlPOST($url,$postfields);

    		$this->SendDebug(__FUNCTION__,"Result : " .$resultcurl,0);

			$result = json_decode($resultcurl,true);

									// "acState": {"on": true, "swing": "stopped", "mode": "fan", "fanLevel": "low"}

        	}



   		//******************************************************************************
		//	Curl GET Abfrage ausfuehren
		//******************************************************************************
		function DoCurl(string $url,bool $debug=false)
			{
			$curl = curl_init($url);
			curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
			$output = curl_exec($curl);
			curl_close($curl);
			return $output;
			}

   		//******************************************************************************
		//	Curl POST Abfrage ausfuehren
		//******************************************************************************
		function DoCurlPOST(string $url,$postfields,bool $debug=false)
			{
			$this->SendDebug(__FUNCTION__,"URL:  " .$url,0);

			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS,$postfields);
			curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
			$output = curl_exec($curl);
			curl_close($curl);
			return $output;
			}

		//******************************************************************************
		//	Curl PATCH Abfrage ausfuehren
		//******************************************************************************
		function DoCurlPATCH(string $url,$postfields,bool $debug=false)
			{
			$this->SendDebug(__FUNCTION__,"URL:  " .$url,0);
			$this->SendDebug(__FUNCTION__,"FIELDS:  " .$postfields,0);
			
			$headers = array('Content-Type: application/json');
			$curl = curl_init($url);
			curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
			curl_setopt($curl, CURLOPT_POSTFIELDS,$postfields);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			$output = curl_exec($curl);
			curl_close($curl);
			return $output;
			}


    	}