<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2019 51 Degrees Mobile Experts Limited, 5 Charlotte Close,
 * Caversham, Reading, Berkshire, United Kingdom RG4 7BY.
 *
 * This Original Work is licensed under the European Union Public Licence (EUPL) 
 * v.1.2 and is subject to its terms as set out below.
 *
 * If a copy of the EUPL was not distributed with this file, You can obtain
 * one at https://opensource.org/licenses/EUPL-1.2.
 *
 * The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
 * amended by the European Commission) shall be deemed incompatible for
 * the purposes of the Work and the provisions of the compatibility
 * clause in Article 5 of the EUPL shall not apply.
 * 
 * If using the Work as, or as part of, a network application, by 
 * including the attribution notice(s) required under Article 5 of the EUPL
 * in the end user terms of the application under an appropriate heading, 
 * such notice(s) shall fulfill the requirements of that article.
 * ********************************************************************* */



namespace fiftyone\pipeline\cloudrequestengine;

use fiftyone\pipeline\engines\aspectDataDictionary;
use fiftyone\pipeline\engines\engine;

class cloudRequestEngine extends engine {

    public $dataKey = "cloud";

    // Default base url
    public $baseURL = "https://ts.51degrees.com/api/v4/";

    public $licenceKey;
    public $resourceKey;

    public function processInternal($flowData) {

        if(!isset($this->resourceKey)){

            throw new \Exception("51Degrees Cloud Engine needs a resource key");

        }

        $url = $this->baseURL . $this->resourceKey . ".json?" . "license=" . $this->licenceKey . "&";

        $propertiesURL = $this->baseURL . "accessibleProperties?" . "license=" . $this->licenceKey . "&";

        $evidence = $flowData->evidence->getAll();

        // Remove prefix from evidence

        $evidenceWithoutPrefix = array();

        foreach($evidence as $key => $value){

            $keySplit = explode(".", $key);

            if(isset($keySplit[1])){

                $evidenceWithoutPrefix[$keySplit[1]] = $value;

            }

        }

        
        $url .= http_build_query($evidenceWithoutPrefix);
        
        $result = file_get_contents($url);

        // Get properties for subsequent cloud engines

        $properties = file_get_contents($propertiesURL);
        $properties = \json_decode($properties, true);
    
        $data = new aspectDataDictionary($this, ["cloud" => $result, "properties" => $properties]);

        $flowData->setElementData($data);

        return;

    }

    public function setResourceKey($resourceKey){

        $this->resourceKey = $resourceKey;
        
    }
    
    public function setLicenseKey($licenceKey){
        
        $this->licenceKey = $licenceKey;

    }

    public function setBaseURL($baseURL){

        $this->baseURL = $baseURL;        

    }

}

