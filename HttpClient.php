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
            $error = null;


            if ($data) {
                $json = json_decode($data, true);
                if (isset($json["errors"]) && count($json["errors"])) {
                    $error = implode(",", $json["errors"]);
                }
            } else {
                // If there were no errors but there was also no other data
                // in the response then add an explanation to the list of
                // messages.
                $error = sprintf("No data in response from cloud service at %s", $url);
            }

            return array("data" => $data, "error" => $error);
        };

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
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
        $error = null;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($data) {
            $output = json_decode($data, true);
            if (isset($output["errors"]) && count($output["errors"])) {
                $error = implode(",", $output["errors"]);
            }
        } else if ($httpCode > 399) {
            // If there were no errors returned but the response code was non
            // success then throw an exception.            
            $error = sprintf("Cloud request engine properties list request returned %s", $httpCode);
        }
        else {
            // If there were no errors but there was also no other data
            // in the response then add an explanation to the list of
            // messages.
            $error = sprintf("No data in response from cloud service at %s", $url);
        }

        curl_close($ch);

        return array("data" => $data, "error" => $error);
    }
}
