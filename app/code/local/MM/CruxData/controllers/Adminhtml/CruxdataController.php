<?php
/**
 * CrUX Data Admin Controller
 */
class MM_CruxData_Adminhtml_CruxdataController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('report/mm_cruxdata');
        $this->_title($this->__('Reports'))->_title($this->__('CrUX Data Report'));
        $this->renderLayout();
    }
    
    public function searchAction()
    {
        if (!$this->_validateFormKey()) {
            $this->_getSession()->addError($this->__('Invalid Form Key. Please refresh the page.'));
            $this->_redirect('*/*/index');
            return;
        }
        
        $this->loadLayout();
        $this->_setActiveMenu('report/mm_cruxdata');
        $this->_title($this->__('Reports'))->_title($this->__('CrUX Data Report'));
        
        $origin = $this->getRequest()->getPost('origin');
        
        if ($origin) {
            $contentBlock = $this->getLayout()->getBlock('mm_cruxdata_report_content');
            if ($contentBlock) {
                $contentBlock->setOrigin($origin);
            }
        }
        
        $this->renderLayout();
    }
    
    /**
     * Check ACL permissions
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('report/mm_cruxdata');
    }
}