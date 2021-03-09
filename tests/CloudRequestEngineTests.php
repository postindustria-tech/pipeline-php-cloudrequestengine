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
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\cloudrequestengine\Constants;
use fiftyone\pipeline\cloudrequestengine\tests\CloudRequestEngineTestsBase;

use PHPUnit\Framework\TestCase;

class CloudRequestEngineTests extends CloudRequestEngineTestsBase {
    const testEndPoint="http://testEndPoint/";
    const testEnvVarEndPoint="http://testEnvVarEndPoint/";

    protected function tearDown(): void {
        $this->assertTrue(putenv(Constants::FOD_CLOUD_API_URL));
    }
    /**
     * Test the explicit setting of cloudEndPoint via constructor take
     * precedence over environment variable settings.
     */
    public function testConfigPrecedenceExplicitSettings() {

        $httpClient = $this->mockHttp();
        
        $this->assertTrue(putenv(Constants::FOD_CLOUD_API_URL .
            "=" .
            CloudRequestEngineTests::testEnvVarEndPoint));

        $engine = new CloudRequestEngine(array(
            "resourceKey" => CloudRequestEngineTests::resourceKey,
            "httpClient" => $httpClient,
            "cloudEndPoint" => CloudRequestEngineTests::testEndPoint));

        $this->assertEquals(
            CloudRequestEngineTests::testEndPoint,
            $engine->baseURL);
    }

    /**
     * Test the environment variable settings of cloud endpoint take 
     * precedence over the default value.
     */
    public function testConfigPrecedenceEnvironmentVariableSettings() {

        $httpClient = $this->mockHttp();
        
        $this->assertTrue(putenv(
            Constants::FOD_CLOUD_API_URL .
            "=" .
            CloudRequestEngineTests::testEnvVarEndPoint));

        $engine = new CloudRequestEngine(array(
            "resourceKey" => CloudRequestEngineTests::resourceKey,
            "httpClient" => $httpClient));

        $this->assertEquals(
            CloudRequestEngineTests::testEnvVarEndPoint,
            $engine->baseURL);
    }

    /**
     * Test that the default end point is used if no other methods is used.
     */
    public function testConfigPrecedenceDefaultSettings() {

        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine(array(
            "resourceKey" => CloudRequestEngineTests::resourceKey,
            "httpClient" => $httpClient));

        $this->assertEquals(
            Constants::BASE_URL_DEFAULT,
            $engine->baseURL);
    }

    /**
     * Test that base URL is appended with slash if does not end with one
     */
    public function testBaseUrlNoSlash() {

        $httpClient = $this->mockHttp();

        $testNoSlashUrl = "http://localhost";

        $engine = new CloudRequestEngine(array(
            "resourceKey" => CloudRequestEngineTests::resourceKey,
            "httpClient" => $httpClient,
            "cloudEndPoint" => $testNoSlashUrl));

        $this->assertEquals(
            $testNoSlashUrl . "/",
            $engine->baseURL);
    }
}
