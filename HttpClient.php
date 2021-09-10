<?php

namespace fiftyone\pipeline\cloudrequestengine;

class HttpClient {
     /**
     * Internal helper method to make a cloud request
     * uses CURL if available, falls back to file_get_contents
     *
     * @param string type Method use to send HTTP request
     * @param string url 
     * @param string content Data to be sent in the post body 
     * @param string originHeader The value to use for the Origin header
     * @return array associative array with data and error properties
     * error contains any errors from the request, data contains the response
     **/
    public function makeCloudRequest($type, $url, $content, $originHeader)
    {
        $headerText = '';
        if(isset($originHeader)) {
            $headerText .= 'Origin: ' . $originHeader;
        }

        if (!function_exists('curl_version')) {
        
            $context = stream_context_create(array(
                'http' => array(
                    'method' => $type,
                    'ignore_errors' => true,
                    'header' =>  $headerText,
                    'content' => $content
                )
            ));

            $data = @file_get_contents($url, false, $context);
            $statusCode = $this->getHttpCode($http_response_header);

            // Validate cloud response for errors.
            $this->validateResponse($data, $statusCode, $http_response_header, $url);
            
            return $data;
        }

        $responseHeaders = array();
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        if(isset($originHeader)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                $headerText
            ));
        }
        if(isset($type) && strcasecmp($type, "POST") == 0) {           
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        }
        else{
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);           
        }

        $data = curl_exec($ch);       
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // Get headers length
        $headerSize = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
        // Get response header and body using header length
        $headerStr = substr( $data , 0 , $headerSize );
        $bodyStr = substr( $data , $headerSize );
        $responseHeaders = explode( "\r\n" , $headerStr );

        // Validate cloud response body for errors.
        $this->validateResponse($bodyStr, $httpCode, $responseHeaders, $url);
        
        curl_close($ch);

        return $bodyStr;
    }

    private function getHttpCode($http_response_header)
    {
        if(is_array($http_response_header))
        {
            $parts=explode(' ', $http_response_header[0]);
            if(count($parts)>1) //HTTP/1.0 <code> <text>
                return intval($parts[1]); //Get code
        }
        return 0;
    }

    /**
     * Parser function to get formatted headers.
     */   
    function parseHeaders( $headers )
    {
        $head = array();
        foreach( $headers as $k=>$v )
        {
            $t = explode( ':', $v, 2 );
            if( isset( $t[1] ) ) {
                $head[ trim($t[0]) ] = trim( $t[1] );
            }                
        }
        return $head;
    }

    private function validateResponse($cloudResponse, $httpStatusCode, $httpResponseHeaders, $url) {

        $message = null;

        if ($cloudResponse) {
            $json = json_decode($cloudResponse, true);

            if (isset($json["errors"]) && count($json["errors"])) {
                $message = implode(",", $json["errors"]);
            } 
            else if ( $httpStatusCode !== 200) {
                // If there were no errors returned but the response code was non
                // success then throw an exception.
                $message = sprintf(Constants::MESSAGE_ERROR_CODE_RETURNED, $url, $httpStatusCode, $cloudResponse);
            }
        } else {
            // If there were no errors but there was also no other data
            // in the response then add an explanation to the list of
            // messages.
            $message = sprintf(Constants::MESSAGE_NO_DATA_IN_RESPONSE, $url);
        }            

        $responseHeaders = null;
        if(isset($message)) {
            // Get the response headers.
            $responseHeaders = $this->parseHeaders($httpResponseHeaders);
            $cloudError = sprintf(Constants::EXCEPTION_CLOUD_ERROR, $message);
            throw new CloudRequestException($cloudError, $httpStatusCode, $responseHeaders);
        }
    }
}
