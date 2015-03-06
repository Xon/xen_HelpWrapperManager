<?php
class Sedo_HelpWrapperManager_ViewPublic_Help_Page extends XFCP_Sedo_HelpWrapperManager_ViewPublic_Help_Page 
{
	protected function _childrenListDetect()
	{
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


	protected function _getPostDetect()
	{
		$html = &$this->_params['templateHtml'];

		if(strpos($html, '{post=') === false)
		{
			return;
		}

		if(!preg_match_all('#{post=(\d+?)(,[^}]+)?}#', $html, $matches, PREG_SET_ORDER))
		{
			return;
		}

		$postModel = XenForo_Model::create('XenForo_Model_Post');
		$search = array();
		$replace = array();
		
		foreach($matches as $match)
		{
			$search[] = $match[0];
			$postId = $match[1];
			$options = $match[2];
			
			$options = explode(',', $options);
			$trimFirstLines = false;
			$trimLastLines = false;
			$noPicture = false;
			
			foreach($options as $option)
			{
				if(!$option) continue;
				$option = trim($option);
				
				if(strpos($option, 'delete-firstlines:') !== false)
				{
					$trimFirstLines = intval(substr($option, 18));
				}
				elseif(strpos($option, 'delete-lastlines:') !== false)
				{
					$trimLastLines = intval(substr($option, 17));				
				}
				elseif($option == 'no-picture')
				{
					$noPicture = true;
				}
			}
			
			$post = $postModel->getPostById($postId);
			
			if(!$post)
			{
				$replace[] = "PostId #{$postId} not found";
			}
			else
			{
				$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
				$bbCodeOptions = array(
					'states' => array(
						'viewAttachments' => true
					),
					'contentType' => 'post',
					'contentIdKey' => 'post_id'
				);

				if($trimFirstLines || $trimLastLines)
				{
					$message = &$post['message'];
					
					if($trimFirstLines)
					{
						$message = preg_replace('#^(.*?\n){'.$trimFirstLines.'}#sui', '', $message);
					}
					
					if($trimLastLines)
					{
						$messageByLines = explode("\n", $message);
						$messageByLines = array_slice($messageByLines, 0, count($messageByLines)-$trimLastLines);
						$message = implode("\n", $messageByLines);
					}
				}

				if($noPicture)
				{
					$message = preg_replace('#[\r\n]?\[(img|bimg|attach).*?\[/\1][\r\n]?#iu', '', $message);
				}

				$messageHtml = XenForo_ViewPublic_Helper_Message::getBbCodeWrapper($post, $bbCodeParser, $bbCodeOptions);
				$replace[] = $messageHtml;
			}
		}
		
		$html = str_replace($search, $replace, $html);
	}

	public function renderHtml()
	{
		parent::renderHtml();

		if(empty($this->_params['templateHtml']))
		{
			return;
		}
		
		$this->_childrenListDetect();
		$this->_getPostDetect();
	}
}
//Zend_Debug::dump($configs);