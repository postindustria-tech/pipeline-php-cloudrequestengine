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

    public $cloudRequestOrigin;

    public $flowElementProperties = array();
    
    private $httpClient;

    /**
     * Constructor for CloudRequestEngine
     *
     * @param array settings
     * Settings should contain a resourceKey
     * and optionally:
     * 1) a cloudEndPoint to overwrite the default baseurl
     * 2) an cloudRequestOrigin to use when sending requests to cloud
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

        if (isset($settings["cloudRequestOrigin"])) {
            $this->cloudRequestOrigin = $settings["cloudRequestOrigin"];
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
        $evidenceKeyRequest = $this->httpClient->makeCloudRequest(
            "GET",
            $this->baseURL . "evidencekeys",
            null, 
            $this->cloudRequestOrigin);

        $evidenceKeys = \json_decode($evidenceKeyRequest, true);

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

        $properties = $this->httpClient->makeCloudRequest(
            "GET",
            $propertiesURL,
            null,
            $this->cloudRequestOrigin);

        $properties = \json_decode($properties, true);
        
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
        $url = $this->baseURL . $this->resourceKey . ".json?";

        $content = http_build_query($this->getContent($flowData));

        $result = $this->httpClient->makeCloudRequest("POST", $url, $content, $this->cloudRequestOrigin);

        $data = new AspectDataDictionary($this, ["cloud" => $result]);

        $flowData->setElementData($data);

        return;
    }

    /**
     * Generate the Content to send in the POST request. The evidence keys
     * e.g. 'query.' and 'header.' have an order of precedence. These are
     * added to the evidence in reverse order, if there is conflict then 
     * the queryData value is overwritten.
     * 
     * 'query.' evidence should take precedence over all other evidence.
     * If there are evidence keys other than 'query.' that conflict then
     * this is unexpected so a warning will be logged.
     * @param FlowData
     * @return Array
     **/
    public function getContent($flowData) {
        
        $queryData = array();

        $evidence = $flowData->evidence->getAll();

        // Add evidence in reverse alphabetical order, excluding special keys. 
        $queryData = $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_OTHER));
        // Add cookie evidence.
        $queryData = $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_COOKIE_PREFIX));
        // Add header evidence.
        $queryData = $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_HTTPHEADER_PREFIX));
        // Add query evidence.
        $queryData = $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_QUERY_PREFIX));

        return $queryData;
    }

    /**
     * Add query data to the evidence.
     * @param queryData The destination Array to add query data to.
     * @param allEvidence All evidence in the flow data. This is used to report 
     * which evidence keys are conflicting.
     * @param evidence Evidence to add to the query Data.
     **/
    public function addQueryData($queryData, $allEvidence, $evidence) {

        foreach ($evidence as $evidenceKey => $evidenceValue) {
            // Get the key parts
            $evidenceKeyParts = explode(Constants::EVIDENCE_SEPERATOR, $evidenceKey);
            $prefix = strtolower($evidenceKeyParts[0]);
            $suffix = strtolower(end($evidenceKeyParts));

            // Check and add the evidence to the query parameters.
            if (!array_key_exists($suffix, $queryData)) {
                $queryData[$suffix] = $evidenceValue;
            }           
            // If the queryParameter exists already.
            else {
                // Get the conflicting pieces of evidence and then log a 
                // warning, if the evidence prefix is not query. Otherwise a
                // warning is not needed as query evidence is expected 
                // to overwrite any existing evidence with the same suffix.
                if (strcmp($prefix, Constants::EVIDENCE_QUERY_PREFIX) !== 0){
                    $conflicts = array();
                    $conflictStr = "";

                    foreach ($allEvidence as $key => $value) {
                        if (strcasecmp($key, $evidenceKey) !== 0 && stripos($key, $suffix) !== false) {
                            $conflicts[$key] = $value;
                        }
                    }
                    $warningMessage = sprintf(Constants::WARNING_MESSAGE, $evidenceKey, $evidenceValue);

                    foreach ($conflicts as $key => $value) {       
                        if(!empty($conflictStr)) {
                            $conflictStr .= ", ";
                        }                
                        $conflictStr .= sprintf("%s=>%s", $key, $value);
                    }
                    if(!empty($conflictStr)) {
                        trigger_error($warningMessage . $conflictStr, E_USER_WARNING);
                    }                  
                }   

            // Overwrite the existing queryParameter value.
            $queryData[$suffix] = $evidenceValue;             
            }
        }
        return $queryData;
    }

    /**
     * Get evidence with specified prefix.
     * @param evidence: All evidence in the flow data.
     * @param type: Required evidence key prefix
     **/
    public function getSelectedEvidence($evidence, $type) {
        
        $selectedEvidence = array();
        if (strcmp($type, Constants::EVIDENCE_OTHER) == 0) {
            foreach ($evidence as $key => $value) {

                if (!$this->keyHasPrefix($key, Constants::EVIDENCE_QUERY_PREFIX) &&
                        !$this->keyHasPrefix($key, Constants::EVIDENCE_HTTPHEADER_PREFIX) && 
                            !$this->keyHasPrefix($key, Constants::EVIDENCE_COOKIE_PREFIX)) {
                                $selectedEvidence[$key] = $value;
                                
                }
            }
            krsort($selectedEvidence);           
        }
        else {
            foreach ($evidence as $key => $value) {
                if ($this->keyHasPrefix($key, $type)) {
                    $selectedEvidence[$key] = $value;
                }
            }
        }
        return $selectedEvidence;
    }

    /**
     * Check that the key of a KeyValuePair has the given prefix.
     * @param itemKey: Key to check
     * @param prefix: The prefix to check for.
     * @return: True if the key has the prefix.
     **/
    public function keyHasPrefix($itemKey, $prefix) {
        $key = explode(Constants::EVIDENCE_SEPERATOR, $itemKey);
        return (strcasecmp($key[0], $prefix) == 0);
    }
}
