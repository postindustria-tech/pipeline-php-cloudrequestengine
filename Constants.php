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

class Constants {
    // Environment variable to set cloud endpoint
    const FOD_CLOUD_API_URL = "FOD_CLOUD_API_URL";
    // Default base url
    const BASE_URL_DEFAULT = "https://cloud.51degrees.com/api/v4/";

    // No Data in response message to be set in exception when cloud neither
    // return any data nor any error messages
    const MESSAGE_NO_DATA_IN_RESPONSE = "No data in response from cloud service at %s";
	
    // Message when multiple errors are returned from cloud service
    const EXCEPTION_CLOUD_ERRORS_MULTIPLE = 
            "Multiple errors returned from 51Degrees cloud service. See inner " .
            "exceptions for details.";

    // Message when single error is returned from cloud service
    const EXCEPTION_CLOUD_ERROR = 
            "Error returned from 51Degrees cloud service: '%s'";

    // Error message when non-success status is returned.
    const MESSAGE_ERROR_CODE_RETURNED = "Cloud service at '%s' returned status code '%s' with content %s";

    // Evidence key seperator
    const EVIDENCE_SEPERATOR = ".";

    // Used to prefix evidence that is obtained from HTTP headers 
    const EVIDENCE_HTTPHEADER_PREFIX = "header";

    // Used to prefix evidence that is obtained from HTTP bookies 
    const EVIDENCE_COOKIE_PREFIX = "cookie";

    // Used to prefix evidence that is obtained from an HTTP request's
    // query string or is passed into the pipeline for off-line 
    // processing.
    const EVIDENCE_QUERY_PREFIX = "query";

    // other evidence constant
    const EVIDENCE_OTHER = "other";
    
    // warning message to be shown for conflicted evidences
    const WARNING_MESSAGE = "'%s=>%s' evidence conflicts with ";
}