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

namespace fiftyone\pipeline\cloudrequestengine\tests;

require(__DIR__ . "/../vendor/autoload.php");

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\cloudrequestengine\HttpClient;

use PHPUnit\Framework\TestCase;

class CloudRequestEngineTestsBase extends TestCase {
    
    const expectedUrl = "https://cloud.51degrees.com/api/v4/resource_key.json";
    const jsonResponse = "{\"device\":{\"value\":\"1\"}}";
    const evidenceKeysResponse = "[\"query.User-Agent\"]";
    const accessiblePropertiesResponse =
            "{\"Products\": {\"device\": {\"DataTier\": \"tier\",\"Properties\": [{\"Name\": \"value\",\"Type\": \"String\",\"Category\": \"Device\"}]}}}";
    const invalidKey = "invalidkey";
    const invalidKeyMessage = "58982060: ".CloudResponse::invalidKey." not a valid resource key";
    const invalidKeyResponse = "{ \"errors\":[\"".CloudResponse::invalidKeyMessage."\"]}";
    const noDataKey = "nodatakey";
    const noDataKeyResponse = "{}";
    const noDataKeyMessageComplete = "Error returned from 51Degrees cloud service: 'No data in response " .
        "from cloud service at https://cloud.51degrees.com/api/v4/accessibleProperties?resource=nodatakey'";    
    const accessibleSubPropertiesResponse =
        "{\n" .
        "    \"Products\": {\n" .
        "        \"device\": {\n" .
        "            \"DataTier\": \"CloudV4TAC\",\n" .
        "            \"Properties\": [\n" .
        "                {\n" .
        "                    \"Name\": \"IsMobile\",\n" .
        "                        \"Type\": \"Boolean\",\n" .
        "                        \"Category\": \"Device\"\n" .
        "                },\n" .
        "                {\n" .
        "                    \"Name\": \"IsTablet\",\n" .
        "                        \"Type\": \"Boolean\",\n" .
        "                        \"Category\": \"Device\"\n" .
        "                }\n" .
        "            ]\n" .
        "        },\n" .
        "        \"devices\": {\n" .
        "            \"DataTier\": \"CloudV4TAC\",\n" .
        "            \"Properties\": [\n" .
        "                {\n" .
        "                    \"Name\": \"Devices\",\n" .
        "                    \"Type\": \"Array\",\n" .
        "                    \"Category\": \"Unspecified\",\n" .
        "                    \"ItemProperties\": [\n" .
        "                        {\n" .
        "                            \"Name\": \"IsMobile\",\n" .
        "                            \"Type\": \"Boolean\",\n" .
        "                            \"Category\": \"Device\"\n" .
        "                        },\n" .
        "                        {\n" .
        "                            \"Name\": \"IsTablet\",\n" .
        "                            \"Type\": \"Boolean\",\n" .
        "                            \"Category\": \"Device\"\n" .
        "                        }\n" .
        "                    ]\n" .
        "                }\n" .
        "            ]\n" .
        "        }\n" .
        "    }\n" .
        "}";            
    const resourceKey = "resource_key";
    const userAgent = "iPhone";
    
    protected function propertiesContainName(
            $properties,
            $name) {
        foreach ($properties as $property) {
            if (strcasecmp($property["name"], $name) === 0) {
                return true;
            }
        }
        return false;
    }
    
    protected function mockHttp() {
        $client = $this->createMock(HttpClient::class);
        $client->method("makeCloudRequest")
                ->will($this->returnCallback("fiftyone\pipeline\cloudrequestengine\\tests\CloudRequestEngineTestsBase::getResponse"));
        return $client;
    }
    
    public static function getResponse() {
        $args = func_get_args();
        $url = $args[1];
        if (strpos($url, "accessibleProperties") !== false) {
            if (strpos($url, "subpropertieskey") !== false) {
                return CloudResponse::accessibleSubPropertiesResponse;
            }
            else if (strpos($url, CloudResponse::invalidKey)) {
                throw new CloudRequestException( CloudResponse::invalidKeyResponse );
            }
            else if (strpos($url, CloudResponse::noDataKey)) {
                throw new CloudRequestException( CloudResponse::noDataKeyMessageComplete );
            }
            else {
                return CloudResponse::accessiblePropertiesResponse;
            }
        }
        else if (strpos($url, "evidencekeys") !== false) {
            return CloudResponse::evidenceKeysResponse;
        }
        else if (strpos($url, "resource_key.json") !== false) {
            return CloudResponse::jsonResponse;
        }
        else {
            throw new CloudRequestException( "this should not have been called with the URL '" . $url . "'" );
        }
    }
}
