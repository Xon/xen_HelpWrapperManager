<?php
class Sedo_HelpWrapperManager_Listener
{
	public static function helpWrapperTemplateMod($matches)
	{
		if(empty($matches[0])){
			return;
		}

		$hookContent = $matches[0];
		$count = preg_match_all('#<li.*?{\$selected}.+?\'(.+?)\'.*?</li>#su', $hookContent, $li, PREG_SET_ORDER);
		
		if(!$count)
		{
			return $matches[0];
		}
		
		$newContent[] = '<xen:hook name="help_sidebar_links"></xen:hook>';
		
		foreach($li as $data)
		{
			$liContent = $data[0];
			$liId = $data[1];

			if($liId == 'terms')
			{
				$liContent = '<xen:if is="{$tosUrl}">'.$liContent.'</xen:if>';
			}

			$newContent[] = "<xen:set var=\"\$xenData.$liId\">$liContent</xen:set>";
		}
		
		return implode("\r\n\t\t\t\t\t", $newContent);
	}

	public static function rerouteHelpIndexTemplate(&$templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if($templateName == 'help_index')
		{
			$templateName = 'sedo_new_help_index';
		}
	}
	
	public static function extendHelpWrapperView($class, array &$extend)
	{
		if($class == 'XenForo_ViewPublic_Help_Wrapper')
		{
			$extend[] = 'Sedo_HelpWrapperManager_ViewPublic_Help_Wrapper';
		}
	}

	public static function extendHelpIndexView($class, array &$extend)
	{
		if($class == 'XenForo_ViewPublic_Help_Index')
		{
			$extend[] = 'Sedo_HelpWrapperManager_ViewPublic_Help_Index';
		}
	}

