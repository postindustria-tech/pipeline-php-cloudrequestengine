<?php

namespace fiftyone\pipeline\cloudrequestengine;
class HttpClient {
     /**
     * Internal helper method to make a cloud request
     * uses CURL if available, falls back to file_get_contents
     *
     * @param string url
     * @param string originHeader The value to use for the Origin header
     * @return array associative array with data and error properties
     * error contains any errors from the request, data contains the response
     **/
    public function makeCloudRequest($url, $originHeader)
    {
        $headerText = '';
        if(isset($originHeader)) {
            $headerText = 'Origin: ' . $originHeader;
        }

        if (!function_exists('curl_version')) {
        
            $context = stream_context_create(array(
                'http' => array(
                    'ignore_errors' => true,
                    'header' => $headerText
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
                $error = "Cloud request engine request error";
            }

            return array("data" => $data, "error" => $error);
        };

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        if(isset($originHeader)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                $headerText
            ));
        }
        $data = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $error = null;

        if ($httpCode > 399) {
            $output = json_decode($data);
            $error = implode(",", $output->errors);
        }

        curl_close($ch);

        return array("data" => $data, "error" => $error);
    }
}
