<?php
/**
 * CrUX Data Report Block
 */
class MM_CruxData_Block_Adminhtml_Report extends Mage_Adminhtml_Block_Template
{
    /**
     * @var MM_CruxData_Helper_Data
     */
    protected $_cruxHelper;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('mm_cruxdata/report.phtml');
        $this->setTitle('CrUX Data Report');

        // Initialize helper
        $this->_cruxHelper = Mage::helper('mm_cruxdata');
    }

    /**
     * Get header text
     *
     * @return string
     */
    public function getHeaderText()
    {
        return $this->__('CrUX Data Report');
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
