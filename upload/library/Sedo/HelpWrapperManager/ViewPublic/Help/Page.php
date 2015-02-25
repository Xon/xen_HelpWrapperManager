<?php
class Sedo_HelpWrapperManager_ViewPublic_Help_Page extends XFCP_Sedo_HelpWrapperManager_ViewPublic_Help_Page 
{
	public function renderHtml()
	{
		parent::renderHtml();

		if(empty($this->_params['templateHtml']))
		{
			return;
		}

		if(strpos($this->_params['templateHtml'], '{childrenList}') === false)
		{
			return;
		}

		$settings = XenForo_Application::get('options')->get('sedo_helpwrapper_settings');

		$page = $this->_params['page'];
		$parentPageId = $page['page_id'];
		
		if(!isset($settings[$parentPageId]))
		{
			return;
		}

		$children = array();
		
		foreach($settings as $pageId => $pageData)
		{
			if($pageData['parent'] == $parentPageId)
			{
				$children[$pageId] = $pageData;
			}
		}; 

		if(empty($children))
		{
			return;
		}

		$helpModel = XenForo_Model::create('XenForo_Model_Help');

		$defaultPages = array('smilies'=>'', 'bbCodes'=>'', 'trophies'=>'', 'cookies'=>'', 'terms'=>'');
		$pages = $helpModel->preparePages($helpModel->getHelpPages());
		$pages += $defaultPages;
		
		$childrenPageData = array_intersect_key($pages, $children);
		
		if(empty($childrenPageData))
		{
			return;
		}

		$subTemplateParams = array(
			'pages' => $childrenPageData
		);

		$subListTemplate = $this->createTemplateObject('help_index', $subTemplateParams);
		$this->_params['templateHtml'] = str_replace('{childrenList}', $subListTemplate, $this->_params['templateHtml']);
	}
}
//Zend_Debug::dump($configs);