	public static function helpWrapper_settings(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
      		$config = $preparedOption['option_value'];
      		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
      			'preparedOption' => $preparedOption,
      			'canEditOptionDefinition' => $canEdit
      		));

		list($pagesData, $parents) = self::getPagesData();
		$backupPagesData = $pagesData;

		if(!empty($config))
		{
			foreach($config as $pageId => &$pageData)
			{
				if(!isset($pagesData[$pageId]))
				{
					unset($config[$pageId]); // Might have been deleted
					continue;
				}
				
				$pageData['name'] = $pagesData[$pageId]['name'];
				if(!empty($pageData['parent']))
				{
					unset($parents[$pageId]); // let's consider that a child can't be a parent (will avoid too much nested elements
				}
			}

			$pagesData = $config+$pagesData; //Use this way: the config order will be kept and new pages will be added
		}

		foreach($pagesData as $pageId => &$pageData)
		{
			if(!isset($backupPagesData[$pageId]))
			{
				continue;
			}

			$pageData['originalOrder'] = $backupPagesData[$pageId]['displayOrder'];

			$usergroupsCheckbox = array(
				'formatParams' => XenForo_Model::create('Sedo_HelpWrapperManager_Model_GetUsergroups')->getUserGroupsOptions($pageData['disableUsergroups']),
				'option_id' => $preparedOption['option_id'] . "][$pageId][disableUsergroups][", //will be wrapped with [] :  name="{$fieldPrefix}[{$preparedOption.option_id}]"
				'title' => new XenForo_Phrase('sedo_helpwrapper_hide_page_for_these_usergroups'),
				'explain' => ''
			);

			$pageData['checkboxTemplate_usr'] = XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal('option_list_option_checkbox', $view, $fieldPrefix, $usergroupsCheckbox, false);

			$availableParents = $parents;
			unset($availableParents[$pageId]);
			$pageData['availableParents'] = $availableParents;
		}
		
     		return $view->createTemplateObject('option_list_sedo_help_wrapper_settings', array(
      			'fieldPrefix' => $fieldPrefix,
      			'listedFieldName' => $fieldPrefix . '_listed[]',
      			'preparedOption' => $preparedOption,
      			'formatParams' => $preparedOption['formatParams'],
      			'editLink' => $editLink,
			'PagesData' => $pagesData,
      			'config' => $config			
      		));
	}

	public static function getPagesData()
	{
		$helpModel = XenForo_Model::create('XenForo_Model_Help');
		$pages = $helpModel->preparePages($helpModel->getHelpPages());

		$defaultPages = array('smilies', 'bbCodes', 'trophies', 'cookies', 'terms');
		$pagesData = array();
		$i = 10;
		$parents[0] = new XenForo_Phrase('root');

		foreach($defaultPages as $defaultKey)
		{
			$pagesData[$defaultKey] = array(
				'name' => $defaultKey,
				'displayOrder' => $i,
				'disableUsergroups' => array(),
				'alwaysHidden' => false,
				'parent' => 0
			);			
			$i = $i+10;
			$parents[$defaultKey] = $defaultKey;
		}

		foreach($pages as $page)
		{
			$pagesData[$page['page_id']] = array(
				'name' => $page['page_name'],
				'displayOrder' => $i,
				'disableUsergroups' => array(),
				'alwaysHidden' => false,
				'parent' => 0
			);
			$i = $i+10;
			$parents[$page['page_id']] = $page['page_name'];
		}
		
		return array($pagesData, $parents);
	}

	public static function helpWrapper_settings_validation(array &$configs, XenForo_DataWriter $dw, $fieldName)
	{
		if(!empty($configs['raz']))
		{
			list($configs) = self::getPagesData();
			
			foreach($configs as $pageId => $pageData)
			{
				unset($configs[$pageId]['name']);
			}

			return true;
		}

		$children = array();
		$refChildren = array();

		foreach($configs as $pageId => &$pageData)
		{
			$neededKeys = array(
				'displayOrder' => intval($pageData['originalOrder']),
				'disableUsergroups' => array(),
				'alwaysHidden' => false,
				'parent' => 0
			);

			$diffToDelete = array_diff_key($pageData, $neededKeys);
			$diffToKeep = array_diff_key($pageData, $diffToDelete);
			$pageData = array_merge($neededKeys, $diffToKeep);
			
			if(!empty($pageData['parent']))
			{
				$children[$pageData['parent']][$pageId] = $pageData;
				$refChildren[$pageId] = $pageData;
				unset($configs[$pageId]);
			}
		}

		uasort($configs, array('Sedo_HelpWrapperManager_Listener', 'sortByDisplayOrder')); 

		if(empty($children))
		{
			return true;
		}

		unset($pageData); // was passed as a reference above
		$configsKeysRaz = array_keys($configs);


		foreach($children as $parentId => $pages)
		{
			uasort($pages, array('Sedo_HelpWrapperManager_Listener', 'sortByDisplayOrder'));
			$pages = array_reverse($pages); // the loop will reverse the order

			foreach($pages as $pageId => $pageData)
			{
				$targetedPos = array_search($parentId, $configsKeysRaz);
				if($targetedPos === false)
				{
					continue;
				}

				$targetedPos++; //Insert after

				$configsKeysRaz = array_values(
					array_slice($configsKeysRaz, 0, $targetedPos, true) +
					array('sedo'=>$pageId) +	
					array_slice($configsKeysRaz, $targetedPos, count($configsKeysRaz)-$targetedPos, true)
				);
			}
		}

		$newConfigs = array();
		foreach($configsKeysRaz as $pageId)
		{
			if(isset($configs[$pageId]))
			{
				$newConfigs[$pageId] = $configs[$pageId];
			}
			elseif(isset($refChildren[$pageId]))
			{
				$newConfigs[$pageId] = $refChildren[$pageId];
			}
		}

		$configs = $newConfigs;
		return true;
	}

	public static function sortByDisplayOrder($a, $b)
	{
		if ($a['displayOrder'] == $b['displayOrder'])
		{
	        	return 0;
		}
		return ($a['displayOrder'] < $b['displayOrder']) ? -1 : 1;
	}
}
//Zend_Debug::dump($contents);