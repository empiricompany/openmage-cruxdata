<?php
/**
 * CrUX Data Helper
 */
class MM_CruxData_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var CruxData_Metrics
     */
    protected $_cruxMetrics = null;
    
    /**
     * Get CrUX Metrics instance
     *
     * @return CruxData_Metrics
     */
    public function getCruxMetrics()
    {
        if ($this->_cruxMetrics === null) {
            $this->_cruxMetrics = new CruxData_Metrics(
                $this->getApiKey(),
                $this->getCustomApiEndpoint()
            );
        }
        
        return $this->_cruxMetrics;
    }
    
    /**
     * Get API key from configuration
     *
     * @return string
     */
    public function getApiKey()
    {
        return Mage::getStoreConfig('mm_cruxdata/general/api_key');
    }
    
    /**
     * Get custom API endpoint from configuration
     *
     * @return string
     */
    public function getCustomApiEndpoint()
    {
        return Mage::getStoreConfig('mm_cruxdata/general/custom_api_endpoint');
    }
    
    /**
     * Set origin URL
     *
     * @param string $origin
     * @return CruxData_Metrics
     */
    public function setOrigin($origin)
    {
        return $this->getCruxMetrics()->setOrigin($origin);
    }
    
    /**
     * Get origin URL
     *
     * @return string
     */
    public function getOrigin()
    {
        return $this->getCruxMetrics()->getOrigin();
    }
    
    /**
     * Fetch CrUX data
     *
     * @return bool
     */
    public function fetchData()
    {
        try {
            $metrics = $this->getCruxMetrics();
            $origin = $this->getOrigin();
            
            if (empty($origin)) {
                return false;
            }

            // Set origin and fetch data from API
            $metrics->setOrigin($origin);
            $result = $metrics->fetchData();
            
            return $result;
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
    }
    
    /**
     * Get form factor distribution
     *
     * @return array
     */
    public function getFormFactorDistribution()
    {
        $metrics = $this->getCruxMetrics();
        return $metrics->getFormFactorDistribution();
    }
    
    /**
     * Get collection period
     *
     * @return string
     */
    public function getCollectionPeriod()
    {
        $metrics = $this->getCruxMetrics();
        return $metrics->getPeriod();
    }
    
    /**
     * Get metric rating
     *
     * @param string $metric
     * @param float $value
     * @return string
     */
    public function getMetricRating($metric, $value)
    {
        $metrics = $this->getCruxMetrics();
        return $metrics->rateMetric($metric, $value);
    }
    
    /**
     * Get metric histogram
     *
     * @param string $formFactor
     * @param string $metric
     * @return array|null
     */
    public function getMetricHistogram($formFactor, $metric)
    {
        $metrics = $this->getCruxMetrics();
        return $metrics->getMetricHistogram($formFactor, $metric);
    }
    
    /**
     * Get form factor percentages
     *
     * @return array
     */
    public function formFactorDistributionPercentages($formFactors = [
        CruxData_Metrics::FORM_FACTOR_PHONE,
        CruxData_Metrics::FORM_FACTOR_TABLET,
        CruxData_Metrics::FORM_FACTOR_DESKTOP
    ])
    {
        $distribution = $this->getFormFactorDistribution();
        $percentages = array();
        
        foreach ($formFactors as $formFactor) {
            $percentages[$formFactor] = isset($distribution[$formFactor]) ?
                round($distribution[$formFactor] * 100) : 0;
        }
        
        return $percentages;
    }
    
    /**
     * Get all metrics values for all form factors
     *
     * @return array
     */
    public function getMetrics($metrics = [
        CruxData_Metrics::METRIC_LCP,
        CruxData_Metrics::METRIC_FCP,
        CruxData_Metrics::METRIC_CLS,
        CruxData_Metrics::METRIC_INP
    ], $formFactors = [
        CruxData_Metrics::FORM_FACTOR_PHONE,
        CruxData_Metrics::FORM_FACTOR_TABLET,
        CruxData_Metrics::FORM_FACTOR_DESKTOP
    ])
    {
        $values = array();
        
        foreach ($formFactors as $formFactor) {
            foreach ($metrics as $metricCode) {
                $value = $this->getCruxMetrics()->getMetricValue($formFactor, $metricCode);
                $values[$formFactor][$metricCode] = $value;
            }
        }
        
        return count($formFactors) == 1 ? reset($values) : $values;
    }

    /**
     * Get form factor title
     * @param string $formFactor
     * @return string
     */
    public function getFormFactorTitle($formFactor)
    {
        switch ($formFactor) {
            case CruxData_Metrics::FORM_FACTOR_PHONE:
                return $this->__('Phone');
            case CruxData_Metrics::FORM_FACTOR_TABLET:
                return $this->__('Tablet');
            case CruxData_Metrics::FORM_FACTOR_DESKTOP:
                return $this->__('Desktop');
            default:
                return '';
        }
    }

    public function getMetricTitle($metricCode)
    {
        switch ($metricCode) {
            case CruxData_Metrics::METRIC_LCP:
                return $this->__('LCP (Largest Contentful Paint)');
            case CruxData_Metrics::METRIC_FCP:
                return $this->__('FCP (First Contentful Paint)');
            case CruxData_Metrics::METRIC_CLS:
                return $this->__('CLS (Cumulative Layout Shift)');
            case CruxData_Metrics::METRIC_INP:
                return $this->__('INP (Interaction to Next Paint)');
            default:
                return '';
        }
    }

    public function getMetricDescription($metricCode)
    {
        switch ($metricCode) {
            case CruxData_Metrics::METRIC_LCP:
                return $this->__('Measures page loading time');
            case CruxData_Metrics::METRIC_FCP:
                return $this->__('Measures the time it takes for the first content to be rendered');
            case CruxData_Metrics::METRIC_CLS:
                return $this->__('Measures visual stability of the page');
            case CruxData_Metrics::METRIC_INP:
                return $this->__('Measures page interactivity');
            default:
                return '';
        }
    }

    /**
     * Get metric format function
     *
     * @param string $metricCode
     * @return callable|null
     */
    public function getMetricValueFormatted($metricCode, $metricValue)
    {
        $formats = array(
            CruxData_Metrics::METRIC_LCP => function($metricValue) { return number_format($metricValue / 1000, 1) . 's'; },
            CruxData_Metrics::METRIC_FCP => function($metricValue) { return number_format($metricValue / 1000, 1) . 's'; },
            CruxData_Metrics::METRIC_CLS => function($metricValue) { return number_format($metricValue, 2); },
            CruxData_Metrics::METRIC_INP => function($metricValue) { return $metricValue . 'ms'; }
        );
        
        return isset($formats[$metricCode]) ? $formats[$metricCode]($metricValue) : null;
    }
    
    /**
     * Get formatted histogram densities
     *
     * @param string $formFactor
     * @param string $metricCode
     * @return array|null
     */
    public function getMetricHistogramFormatted($formFactor, $metricCode)
    {
        $histogram = $this->getMetricHistogram($formFactor, constant('CruxData_Metrics::METRIC_' . $metricCode));
        
        if (!$histogram) {
            return null;
        }
        
        return array(
            'good' => isset($histogram[0]['density']) ? $histogram[0]['density'] * 100 : 0,
            'moderate' => isset($histogram[1]['density']) ? $histogram[1]['density'] * 100 : 0,
            'poor' => isset($histogram[2]['density']) ? $histogram[2]['density'] * 100 : 0
        );
    }
    
    /**
     * Check if CrUX data is available
     *
     * @return bool
     */
    public function hasCruxData()
    {
        $origin = $this->getOrigin();
        if (empty($origin)) {
            return false;
        }
        
        $distribution = $this->getFormFactorDistribution();
        $noData = array_reduce($distribution, function($carry, $value) {
            return $carry && ($value <= 0);
        }, true);
        
        return !$noData;
    }
}