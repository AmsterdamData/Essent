<?php
set_time_limit(300);
date_default_timezone_set("CET");

    /**
    * Wrapper for Essent SoapAPI to GeoJSON for 'Elektrische oplaadpunten'
    */
    class EssentConnect{
        var $json;
        var $capability, $connector, $identification;
        
        function EssentConnect(){
            $this->capability = Array(
                0 => "Unkown",
                1 => "Slow charge (AC up to 3,7kW)",
                2 => "Easy charge (AC 3,7kW)",
                3 => "Normal charge (AC 11kW)",
                4 => "Fast charge (AC 22kW)",
                5 => "Very fast charge ( DC 50kW)"
            );
            
            $this->connector = Array(
                0 => "Unknown",
                7 => "CHAdeMO",
                8 => "Schuko",
                9 => "CEE 3 polig", 
                10 => "Type 2",
                20 => "CEE 5 polig"
            );
            
            $this->identification = Array(
                0 => "Unknown",
                1 => "No identification",
                2 => "Dutch RFID badge",
                3 => "Dutch RFID fastchargebadge",
                4 => "Essent RFID badge",
                5 => "Essent RFID fastchargebadge",
                6 => "Smartphone app",
                7 => "Greenwheels RFID badge",
                8 => "Taxi RFID badge",
                9 => "SMS"
            );
            
            $this->json = Array();
            $this->json["type"] = "FeatureCollection";
            $json["metadata"] = Array(
                "Generated" => date("Y-m-d H:i:s")
                );
            $json["features"] = Array();            
            
            $this->client = new SoapClient("https://api.essent.nl/partner/a2a/cs/getStaticChargingStations?wsdl");
        }
        
        function getDataAmsterdam(){
            $this->getData(52.282187, 52.431689, 4.738972, 5.079548);
        }
        
        function getData($latmin, $latmax, $lonmin, $lonmax){
            $lat = new stdClass();
            $lat->high = $latmax;
            $lat->low = $latmin;

            $lon = new stdClass();
            $lon->high = $lonmax;
            $lon->low = $lonmin;

            $location = new stdClass();
            $location->Latitude = $lat;
            $location->Longitude = $lon;
            
            $result = $this->client->__call('GetStaticChargingStations_Operation', array(array("Location" => $location)));
            
            if(!$result->Messages->Message->Code == "Succes") exit($result->Messages->Message->Message);
            
            foreach($result->ChargingStations->ChargingStation as $station){
                $feature = Array("type" => "Feature");
                $feature["geometry"] = Array("type" => "Point", "coordinates" => Array((float)$station->Location->Longitude,(float)$station->Location->Latitude));
                $feature["properties"] = (array) $station->Location;
                $feature["properties"]["City"] = (string) $station->Location->City->_;
                $feature["properties"]["CSExternalID"] = (string) $station->CSExternalID;
                $feature["properties"]["Aanbieder"] = (string) $station->Provider;
                $feature["properties"]["Restricties"] = (string) $station->RestrictionsRemark;
                //$feature["points"] = $station->ChargingPoints->ChargingPoint;
                $points = Array();
                $vehicles = Array();
                $capabilities = Array();
                $connectors = Array();
                $identifications = Array();
                
                foreach($station->ChargingPoints->ChargingPoint as $point){
                    $points[$point->CPExternalID] = $point;
                    if(!in_array($point->VehicleType, $vehicles)) $vehicles[] = $point->VehicleType;
                    if(!in_array($this->capability[$point->ChargingCapability], $capabilities)) $capabilities[] = $this->capability[$point->ChargingCapability];
                    if(!in_array($this->connector[$point->ConnectorType], $connectors)) $connectors[] = $this->connector[$point->ConnectorType];
                    if(!in_array($this->identification[$point->IdentificationType], $identifications)) $identifications[] = $this->identification[$point->IdentificationType];
                    
                }
                $feature["properties"]["Type voertuigen"] = implode(", ", $vehicles);
                $feature["properties"]["Aantal laadpunten"] = count($points);
                $feature["properties"]["CPExternalID"] = implode(", ", array_keys($points));
                $feature["properties"]["Capaciteit"] = implode(", ", $capabilities);
                $feature["properties"]["Connector"] = implode(", ", $connectors);
                $feature["properties"]["Identificatie"] = implode(", ", $identifications);
                $this->json["features"][] = $feature;
            }
        }
        
        function getJSON(){
            echo json_encode($this->json);
        }
    }
    
$con = new EssentConnect();
$con->getDataAmsterdam();
$con->getJSON();
?>
