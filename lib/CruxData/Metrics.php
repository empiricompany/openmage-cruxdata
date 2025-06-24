<?php
/**
 * CrUX Metrics
 * 
 * @author MageMEGA
 * @version 1.0
 */

require_once 'Exception.php';
require_once 'ApiClient.php';

/**
 * CruxData_Metrics Class
 *
 * Manages Chrome User Experience Report (CrUX) metrics
 */
class CruxData_Metrics {
    /**
     * Metric constants
     */
    public const METRIC_FCP = 'FCP'; // First Contentful Paint
    public const METRIC_LCP = 'LCP'; // Largest Contentful Paint
    public const METRIC_CLS = 'CLS'; // Cumulative Layout Shift
    public const METRIC_INP = 'INP'; // Interaction to Next Paint
    
    /**
     * Form factor constants
     */
    public const FORM_FACTOR_PHONE = 'PHONE';
    public const FORM_FACTOR_TABLET = 'TABLET';
    public const FORM_FACTOR_DESKTOP = 'DESKTOP';
    
    /**
     * Rating constants
     */
    public const RATING_GOOD = 'good';
    public const RATING_AVERAGE = 'moderate';
    public const RATING_POOR = 'poor';
    
    /**
     * Metric thresholds (in milliseconds, except CLS which is dimensionless)
     */
    public const THRESHOLD_FCP_GOOD = 2000;
    public const THRESHOLD_FCP_AVERAGE = 4000;
    
    public const THRESHOLD_LCP_GOOD = 2500;
    public const THRESHOLD_LCP_AVERAGE = 4000;
    
    public const THRESHOLD_CLS_GOOD = 0.1;
    public const THRESHOLD_CLS_AVERAGE = 0.25;
    
    public const THRESHOLD_INP_GOOD = 200;
    public const THRESHOLD_INP_AVERAGE = 500;
    
    /**
     * @var CruxData_ApiClient API client
     */
    private $apiClient;
    
    /**
     * @var string Website URL to analyze
     */
    private $origin;
    
    /**
     * @var array Metrics results
     */
    private $results = [];
    
    /**
     * @var string Data collection period
     */
    private $period;
    
    /**
     * @var array Mapping between API names and metric acronyms
     */
    private $metrics = [
        'first_contentful_paint' => self::METRIC_FCP,
        'largest_contentful_paint' => self::METRIC_LCP,
        'cumulative_layout_shift' => self::METRIC_CLS,
        'interaction_to_next_paint' => self::METRIC_INP
    ];
    
    /**
     * @var array Supported form factors
     */
    private $formFactors = [
        self::FORM_FACTOR_PHONE,
        self::FORM_FACTOR_TABLET,
        self::FORM_FACTOR_DESKTOP
    ];
    
    /**
     * Constructor
     *
     * @param string $apiKey API key to access CrUX data
     * @param string $customEndpoint Optional custom API endpoint
     */
    public function __construct($apiKey, $customEndpoint = null) {
        $this->apiClient = new CruxData_ApiClient($apiKey, $customEndpoint);
    }
    
    /**
     * Set the website URL to analyze
     * 
     * @param string $origin Website URL
     * @return CruxData_Metrics
     * @throws CruxData_Exception If the URL is invalid
     */
    public function setOrigin($origin) {
        // Ensure the URL has a scheme
        if (!preg_match('~^https?://~i', $origin)) {
            $origin = 'https://' . $origin;
        }
        
        // Basic URL validation (more permissive than filter_var)
        if (!preg_match('/^https?:\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}(:[0-9]{1,5})?(\/.*)?$/i', $origin)) {
            throw new CruxData_Exception("Invalid URL: $origin");
        }
        
        $this->origin = $origin;
        return $this;
    }

    /**
     * Get origin URL
     * @return string Origin URL
     */
    public function getOrigin() {
        return $this->origin;
    }
    
