<?php
class Sedo_HelpWrapperManager_Helper_Help
{
	public static function manageHelpPages(array $pagesSource)
	{
		$xenPages = array('smilies' => '', 'bbCodes' => array(), 'trophies' => array(), 'cookies' => array(), 'terms' => array());
		$reorderedPages = array();

		$settings = XenForo_Application::get('options')->get('sedo_helpwrapper_settings');
		$visitor = XenForo_Visitor::getInstance();
		$visitorUserGroupIds = array_merge(array((string)$visitor['user_group_id']), (explode(',', $visitor['secondary_group_ids'])));
		$childrenRef = array();
		$alwaysHidden = array();
		
		foreach($settings as $pageId => $pageSettings)
		{
			if(!isset($xenPages[$pageId]) && !isset($pagesSource[$pageId]))
			{
				continue;
			}

			if(!empty($pageSettings['alwaysHidden']))
			{
				$alwaysHidden[$pageId] = $pageSettings;
				continue;
			}

			if(!empty($pageSettings['disableUsergroups']))
			{
				if(!array_intersect($visitorUserGroupIds, $pageSettings['disableUsergroups']))
				{
					continue;
				}
			}
			
			$pageData = (isset($xenPages[$pageId])) ? $xenPages[$pageId] : $pagesSource[$pageId];
			
			if(!is_array($pageData))
			{
				$pageData = array();
			}
			
			$pageData['hasChildren'] = false;
			$pageData['isChild'] = (!empty($pageSettings['parent']));
			$pageData['parentId'] = (!empty($pageSettings['parent'])) ? $pageSettings['parent'] : null;

			if($pageData['isChild']  && !isset($reorderedPages[$pageData['parentId']]))
			{
				//The parent page might be hidden
				$alwaysHidden[$pageId] = $pageData;
				continue;
			}

			if($pageData['isChild'] && isset($reorderedPages[$pageData['parentId']]))
			{
				$reorderedPages[$pageData['parentId']]['hasChildren'][$pageId] = $pageData;
				$childrenRef[$pageId] = $pageData;
				continue;		
			}

			$reorderedPages[$pageId] = $pageData;
		}

		$catchupNewpages = array_diff_key($pagesSource, $reorderedPages+$childrenRef);
		$catchupNewpages = array_diff_key($catchupNewpages, $alwaysHidden);
		
		if($catchupNewpages)
		{
			$reorderedPages+=$catchupNewpages;
		}

		return $reorderedPages;
	}
}
//Zend_Debug::dump($parent);