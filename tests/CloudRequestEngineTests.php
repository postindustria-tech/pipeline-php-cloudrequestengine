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

    /**
     * @after
     */
    protected function tearDowniCloudEndPoint() {
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

    // Data Provider for testGetSelectedEvidence
	public function provider_testGetSelectedEvidence()
    {
        return array(
        array(array("query.User-Agent"=>"iPhone", "header.User-Agent"=>"iPhone"), "query",  array("query.User-Agent" =>"iPhone")),
        array(array("header.User-Agent"=>"iPhone", "a.User-Agent"=>"iPhone", "z.User-Agent"=>"iPhone"), "other",  array("z.User-Agent"=>"iPhone", "a.User-Agent"=>"iPhone"))
        );
    }

    /**
     * Test evidence of specific type is returned from all
     * the evidence passed, if type is not from query, header
     * or cookie then evidences are returned sorted in descending order
     * @dataProvider provider_testGetSelectedEvidence
     */
    public function testGetSelectedEvidence($evidence, $type, $expectedValue) {

        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine(array(
            "resourceKey" => CloudRequestEngineTests::resourceKey,
            "httpClient" => $httpClient));

        $result = $engine->getSelectedEvidence($evidence, $type);
        $this->assertEquals($expectedValue, $result);
    }

    // Data Provider for testGetContent_nowarning
	public function provider_testGetContent_nowarning()
    {
        return array(
            array(array("query.User-Agent" => "query-iPhone", "header.user-agent" => "header-iPhone"), "query-iPhone"),
            array(array("query.User-Agent" => "query-iPhone", "cookie.User-Agent" => "cookie-iPhone"), "query-iPhone"),
            array(array("query.User-Agent" => "query-iPhone", "a.User-Agent" => "a-iPhone"), "query-iPhone")
        );
    }

    /**
     * Test Content to send in the POST request is generated as
     * per the precedence rule of The evidence keys. Verify that query
     * evidence overwrite other evidences without any warning logged.
     * @dataProvider provider_testGetContent_nowarning
     */
    public function testGetContent_nowarning($evidence, $expectedValue) {

        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine(array(
            "resourceKey" => CloudRequestEngineTests::resourceKey,
            "httpClient" => $httpClient));

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($engine)->build();

        $data = $pipeline->createFlowData();

        foreach($evidence as $key => $value){
            $data->evidence->set($key, $value);
        }
        
        $result = $engine->getContent($data);
        $this->assertEquals($expectedValue, $result["user-agent"]);
    }

    // Data Provider for testGetContent_warnings
	public function provider_testGetContent_warnings()
    {
        return array(
            array(array("header.User-Agent" => "header-iPhone", "cookie.User-Agent" => "cookie-iPhone"), "header-iPhone"),
            array(array("a.User-Agent" => "a-iPhone", "b.User-Agent" => "b-iPhone", "z.User-Agent" => "z-iPhone"), "a-iPhone"),
            array(array("query.User-Agent" => "query-iPhone","header.User-Agent" => "header-iPhone", "cookie.User-Agent" => "cookie-iPhone", "a.User-Agent" => "a-iPhone"), "query-iPhone")
        );
    }

    /**
     * Test Content to send in the POST request is generated as
     * per the precedence rule of The evidence keys. These are
     * added to the evidence in reverse order, if there is conflict then 
     * the queryData value is overwritten and warnings are logged.
     * @dataProvider provider_testGetContent_warnings
     */
    public function testGetContent_warnings($evidence, $expectedValue) {

        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine(array(
            "resourceKey" => CloudRequestEngineTests::resourceKey,
            "httpClient" => $httpClient));

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($engine)->build();

        $data = $pipeline->createFlowData();

        foreach($evidence as $key => $value){
            $data->evidence->set($key, $value);
        }
        
        $this->assertEquals($expectedValue, @$engine->getContent($data)["user-agent"]);
        
        try {
            $engine->getContent($data);
        } catch (\Exception $e) {
            $this->assertTrue(strpos($e->getMessage(), "evidence conflicts with") !== false);
        }
    }

    // Data Provider for testGetContent_case_insensitive
	public function provider_testGetContent_case_insensitive()
    {
        return array(
            array(array("query.User-Agent" => "iPhone1", "Query.user-agent" => "iPhone2"), "iPhone2"),
            array(array("a.User-Agent" => "iPhone1", "A.user-agent" => "iPhone2"), "iPhone2")
        );
    }

    /**
     * Test Content to send in the POST request is generated as
     * per the precedence rule of The evidence keys. Verify that 
     * comparison is case insensitive and evidence values will
     * overwritten without any warning logged.
     * @dataProvider provider_testGetContent_case_insensitive
     */
    public function testGetContent_case_insensitive($evidence, $expectedValue) {

        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine(array(
            "resourceKey" => CloudRequestEngineTests::resourceKey,
            "httpClient" => $httpClient));

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($engine)->build();

        $data = $pipeline->createFlowData();

        foreach($evidence as $key => $value){
            $data->evidence->set($key, $value);
        }
        
        $result = $engine->getContent($data);
        $this->assertEquals($expectedValue, $result["user-agent"]);
    }

}

