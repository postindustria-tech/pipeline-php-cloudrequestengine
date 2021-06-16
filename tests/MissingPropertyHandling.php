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
use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\MissingPropertyMessages;

use PHPUnit\Framework\TestCase;

class MissingPropertyHandling extends TestCase {
    
    /**
     * Test that a cloud response which has a null value for a property is
     * mapped into an AspectPropertyValue with the 'no value reason' set from
     * the 'nullvaluereason' in the cloud response.
     */
    public function testPropertyInResourceNullValue() {
        $engine = new CloudEngine();
        $engine->dataKey = "testElement";

        $cloudRequestEngine = $this->createMock(CloudRequestEngine::class);
        $cloudRequestEngine->method("getProperties")
            ->willReturn($this->cloudProperties);
        $cloudRequestEngine->flowElementProperties = array(
            "testElement" => $this->properties
        );
        $cloudRequestEngine->dataKey = "cloud";
        
        $builder = new PipelineBuilder();

        $pipeline = $builder->add($cloudRequestEngine)->add($engine)->build();

        $flowData = $pipeline->createFlowData();
        
        $this->addResponse($cloudRequestEngine, $flowData, MissingPropertyHandling::nullValueJson);
        
        $engine->aspectProperties = $this->properties;
        $engine->dataKey = "testElement";
        $engine->processInternal($flowData);


        $data = $flowData->get("testElement");
        
        $property2 = $data->get("property2");
        $this->assertTrue($property2 != null);
        $this->assertFalse($property2->hasValue);
        $this->assertEquals(
            MissingPropertyHandling::expectedNullReason,
            $property2->noValueMessage);
    }

    /**
     * Test that a cloud response which has no value for a property throws a
     * PropertyMissingException.
     */
    public function testPropertyNotInResource() {
        $engine = new CloudEngine();
        $engine->dataKey = "testElement";

        $cloudRequestEngine = $this->createMock(CloudRequestEngine::class);
        $cloudRequestEngine->method("getProperties")
            ->willReturn($this->cloudProperties);
        $cloudRequestEngine->flowElementProperties = array(
            "testElement" => $this->properties
        );
        $cloudRequestEngine->dataKey = "cloud";
        
        $builder = new PipelineBuilder();

        $pipeline = $builder->add($cloudRequestEngine)->add($engine)->build();

        $flowData = $pipeline->createFlowData();
        
        $this->addResponse($cloudRequestEngine, $flowData, MissingPropertyHandling::nullValueJson);
        
        $engine->aspectProperties = $this->properties;
        $engine->dataKey = "testElement";
        $engine->processInternal($flowData);


        $data = $flowData->get("testElement");
        
        try {
            $property = $data->get("property3");
            $this->fail();
        } catch (\Exception $ex) {
            $this->assertEquals(
                sprintf(
                    MissingPropertyMessages::PREFIX .
                    MissingPropertyMessages::PROPERTY_NOT_IN_CLOUD_RESOURCE,
                        "property3",
                        "testElement",
                        "testElement",
                        "property1, property2"),
                $ex->getMessage());

        }
    }

    const expectedNullReason = "this is the null reason";

    const nullValueJson =
        "{\n" .
        "  \"testElement\": {\n" .
        "    \"property1\": \"a value\",\n" .
        "    \"property2\": null,\n" .
        "    \"property2nullreason\": \"" . MissingPropertyHandling::expectedNullReason . "\"\n" .
        "  },\n" .
        "  \"javascriptProperties\": []\n" .
        "}";

    private $properties =
        array(
            "property1" => array(
                "name" => "property1",
                "type" => "string",
                "available" => true),
            "property2" => array(
                "name" => "property2",
                "type" => "string",
                "available" => true
            )
        );
    private $cloudProperties =
        array(
            "cloud" => array(
                "name" => "cloud",
                "type" => "string",
                "available" => true
            )
        );

    private function addResponse($cloud, $flowData, $json) {
        $cloudData = new AspectDataDictionary($cloud, ["cloud" => $json]);
        $flowData->setElementData($cloudData);
    }
}
