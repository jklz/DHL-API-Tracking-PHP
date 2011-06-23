<?php
/**********************
Class File

**********************/

class dhl_tracking{
	//
	var $_PIuserid	= "911comprep";
	var $_PIpwd		= "DiiC08pR3p";

	var $_PIurl = "";
	var $_PItesturl = "";
	
	var $_PImode = "";
	
	var $_errors = array();
	var $errorFail = false;
	
	var $_xml = null;
	var $_result = null;
	var $_xmlEnd	= "\n";
	
	var $checkAuth = false;
	var $checkReq = false;
	
	function __construct($mode = 'test'){
		//
		$this->_PIurl = "https://xmlpi-ea.dhl.com/XMLShippingServlet";
		$this->_PItesturl = "https://xmlpitest-ea.dhl.com/XMLShippingServlet";
		switch(strtolower($mode)){
			case 'live':
				// we use live mode
				$this->_PImode = "live";
				break;
			case 'test':
			default:
				// we default to test mode
				$this->_PImode = "test";
				break;
			
		}
	}
	//========================================================================================
	// set login info
	//========================================================================================
	function setAuth($userid = NULL,$pwd = NULL){
		if(is_null($userid)){
			$this->logError("auth > UserID", $msg = "user id was not set", true);
		}else{
			$this->_PIuserid = $userid;
		}
		if(is_null($userid)){
			$this->logError("auth > Password", $msg = "Password was not set", true);
		}else{
			$this->_PIpwd = $pwd;
		}
		$this->checkAuth
	}
	
	function logError($loc = "", $msg = "", $fail = false){
		//
		$tmp = array(
					'location' => $loc,
					'message' => $msg,
					'stop' => ((bool)$fail ? "Yes" : "No"),
					'time' => microtime(true)
					);
		if((bool)$fail){
			$this->errorFail = true;
		}
		$this->_errors[] = $tmp;
		$tmp = NULL;
	}
	
	function getErrors(){
		//
		return ($this->_errors);
	}
	
	
	
