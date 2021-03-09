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

require(__DIR__ . "/Constants.php");

use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\Engine;

// Engine that makes a call to the 51Degrees cloud service
// Returns raw JSON as a "cloud" property under "cloud" dataKey
class CloudRequestEngine extends Engine
{
    public $dataKey = "cloud";

    // Default base url
    public $baseURL;

    public $flowElementProperties = array();
    
    private $httpClient;

    /**
     * Constructor for CloudRequestEngine
     *
     * @param array settings
     * Settings should contain a resourceKey
     * and optionally a cloudEndPoint to overwrite the default baseurl
     */
    public function __construct($settings)
    {
        if (isset($settings["resourceKey"])) {
            $this->resourceKey = $settings["resourceKey"];
        } else {
            throw new \Exception("CloudRequestEngine needs a resource key");
        }

        if (isset($settings["cloudEndPoint"])) {
            $this->baseURL = $settings["cloudEndPoint"];
        } else {
            // Check if base URL is set via environment variable
            $envVarURL = getenv(Constants::FOD_CLOUD_API_URL);
            if (!empty($envVarURL)) {
                $this->baseURL = $envVarURL;
            } else {
                // Use default if nothing else is set
                $this->baseURL = Constants::BASE_URL_DEFAULT;
            }
        }
        
        // Make sure the base URL end with '/'
        $length = strlen($this->baseURL);
        if ($length > 0 && substr($this->baseURL, $length - 1) != "/" ) {
            $this->baseURL = $this->baseURL . "/";
        }

        if (isset($settings["httpClient"])) {
            $this->httpClient = $settings["httpClient"];
        }
        else {
            $this->httpClient = new HttpClient();
        }

        $this->flowElementProperties = $this->getEngineProperties();

        $this->evidenceKeys = $this->getEvidenceKeys();

        parent::__construct($settings);
    }

    /**
     * Internal function for getting evidence keys used by cloud engines
     *
     * @return array list of keys
     **/
    private function getEvidenceKeys()
    {
        $evidenceKeyRequest = $this->httpClient->makeCloudRequest($this->baseURL . "evidencekeys");

        if ($evidenceKeyRequest["error"] !== null) {
            throw new \Exception("Cloud request engine evidence keys request returned " . $evidenceKeyRequest["error"]);
        }

        $evidenceKeys = \json_decode($evidenceKeyRequest["data"], true);

        return $evidenceKeys;
    }

    /**
     * Instance of EvidenceKeyFilter based on the evidence keys fetched
     * from the cloud service by the private getEvidenceKeys() method
     *
     * @return BasicListEvidenceKeyFilter
     **/
    public function getEvidenceKeyFilter()
    {
        return new BasicListEvidenceKeyFilter($this->evidenceKeys);
    }

    /**
     * Internal method to get properties for cloud engines from the cloud service
     *
     * @return array
     **/
    private function getEngineProperties()
    {

        // Get properties for all engines

        $propertiesURL = $this->baseURL . "accessibleProperties?" . "resource=" . $this->resourceKey;

        $properties = $this->httpClient->makeCloudRequest($propertiesURL);

        if ($properties["error"] !== null) {
            throw new \Exception("Cloud request engine properties list request returned " . $properties["error"]);
        }

        $properties = \json_decode($properties["data"], true);
        
        $properties = $this->LowerCaseArrayKeys($properties);

        $flowElementProperties = array();

        // Change indexes to be by name
        foreach ($properties["products"] as $dataKey => $elementProperties) {
            foreach ($elementProperties["properties"] as $index => $meta) {
                $flowElementProperties[$dataKey][strtolower($meta["name"])] = $meta;
            }
        }

        return $flowElementProperties;
    }

    /**
     * Internal helper method to lowercase keys returned from the
     * cloud service
     *
     * @return array
     **/
    private function lowerCaseArrayKeys($arr)
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                $item = $this->LowerCaseArrayKeys($item);
            }
            return $item;
        }, array_change_key_case($arr));
    }
    
    /**
     * Processing function for the CloudRequestEngine
     * Makes a request to the cloud service with the supplied resource key
     * and evidence and returns a JSON object that is then parsed by cloud engines
     * placed later in the pipeline
     * @param FlowData
     **/
    public function processInternal($flowData)
    {
        $url = $this->baseURL . $this->resourceKey . ".json?&";

        $evidence = $flowData->evidence->getAll();

        // Remove prefix from evidence

        $evidenceWithoutPrefix = array();

        foreach ($evidence as $key => $value) {
            $keySplit = explode(".", $key);

            if (isset($keySplit[1])) {
                $evidenceWithoutPrefix[$keySplit[1]] = $value;
            }
        }

        $url .= http_build_query($evidenceWithoutPrefix);

        $result = $this->httpClient->makeCloudRequest($url);

        if ($result["error"] !== null) {
            throw new \Exception("Cloud engine returned " . $result["error"]);
        }

        $data = new AspectDataDictionary($this, ["cloud" => $result["data"]]);

        $flowData->setElementData($data);

        return;
    }
}
