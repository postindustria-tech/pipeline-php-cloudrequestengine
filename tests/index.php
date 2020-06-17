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

require(__DIR__ . "/../vendor/autoload.php");

require(__DIR__ . "/../CloudRequestEngine.php");
require(__DIR__ . "/../CloudEngine.php");

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\core\PipelineBuilder;

use PHPUnit\Framework\TestCase;

class CloudRequestEngineTests extends TestCase
{
    public function testCloudRequestEngine()
    {
        $params = array("resourceKey" => $_ENV["RESOURCEKEY"]);

        if ($params["resourceKey"] === "!!YOUR_RESOURCE_KEY!!") {
            $this->fail("You need to create a resource key at " .
            "https://configure.51degrees.com and paste it into the " .
            "phpunit.xml config file, " .
            "replacing !!YOUR_RESOURCE_KEY!!.");
        }

        $cloud = new CloudRequestEngine($params);

        $engine = new CloudEngine();

        $engine->dataKey = "device";

        $cloud->setRestrictedProperties(array("cloud"));

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($cloud)->add($engine)->build();

        $fd = $pipeline->createFlowData();

        $fd->evidence->set("header.user-agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:78.0) Gecko/20100101 Firefox/78.0");

        $result = $fd->process();

        $this->assertEquals($result->device->ismobile->hasValue, true);
    }
}
