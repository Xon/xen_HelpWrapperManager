<?php
class Sedo_HelpWrapperManager_ViewPublic_Help_Wrapper extends XFCP_Sedo_HelpWrapperManager_ViewPublic_Help_Wrapper
{
	public function renderHtml()
	{
		if(is_callable('parent::renderHtml'))
		{
			parent::renderHtml();
		}
		
		if(!isset($this->_params['pages']))
		{
			return;
		}
		
		$this->_params['pages'] = Sedo_HelpWrapperManager_Helper_Help::manageHelpPages($this->_params['pages']);
	}
}
//Zend_Debug::dump($pagesData);