<?php
/**
 * CrUX API Client
 * 
 * @author MageMEGA
 * @version 1.0
 */

/**
 * CruxData_ApiClient Class
 *
 * Handles API interactions with the Chrome User Experience Report (CrUX) API
 */
class CruxData_ApiClient {
    /**
     * CrUX API base domain
     */
    public const API_BASE_DOMAIN = 'https://chromeuxreport.googleapis.com';
    
    /**
     * CrUX API endpoints
     */
    public const ENDPOINT_QUERY_RECORD = '/v1/records:queryRecord';
    public const ENDPOINT_QUERY_HISTORY_RECORD = '/v1/records:queryHistoryRecord';
    
    /**
     * @var string API key to access CrUX data
     */
    private $apiKey;
    
    /**
     * @var string Custom API endpoint domain
     */
    private $customEndpoint;
    
    /**
     * Constructor
     *
     * @param string $apiKey API key to access CrUX data
     * @param string $customEndpoint Optional custom API endpoint domain
     */
    public function __construct($apiKey, $customEndpoint = null) {
        $this->apiKey = $apiKey;
        $this->customEndpoint = $customEndpoint;
    }
    
    /**
     * Call the CrUX API with the given parameters
     *
     * @param array $params Parameters to send to the API
     * @param string $endpoint API endpoint to use (default: ENDPOINT_QUERY_RECORD)
     * @return array API response or empty array if data not available (404)
     * @throws Exception If an error occurs during the API call (except 404)
     */
    public function callApi($params, $endpoint = self::ENDPOINT_QUERY_RECORD) {
        // Determine which base domain to use
        $baseDomain = !empty($this->customEndpoint) ? $this->customEndpoint : self::API_BASE_DOMAIN;
        
        // Build the complete URL
        if (!empty($this->customEndpoint)) {
            // Use custom endpoint without API key
            $url = $baseDomain . $endpoint;
            if ($this->apiKey) {
                $url .= "?key={$this->apiKey}";
            }
        } else {
            // Use default endpoint with API key
            if (empty($this->apiKey)) {
                return ['error' => 'API key not set', 'status' => 400];
            }
            $url = $baseDomain . $endpoint . "?key={$this->apiKey}";
        }
        
        $data = json_encode($params);
        
        $options = [
            'http' => [
                'header' => [
                    "Content-type: application/json",
                    "Accept: application/json"
                ],
                'method' => 'POST',
                'content' => $data,
                'ignore_errors' => true // Don't throw errors on HTTP error codes
            ]
        ];
        Mage::log("CruxApiClient::callApi - URL: $url, Data: $data", null, 'cruxdata.log');
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        // Check for HTTP response code
        $status_code = 0;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                    $status_code = intval($matches[1]);
                    // If 404, return empty array (data not available)
                    if ($status_code === 404) {
                        return ['error' => 'Data not available', 'status' => 404];
                    }
                    
                    // If not 2xx status code, throw exception
                    if ($status_code < 200 || $status_code >= 300) {
                        $error_msg = "API call failed with status code $status_code: " . ($result ?: 'No response body');
                        throw new Exception($error_msg);
                    }
                    
                    break;
                }
            }
        }
        
        if ($result === false) {
            $error = error_get_last();
            $error_msg = "API call failed: " . ($error['message'] ?? 'Unknown error');
            throw new Exception($error_msg);
        }
        
        $decoded = json_decode($result, true);
        
        if (!is_array($decoded)) {
            $error_msg = 'Failed to decode JSON response: ' . $result;
            throw new Exception($error_msg);
        }
        
        return $decoded;
    }
}