    /**
     * Fetch all data for all form factors
     * 
     * @return bool True if the fetch was successful, false otherwise
     * @throws CruxData_Exception If the URL is not set or if an error occurs during data fetching
     */
    public function fetchData() {
        if (empty($this->origin)) {
            throw new CruxData_Exception("URL not set. Use setOrigin() before fetchData()");
        }
        
        try {
            // Fetch general data (without form factor) to get form factor distribution
            $generalData = $this->fetchCruxData();
            $formFactorDistribution = [];
            
            // Check if general data is available
            if (isset($generalData['error']) && $generalData['status'] === 404) {
                // No data available for this origin
                return false;
            }
            
            // Extract form factor distribution if available
            if (isset($generalData['record']['metrics']['form_factors']['fractions'])) {
                $formFactorDistribution = $generalData['record']['metrics']['form_factors']['fractions'];
            }
            
            // Fetch data for each form factor
            foreach ($this->formFactors as $formFactor) {
                $data = $this->fetchCruxData($formFactor);
                
                // Skip if data not available for this form factor
                if (isset($data['error']) || !isset($data['record']['metrics'])) {
                    continue;
                }
                
                $this->results[$formFactor] = $data['record']['metrics'];
                
                // Add form factor distribution
                $this->results[$formFactor]['form_factors'] = 
                    isset($formFactorDistribution[strtolower($formFactor)]) ? 
                    $formFactorDistribution[strtolower($formFactor)] : 0;
                
                // Save collection period (only once)
                if (empty($this->period) && isset($data['record']['collectionPeriod'])) {
                    $this->period = $this->formatPeriod($data['record']['collectionPeriod']);
                }
            }
            
            return !empty($this->results);
        } catch (Exception $e) {
            throw new CruxData_Exception("Error fetching data: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Get all metrics results
     * 
     * @return array Metrics results
     */
    public function getResults() {
        return $this->results;
    }
    
    /**
     * Set metrics results directly
     *
     * @param array $results Metrics results
     * @return void
     */
    public function setResults($results) {
        $this->results = $results;
    }
    
    /**
     * Get data collection period
     * 
     * @return string Data collection period
     */
    public function getPeriod() {
        return $this->period ?? '';
    }
    
    /**
     * Get the value of a specific metric for a form factor
     * 
     * @param string $formFactor Form factor (PHONE, TABLET, DESKTOP)
     * @param string $metric Metric (FCP, LCP, CLS, INP)
     * @return float|null Metric value or null if not available
     */
    public function getMetricValue($formFactor, $metric) {
        $apiMetric = array_search($metric, $this->metrics);
        
        if ($apiMetric === false || !isset($this->results[$formFactor][$apiMetric]['percentiles']['p75'])) {
            return null;
        }
        
        $value = $this->results[$formFactor][$apiMetric]['percentiles']['p75'];
        return (float) str_replace(',', '.', $value);
    }
    
    /**
     * Get the histogram of a specific metric for a form factor
     * 
     * @param string $formFactor Form factor (PHONE, TABLET, DESKTOP)
     * @param string $metric Metric (FCP, LCP, CLS, INP)
     * @return array|null Metric histogram or null if not available
     */
    public function getMetricHistogram($formFactor, $metric) {
        $apiMetric = array_search($metric, $this->metrics);
        
        if ($apiMetric === false || !isset($this->results[$formFactor][$apiMetric]['histogram'])) {
            return null;
        }
        
        return $this->results[$formFactor][$apiMetric]['histogram'];
    }
    
    /**
     * Get form factor distribution
     * 
     * @return array Form factor distribution
     */
    public function getFormFactorDistribution() {
        $distribution = [];
        
        foreach ($this->formFactors as $formFactor) {
            $distribution[$formFactor] = isset($this->results[$formFactor]['form_factors']) ? 
                $this->results[$formFactor]['form_factors'] : 0;
        }
        
        return $distribution;
    }
    
    /**
     * Rate a metric (good, average, poor)
     * 
     * @param string $metric Metric (FCP, LCP, CLS, INP)
     * @param float $value Metric value
     * @return string Rating (good, average, poor)
     * @throws CruxData_Exception If the metric is not supported
     */
    public function rateMetric($metric, $value) {
        switch ($metric) {
            case self::METRIC_FCP:
                if ($value < self::THRESHOLD_FCP_GOOD) {
                    return self::RATING_GOOD;
                }
                if ($value < self::THRESHOLD_FCP_AVERAGE) {
                    return self::RATING_AVERAGE;
                }
                return self::RATING_POOR;
                
            case self::METRIC_LCP:
                if ($value < self::THRESHOLD_LCP_GOOD) {
                    return self::RATING_GOOD;
                }
                if ($value < self::THRESHOLD_LCP_AVERAGE) {
                    return self::RATING_AVERAGE;
                }
                return self::RATING_POOR;
                
            case self::METRIC_CLS:
                if ($value < self::THRESHOLD_CLS_GOOD) {
                    return self::RATING_GOOD;
                }
                if ($value < self::THRESHOLD_CLS_AVERAGE) {
                    return self::RATING_AVERAGE;
                }
                return self::RATING_POOR;
                
            case self::METRIC_INP:
                if ($value < self::THRESHOLD_INP_GOOD) {
                    return self::RATING_GOOD;
                }
                if ($value < self::THRESHOLD_INP_AVERAGE) {
                    return self::RATING_AVERAGE;
                }
                return self::RATING_POOR;
                
            default:
                throw new CruxData_Exception("Unsupported metric: $metric");
        }
    }
    
    /**
     * Fetch CrUX data with optional form factor
     *
     * @param string|null $formFactor Optional form factor (PHONE, TABLET, DESKTOP)
     * @param string $endpoint API endpoint to use (default: ENDPOINT_QUERY_RECORD)
     * @return array Fetched data
     * @throws Exception If an error occurs during data fetching
     */
    private function fetchCruxData($formFactor = null, $endpoint = CruxData_ApiClient::ENDPOINT_QUERY_RECORD) {
        $params = [
            "origin" => $this->origin
        ];
        
        // Add form factor if provided
        if ($formFactor !== null) {
            $params["formFactor"] = $formFactor;
        }
        
        return $this->apiClient->callApi($params, $endpoint);
    }
    
    /**
     * Format data collection period
     * 
     * @param array $period Data collection period
     * @return string Formatted period
     */
    private function formatPeriod($period) {
        $firstTimestamp = mktime(0, 0, 0,
            $period['firstDate']['month'],
            $period['firstDate']['day'], 
            $period['firstDate']['year']
        );
        $lastTimestamp = mktime(0, 0, 0,
            $period['lastDate']['month'],
            $period['lastDate']['day'],
            $period['lastDate']['year']
        );
        
        $dateFormat = Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM;
        return Mage::helper('core')->formatDate($firstTimestamp, $dateFormat) . 
               ' - ' . 
               Mage::helper('core')->formatDate($lastTimestamp, $dateFormat);
    }
}