<?php

/**
 * Created by PhpStorm.
 * User: matti
 * Date: 2018-05-21
 * Time: 09:24
 *
*/
class postnordWebservice
{
    function __construct($apikey,$language ="SV",$log = false){
        $this->curlBase = "https://api2.postnord.com/rest/";
        $this->apiKey = $apikey;

        $this->language = $language;
        $this->shouldLog = false;
        $this->logger = new WC_Logger();
        if($log === "1"){

            $this->shouldLog = true;
            $this->SaveLog("Created postnordService");
        }

    }
    /***
     * @param $shipmentId What is the shipment ID we are looking for?
     * @return array|string
     */
    public function GetByShipmentId($shipmentId){
        ///
        $response = $this->MakeCurl("shipment/v1/trackandtrace/findByIdentifier.json?id=".$shipmentId."&locale=".$this->language."&apikey=".$this->apiKey);
        if(isset($response->TrackingInformationResponse->shipments[0]->items[0]->events)){
            return $this->cleanResponse($response->TrackingInformationResponse->shipments[0]->items[0]->events);
        }
        return 0;
    }
    private function MakeCurl($relativeUrl)
    {

       $url = $this->curlBase . $relativeUrl;
        if($this->shouldLog){
            $this->SaveLog("making curl to ".$url);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        $output = curl_exec($ch);
        curl_close($ch);
        if(curl_error($ch))
        {
            if($this->shouldLog){
                $this->SaveLog('CURL error:' . curl_error($ch));
            }
        }
        return json_decode($output);
    }
    /***
     * Gets you a result only on your shipments. ! Requires API Credentials !
     * @param $shipmentId
     * @return array
     */
    public function GetShipmentByReference($reference){
        $client = $this->getSoapClient();
        $client->__setSoapHeaders($this->CreateLoginHeaders());
        $xml = $this->makeSoapCall($client,'GetConsignmentsByReference',$this->GetPayloadForReference($reference));
        $history = ($xml->Body->GetConsignmentsByReferenceResponse->consignment->eventHistory);
        $destination = $xml->Body->GetConsignmentsByReferenceResponse->consignment->consigneeName;
        return $this->cleanResponse($history,$destination);
    }
    private function GetPayloadForReference($ref){
        $wrapper = new StdClass;
        $wrapper->responseLocale = new SoapVar($this->language,XSD_STRING);
        $wrapper->referenceData = new stdClass();
        $wrapper->referenceData->reference = new SoapVar($ref, XSD_STRING);
        $wrapper->referenceData->referenceType = new SoapVar("ALL", XSD_STRING);
        $params = new SoapVar($wrapper,XSD_ANYTYPE);
        return array($params);
    }

    private function SaveLog($message){
        $message = str_replace($this->apiKey,'XXXXXXXX',$message); // make sure to not log the key
        $this->logger->debug($message,array( 'source' => 'postnord-tracking-form' ));
    }

    /***
     * @param $history All the event-history
     * @return array a new clean array with only the date, time and description
     */
    private function cleanResponse($events){
        $cleanData = array();
        for($i = count($events)-1; $i >=0; $i--){
            $location = (string)$events[$i]->location->displayName;
            $datetime = (string)$events[$i]->eventTime;
            $descr = (string)$events[$i]->eventDescription;

            $cleanData[] = array(
                "datetime" => $datetime,
                "descr" => $descr,
                "location" => $location
            );
        }
        return $cleanData;
    }
    /***
     * @param $xmlstring The string to be cleaned
     * @return mixed|string The properly cleaned string
     */
    private function cleanXML($xmlstring){
        $xmlstring = substr($xmlstring,strpos($xmlstring,"<soap:"));
        $xmlstring = substr($xmlstring,0,strpos($xmlstring,":Envelope>")+10);
        $xmlstring = str_ireplace(['SOAP-ENV:', 'SOAP:','ns2:'], '', $xmlstring);
        return $xmlstring;
    }

    /***
     * @return SoapHeader Gettings you the proper login headers to consume the API
     */
    private function CreateLoginHeaders(){
        //Check with your provider which security name-space they are using.
        $strWSSENS = "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd";
        $objSoapVarUser = new SoapVar($this->user, XSD_STRING,null,$strWSSENS,null,$strWSSENS);
        $objSoapVarPass =new SoapVar('<ns2:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">' . $this->pass . '</ns2:Password>', XSD_ANYXML );
        $objWSSEAuth = new clsWSSEAuth($objSoapVarUser, $objSoapVarPass);
        $objSoapVarWSSEAuth = new SoapVar($objWSSEAuth, SOAP_ENC_OBJECT, NULL, $strWSSENS, 'UsernameToken', $strWSSENS);
        $objWSSEToken = new clsWSSEToken($objSoapVarWSSEAuth);
        $objSoapVarWSSEToken = new SoapVar($objWSSEToken, SOAP_ENC_OBJECT, NULL, $strWSSENS, 'UsernameToken', $strWSSENS);
        $objSoapVarHeaderVal=new SoapVar($objSoapVarWSSEToken, SOAP_ENC_OBJECT, NULL, $strWSSENS, 'Security', $strWSSENS);
        $objSoapVarWSSEHeader = new SoapHeader($strWSSENS, 'Security', $objSoapVarHeaderVal,true);
        if( $this->shouldLog) {
            $this->SaveLog("Created login headers ");
            $this->SaveLog(print_r($objSoapVarWSSEHeader,true));
        }
        return $objSoapVarWSSEHeader;
    }
}