	//========================================================================================
	// send pi request
	//========================================================================================
	function _sendCallPI(){
		
		if(!$ch=curl_init())
		{
			$this->logError("Send >> Curl", $msg = "Curl is not initialized", true);
			return false;
		}
		else
		{
			if( $this->checkAuth && $this->checkReq && !$this->errorFail ){
				
				$use_url = ($this->_PImode == "test" ? $this->_PItesturl : $this->_PIurl);
				curl_setopt($ch, CURLOPT_URL,$use_url); 
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_xml);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				$this->_result = curl_exec($ch);
				if(curl_error($ch) != "")
				{
					$this->logError("Send >> Curl", $msg = "Error with Curl installation: " . curl_error($ch), true);
					return false;
				}
				else
				{
					curl_close($ch);
					return $this->_result;
				}
			}
		}
	}
	
	
	
	function single($airbill){
		//
		$this->_xml = "";
		$this->_xml .= "<?xml version = '1.0' encoding = 'UTF-8'?>" . $this->_xmlEnd;
		$this->_xml .= "<req:KnownTrackingRequest xmlns:req='http://www.dhl.com' ";
		$this->_xml .= "		xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' ";
		$this->_xml .= "		xsi:schemaLocation='http://www.dhl.com TrackingRequestKnown.xsd'>" . $this->_xmlEnd;
		$this->_xml .= "<Request>" . $this->_xmlEnd;
		$this->_xml .= "<ServiceHeader>" . $this->_xmlEnd;
		$this->_xml .= "<MessageTime>".date("c")."</MessageTime>" . $this->_xmlEnd;
		$this->_xml .= "<MessageReference>".time().$airbill."</MessageReference>" . $this->_xmlEnd;
		$this->_xml .= "<SiteID>" . $this->_PIuserid . "</SiteID>" . $this->_xmlEnd;
		$this->_xml .= "<Password>" . $this->_PIpwd . "</Password>" . $this->_xmlEnd;
		$this->_xml .= "</ServiceHeader>" . $this->_xmlEnd;
		$this->_xml .= "</Request>" . $this->_xmlEnd;
		$this->_xml .= "<LanguageCode>en</LanguageCode>" . $this->_xmlEnd;
		$this->_xml .= "<AWBNumber>" . $airbill . "</AWBNumber>" . $this->_xmlEnd;
		$this->_xml .= "<LevelOfDetails>ALL_CHECK_POINTS</LevelOfDetails>" . $this->_xmlEnd;
		$this->_xml .= "</req:KnownTrackingRequest>" . $this->_xmlEnd;
		$abi = simplexml_load_string($this->_sendCallPI());
		
		$tmp_awb = (string)$abi->AWBNumber;
		$td['awb'] = $tmp_awb;
		
		$td['res']['status'] = (string)$abi->Status->ActionStatus;
		
		$td['event']['time']['date'] = (string)$abi->ShipmentInfo->ShipmentEvent->Date;
		$td['event']['time']['time'] = (string)$abi->ShipmentInfo->ShipmentEvent->Time;
		$td['event']['time']['stamp'] = strtotime($td['event']['time']['date'] . " " . $td['event']['time']['time']);
		//$td['event']['time']['check'] = date("c", $td['event']['time']['stamp']);
		$td['event']['code'] = (string)$abi->ShipmentInfo->ShipmentEvent->ServiceEvent->EventCode;
		
		$tmp_event_desc = (string)$abi->ShipmentInfo->ShipmentEvent->ServiceEvent->Description;
		$tmp_event_desc = preg_replace('/\s\s+/', ' ', $tmp_event_desc);
		$td['event']['desc'] = $tmp_event_desc;
		
		$tmp_loc_desc = (string)$abi->ShipmentInfo->ShipmentEvent->ServiceArea->Description;
		$tmp_loc_desc = preg_replace('/\s\s+/', ' ', $tmp_loc_desc);
		$td['event']['location'] = $tmp_loc_desc;
		
		
		return $td;
	}
	



	function multipul($airbill_in = array()){
		//
		$airbill_in = (array)$airbill_in;
		
		$ab_main = array();
		$ab_sub = array();
		$ab_out = array();
		
		foreach($airbill_in AS $ab_run){
			//
			$ab_sub[] = $ab_run;
			
			if(count($ab_sub) == 8){
				$ab_main[] = $ab_sub;
				$ab_sub = NULL;
				$ab_sub = array();
			}//END -if(count($ab_sub) == 8){
		}//END -foreach($airbill_in AS $ab_run){
		if(count($ab_sub) >= 1){
			$ab_main[] = $ab_sub;
			$ab_sub = NULL;
			$ab_sub = array();
		
		}// END -if(count($ab_sub) >= 1){
		
		if(count($ab_main) < 1){
			// No Info Passed
			$this->logError("Multi >> Count", $msg = "Error with number to track", false);
			return false;
		} //END - if(count($ab_main) < 1){
		
		foreach($ab_main AS $awb_run){
		
			$track_ab = "";
			if(count($awb_run) >= 1 && count($awb_run) <= 10){
			
				foreach($awb_run AS $ab){
					$track_ab .= "<AWBNumber>" . $ab . "</AWBNumber>" . $this->_xmlEnd;
				}//END -foreach($airbill_in AS $ab){
				//now build the output
				$ts = time();
				$req_reference = $ts . $ts . $ts . $ts . $ts . $ts . $ts . $ts . $ts . $ts;
				$req_reference = substr($req_reference, 0, 30);
			
				$req_level = "LAST_CHECK_POINT_ONLY";
				//$req_level = "ALL_CHECK_POINTS";
			
				$this->_xml = "";
				$this->_xml .= "<?xml version = '1.0' encoding = 'UTF-8'?>" . $this->_xmlEnd;
				$this->_xml .= "<req:KnownTrackingRequest xmlns:req='http://www.dhl.com' ";
				$this->_xml .= "		xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' ";
				$this->_xml .= "		xsi:schemaLocation='http://www.dhl.com TrackingRequestKnown.xsd'>" . $this->_xmlEnd;
				$this->_xml .= "<Request>" . $this->_xmlEnd;
				$this->_xml .= "<ServiceHeader>" . $this->_xmlEnd;
				$this->_xml .= "<MessageTime>".date("c")."</MessageTime>" . $this->_xmlEnd;
				$this->_xml .= "<MessageReference>" . $req_reference . "</MessageReference>" . $this->_xmlEnd;
				$this->_xml .= "<SiteID>" . $this->_PIuserid . "</SiteID>" . $this->_xmlEnd;
				$this->_xml .= "<Password>" . $this->_PIpwd . "</Password>" . $this->_xmlEnd;
				$this->_xml .= "</ServiceHeader>" . $this->_xmlEnd;
				$this->_xml .= "</Request>" . $this->_xmlEnd;
				$this->_xml .= "<LanguageCode>en</LanguageCode>" . $this->_xmlEnd;
					// Removed for later possible use
					//$this->_xml .= "<AWBNumber>" . $airbill . "</AWBNumber>" . $this->_xmlEnd;
				//$this->_xml .= "<AWBNumber></AWBNumber>" . $this->_xmlEnd;
				$this->_xml .= $track_ab . $this->_xmlEnd;
				
				$this->_xml .= "<LevelOfDetails>" . $req_level . "</LevelOfDetails>" . $this->_xmlEnd;
				$this->_xml .= "</req:KnownTrackingRequest>" . $this->_xmlEnd;
				$xml = simplexml_load_string($this->_sendCallPI());
				
				if((string)$xml->Response->Status->ActionStatus == "Failure"){
					//is error
					$out_error = array(
									'req' => $this->_xml,
									'res' => $xml
									);
					$this->logError("Multi >> Responce", $out_error, true);
					return false;
				}else{ // MID - if((string)$xml->Response->Status->ActionStatus == "Failure")
					// is Fine
					$tinfo = $xml;
					//here we process the responce
					if(count($tinfo->AWBInfo) >= 1){
						foreach($tinfo->AWBInfo AS $abi){
							$tmp_awb = NULL;
							$tmp_awb = (string)$abi->AWBNumber;
							$td['awb'] = $tmp_awb;
							
							$td['res']['status'] = (string)$abi->Status->ActionStatus;
							
							$td['event']['time']['date'] = (string)$abi->ShipmentInfo->ShipmentEvent->Date;
							$td['event']['time']['time'] = (string)$abi->ShipmentInfo->ShipmentEvent->Time;
							$td['event']['time']['stamp'] = strtotime($td['event']['time']['date'] . " " . $td['event']['time']['time']);
							//$td['event']['time']['check'] = date("c", $td['event']['time']['stamp']);
							$td['event']['code'] = (string)$abi->ShipmentInfo->ShipmentEvent->ServiceEvent->EventCode;
							
							$tmp_event_desc = (string)$abi->ShipmentInfo->ShipmentEvent->ServiceEvent->Description;
							$tmp_event_desc = preg_replace('/\s\s+/', ' ', $tmp_event_desc);
							$td['event']['desc'] = $tmp_event_desc;
							
							$tmp_loc_desc = (string)$abi->ShipmentInfo->ShipmentEvent->ServiceArea->Description;
							$tmp_loc_desc = preg_replace('/\s\s+/', ' ', $tmp_loc_desc);
							$td['event']['location'] = $tmp_loc_desc;
							
							$ab_out[$tmp_awb] = $td;
							$td = null;
						}	
					}
					//$ab_out[] = $xml;
				}//END - if((string)$xml->Response->Status->ActionStatus == "Failure")
			}else{// MID - if(count($airbill_in) >= 1 && count($airbill_in) <= 10){
				$this->logError("Send >> Count", "error (if(count($airbill_in) >= 1 && count($airbill_in) <= 10){)", true);
				return false;
			}// END - if(count($airbill_in) >= 1 && count($airbill_in) <= 10){
		}//END -foreach($ab_main AS $awb_run){
		return($ab_out);
	}//end function
	
	
}
?>