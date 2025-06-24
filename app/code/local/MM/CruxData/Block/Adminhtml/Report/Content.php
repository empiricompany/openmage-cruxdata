<?php
/**
 * CrUX Data Report Content Block
 */
class MM_CruxData_Block_Adminhtml_Report_Content extends Mage_Adminhtml_Block_Template
{
    /**
     * @var MM_CruxData_Helper_Data
     */
    protected $_cruxHelper;
    
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('mm_cruxdata/report/content.phtml');
        $this->setCacheLifetime(86400);
        
        // Initialize helper
        $this->_cruxHelper = Mage::helper('mm_cruxdata');
    }
    
    /**
     * Set origin URL without fetching data
     *
     * @param string $origin
     * @return MM_CruxData_Block_Adminhtml_Report_Content
     */
    public function setOrigin($origin)
    {
        if (!empty($origin)) {
            $this->_cruxHelper->setOrigin($origin);
        }
        return $this;
    }
    
    /**
     * Get cache key info
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        $origin = $this->getCruxHelper()->getOrigin();
        $date = date('Y-m-d'); // Include date in cache key to refresh daily
        
        return [
            'CRUXDATA_CONTENT',
            Mage::app()->getStore()->getCode(),
            $origin,
            $date,
            $this->getTemplateFile()
        ];
    }
    
    /**
     * Get CrUX data helper
     *
     * @return MM_CruxData_Helper_Data
     */
    public function getCruxHelper()
    {
        return $this->_cruxHelper;
    }
}