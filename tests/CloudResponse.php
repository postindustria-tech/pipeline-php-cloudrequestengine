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

require(__DIR__ . "/../CloudRequestEngine.php");
require(__DIR__ . "/../CloudEngine.php");

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\cloudrequestengine\HttpClient;

use PHPUnit\Framework\TestCase;

class CloudResponse extends TestCase {
    
    const expectedUrl = "https://cloud.51degrees.com/api/v4/resource_key.json";
    const jsonResponse = "{\"device\":{\"value\":\"1\"}}";
    const evidenceKeysResponse = "[\"query.User-Agent\"]";
    const accessiblePropertiesResponse =
            "{\"Products\": {\"device\": {\"DataTier\": \"tier\",\"Properties\": [{\"Name\": \"value\",\"Type\": \"String\",\"Category\": \"Device\"}]}}}";
    const invalidKey = "invalidkey";
    const invalidKeyMessage = "58982060: ".CloudResponse::invalidKey." not a valid resource key";
    const invalidKeyResponse = "{ \"errors\":[\"".CloudResponse::invalidKeyMessage."\"]}";
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

    
    /**
     * Test cloud request engine adds correct information to post request
     * and returns the response in the ElementData
     */
    public function testProcess() {

        $httpClient = $this->mockHttp();
        
        $engine = new CloudRequestEngine(array(
            "resourceKey" => CloudResponse::resourceKey,
            "httpClient" => $httpClient));

        $builder= new PipelineBuilder();
        $pipeline = $builder
            ->add($engine)
            ->build();
        $data = $pipeline->createFlowData();
        $data->evidence->set("query.User-Agent", CloudResponse::userAgent);

        $data->process();

        $result = $data->getFromElement($engine)->cloud;
        $this->assertEquals(CloudResponse::jsonResponse, $result);

        $jsonObj = \json_decode($result, true);
        $this->assertEquals(1, $jsonObj["device"]["value"]);
    }
    
    /**
     * Verify that the CloudRequestEngine can correctly parse a
     * response from the accessible properties endpoint that contains
     * meta-data for sub-properties.
     */
    public function testSubProperties() {
        
        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine(array(
            "resourceKey" => "subpropertieskey",
            "httpClient" => $httpClient));

        $this->assertEquals(2, count($engine->flowElementProperties));

        
        $deviceProperties = $engine->flowElementProperties["device"];
        $this->assertEquals(2, count($deviceProperties));
        $this->assertTrue($this->propertiesContainName($deviceProperties, "IsMobile"));
        $this->assertTrue($this->propertiesContainName($deviceProperties, "IsTablet"));
        $devicesProperties = $engine->flowElementProperties["devices"];
        $this->assertEquals(1, count($devicesProperties));
        $this->assertTrue(isset($devicesProperties["devices"]));
        $this->assertTrue($this->propertiesContainName($devicesProperties["devices"]["itemproperties"], "IsMobile"));
        $this->assertTrue($this->propertiesContainName($devicesProperties["devices"]["itemproperties"], "IsTablet"));
    }

    
    /** 
     * Test cloud request engine handles errors from the cloud service 
     * as expected.
     * An exception should be thrown by the cloud request engine
     * containing the errors from the cloud service
     * and the pipeline is configured to throw any exceptions up 
     * the stack.
     * We also check that the exception message includes the content 
     * from the JSON response.
     */ 
    public function testValidateErrorHandlingInvalidResourceKey() {

        $httpClient = $this->mockHttp();

        $exception = null;

        try {
            $engine = new CloudRequestEngine(array(
                "resourceKey" => CloudResponse::invalidKey,
                "httpClient" => $httpClient
            ));
        }
        catch (\Exception $ex) {
            $exception = $ex;
        }

        $this->assertNotNull("Expected exception to occur", $exception);
        $this->assertStringContainsString(
                CloudResponse::invalidKeyMessage,
                $exception->getMessage());
    }
    
    private function propertiesContainName(
            $properties,
            $name) {
        foreach ($properties as $property) {
            if (strcasecmp($property["name"], $name) === 0) {
                return true;
            }
        }
        return false;
    }
    
    private function mockHttp() {
        $client = $this->createMock(HttpClient::class);
        $client->method("makeCloudRequest")
                ->will($this->returnCallback("fiftyone\pipeline\cloudrequestengine\\tests\CloudResponse::getResponse"));
        return $client;
    }
    
    public function getResponse() {
        $args = func_get_args();
        $url = $args[0];
        if (strpos($url, "accessibleProperties") !== false) {
            if (strpos($url, "subpropertieskey") !== false) {
                return array("data" => CloudResponse::accessibleSubPropertiesResponse, "error" => null);
            }
            else if (strpos($url, CloudResponse::invalidKey)) {
                return array("data" => null, "error" => CloudResponse::invalidKeyResponse);
            }
            else {
                return array("data" => CloudResponse::accessiblePropertiesResponse, "error" => null);
            }
        }
        else if (strpos($url, "evidencekeys") !== false) {
            return array("data" => CloudResponse::evidenceKeysResponse, "error" => null);
        }
        else if (strpos($url, "resource_key.json") !== false) {
            return array("data" => CloudResponse::jsonResponse, "error" => null);
        }
        else {
            return array(
                "data" => null,
                "error" =>
                "this should not have been called with the URL '"
                . $url . "'");
        }
    }
}
