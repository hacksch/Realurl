<?php

/**
 * Class Tx_Realurl_Module_BackendInfoModule
 */
class Tx_Realurl_Module_BackendInfoModule extends tx_realurl_modfunc1 {

	/**
	 * @var Tx_Realurl_CacheManagement
	 */
	protected $cacheManagement;

	/**
	 * @var Tx_Realurl_PathGenerator
	 */
	protected $pathGenerator;


	/**
	 * Initialize the object
	 *
	 * @param object $pObj
	 * @param array $conf
	 * @return void
	 * @see \TYPO3\CMS\Backend\Module\BaseScriptClass::checkExtObj()
	 */
	public function init(&$pObj, $conf) {
		parent::init($pObj, $conf);
		$GLOBALS['LANG']->includeLLFile('EXT:realurl/Resources/Private/Language/Module/locallang.xml');
	}

	/**
	 * @return string
	 */
	protected function createModuleContentForPage() {
		$this->addModuleStyles();

		$result = $this->getFunctionMenu() . ' ';

		switch ($this->pObj->MOD_SETTINGS['type']) {
			case 'pathcache':
				$this->edit_save();
				$result .= $this->getDepthSelector();
				$result .= $this->renderModule($this->initializeTree());
				break;
			case 'encode':
				$result .= $this->getDepthSelector();
				$result .= $this->encodeView($this->initializeTree());
				break;
			case 'decode':
				$result .= $this->getDepthSelector();
				$result .= $this->decodeView($this->initializeTree());
				break;
			case 'uniqalias':
				$this->edit_save_uniqAlias();
				$result .= $this->uniqueAlias();
				break;
			case 'config':
				$result .= $this->getDepthSelector();
				$result .= $this->configView();
				break;
			case 'redirects':
				$result .= $this->redirectView();
				break;
			case 'log':
				$result .= $this->logView();
				break;
		}
		return $result;
	}

	/**
	 * @return t3lib_pageTree
	 */
	protected function initializeTree() {
		$tree = parent::initializeTree();
		$tree->addField('l18n_cfg');
		return $tree;
	}

	/**
	 * MAIN function for page information of localization
	 *
	 * @param \TYPO3\CMS\Backend\Tree\View\PageTreeView The Page tree data
	 * @return string Output HTML for the module.
	 */
	public function renderModule(\TYPO3\CMS\Backend\Tree\View\PageTreeView $tree) {
		if (!$this->pObj->id) {
			throw new Exception('No page ID given', 1379507542);
		}

		$theOutput = '';
		$this->cacheManagement = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', $GLOBALS['BE_USER']->workspace, 0, 1);
		$this->pathGenerator = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_PathGenerator');
		$this->pathGenerator->init(array());

		// Add action buttons:
		$theOutput .= '<table><tr><td style="vertical-align: top">';
		$theOutput .= '<h3>' . $GLOBALS['LANG']->getLL('backendInfoModule.actions') . '</h3>';
		$theOutput .= '<input name="id" value="' . $this->pObj->id . '" type="hidden"><input type="submit" value="' .
			$GLOBALS['LANG']->getLL('backendInfoModule.clearAll') . '" name="_action_clearall">';
		$theOutput .= '<br /><input type="submit" value="' .
			$GLOBALS['LANG']->getLL('backendInfoModule.clearVisibleTree') . '" name="_action_clearvisible">';
		$theOutput .= '<br /><input type="submit" value="' .
			$GLOBALS['LANG']->getLL('backendInfoModule.markVisibleTreeAsDirty') . '" name="_action_dirtyvisible">';
		$theOutput .= '<br /><input type="submit" value="' .
			$GLOBALS['LANG']->getLL('backendInfoModule.clearCompleteHistoryCache') . '" name="_action_clearallhistory">';
		$theOutput .= '<br /><input type="submit" value="' .
			$GLOBALS['LANG']->getLL('backendInfoModule.regeneratePaths') . '" name="_action_regenerate"></td><td valign="top">';
		$theOutput .= '<h3>' . $GLOBALS['LANG']->getLL('backendInfoModule.colors') . '</h3>';
		$theOutput .= '<table border="0">';
		$theOutput .= '<tr><td class="c-ok">' . $GLOBALS['LANG']->getLL('backendInfoModule.cacheFound') . '</td></tr>';
		$theOutput .= '<tr><td class="c-ok-expired">' . $GLOBALS['LANG']->getLL('backendInfoModule.cacheExpired') . '</td></tr>';
		$theOutput .= '<tr><td class="c-shortcut">' . $GLOBALS['LANG']->getLL('backendInfoModule.cacheShortcut') . '</td></tr>';
		$theOutput .= '<tr><td class="c-delegation">' . $GLOBALS['LANG']->getLL('backendInfoModule.cacheDelegation') . '</td></tr>';
		$theOutput .= '<tr><td class="c-nok">' . $GLOBALS['LANG']->getLL('backendInfoModule.cacheNotFound') . '</td></tr></table>';
		$theOutput .= '</td></tr></table>';

		// check actions:
		if (\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('_action_clearall') != '') {
			$this->cacheManagement->clearAllCache();
		}
		if (\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('_action_clearallhistory') != '') {
			$this->cacheManagement->clearAllCacheHistory();
		}

		// Add CSS needed:
		$cssContent = '
			TABLE#langTable {
				margin-top: 10px;
			}
			TABLE#langTable TR TD {
				padding-left : 2px;
				padding-right : 2px;
				white-space: nowrap;
			}
			TR.odd { background-color:#ddd; }
			TD.c-ok { background-color: #A8E95C; }
			TD.c-ok-expired { background-color: #B8C95C; }
			TD.c-shortcut { background-color: #B8E95C; font-weight: 200}
			TD.c-delegation { background-color: #EE0; }
			/*TD.c-nok { background-color: #E9CD5C; }*/
			TD.c-leftLine {border-left: 2px solid black; }
			TD.bgColor5 { font-weight: bold; }
		';
		$marker = '/*###POSTCSSMARKER###*/';
		if (!stristr($this->pObj->content, $marker)) {
			$theOutput = '<style type="text/css">' . $cssContent . '</style>' . chr(10) . $theOutput;
		} else {
			$this->pObj->content = str_replace($marker, $cssContent . chr(10) . $marker, $this->pObj->content);
		}
		$theOutput .= '<hr />' .  $GLOBALS['LANG']->getLL('backendInfoModule.pathCacheForWorkspace') . ' ' . $GLOBALS['BE_USER']->workspace;
		// Render information table:
		$theOutput .= $this->renderTable($tree);

	return $theOutput;
	}

	/**
	 * Render the information table
	 *
	 * @param \TYPO3\CMS\Backend\Tree\View\PageTreeView The Page tree data
	 * @return string HTML for the information table
	 */
	protected function renderTable(&$tree) {
		// title length
		$titleLen = $GLOBALS['BE_USER']->uc['titleLen'];
		// put together the TREE
		$output = '';
		$languageList = $this->getSystemLanguages();
		//traverse Tree:
		$rows = 0;
		foreach ($tree->tree as $data) {
			$tCells = array();
			$editUid = $data['row']['uid'];
			//check actions:
			if (\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('_action_clearvisible') != '') {
				$this->cacheManagement->delCacheForCompletePid($editUid);
			}
			if (\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('_action_dirtyvisible') != '') {
				$this->cacheManagement->markAsDirtyCompletePid($editUid);
			}

			// first cell (tree):
			// Page icons / titles etc.
			$tCells[] = '<td' . ($data['row']['_CSSCLASS'] ? ' class="' . $data['row']['_CSSCLASS'] . '"' : '') . '>'
				. $data['HTML'] . htmlspecialchars(\TYPO3\CMS\Core\Utility\GeneralUtility::fixed_lgd_cs($data['row']['title'], $titleLen))
				. (strcmp($data['row']['nav_title'], '') ? ' [Nav: <em>' . htmlspecialchars(\TYPO3\CMS\Core\Utility\GeneralUtility::fixed_lgd_cs($data['row']['nav_title'], $titleLen))
					. '</em>]' : '') . '</td>';
			//language cells:
			foreach ($languageList as $language) {

				if ($language['uid'] === '') {
					continue;
				}

				$langId = $language['uid'];
				$actionRegenerate = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('_action_regenerate');
				if ($actionRegenerate !== NULL) {
					$url = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . 'index.php?id=' . $editUid . '&no_cache=1&L=' . $langId;
					\TYPO3\CMS\Core\Utility\GeneralUtility::getUrl($url);
				}
				$info = '';
				$params = '&edit[pages][' . $editUid . ']=edit';

				$this->cacheManagement->setLanguageId($langId);
				$cacheRow = $this->cacheManagement->getCacheRowForPid($editUid);
				$cacheHistoryRows = $this->cacheManagement->getCacheHistoryRowsForPid($editUid);
				$isValidCache = $this->cacheManagement->isCacheRowStillValid($cacheRow);
				$hasEntry = FALSE;
				$path = '';
				if (is_array($cacheRow)) {
					$hasEntry = TRUE;
					$path = $cacheRow['path'] . ' <small style="color: #555"><i>' . ($cacheRow['dirty']?'X':'') . '(' . $cacheRow['rootpid'] . ')</i></small>';
				}
				if ($this->pathGenerator->isDelegationDoktype($data['row']['doktype'])) {
					$path .= ' [Delegation]';
				}
				if (count($cacheHistoryRows) > 0) {
					$path .= '[History:' . count($cacheHistoryRows) . ']';
				}
				if ($isValidCache) {
					$status = 'c-ok';
				} elseif ($hasEntry) {
					$status = 'c-ok-expired';
				} elseif ($data['row']['doktype'] == 4) {
					$path = '--- [shortcut]';
					$status = 'c-shortcut';
				} elseif ($this->pathGenerator->isDelegationDoktype($data['row']['doktype'])) {
					$status = 'c-delegation';
				} else {
					$status = 'c-nok';
				}
				$viewPageLink = '<a href="#" onclick="' .
					htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::viewOnClick($data['row']['uid'], $GLOBALS['BACK_PATH'], '', '', '', '&L=###LANG_UID###')) .
					'">' . '<img' . \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($GLOBALS['BACK_PATH'], 'gfx/zoom.gif', 'width="12" height="12"') .
					' title="' . $GLOBALS['LANG']->getLL('backendInfoModule.viewPage') . '" border="0" alt="" /></a>';
				$viewPageLink = str_replace('###LANG_UID###', $langId, $viewPageLink);
				if ($langId === 0) {
					//Default
					//"View page" link is created:
					$viewPageLink = '<a href="#" onclick="' .
						htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::viewOnClick($data['row']['uid'], $GLOBALS['BACK_PATH'], '', '', '', '&L=###LANG_UID###')) . '">' .
						'<img' . \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($GLOBALS['BACK_PATH'], 'gfx/zoom.gif', 'width="12" height="12"') . ' title="' .
						$GLOBALS['LANG']->getLL('backendInfoModule.viewPage') . '" border="0" alt="" /></a>';
					$info .= '<a href="#" onclick="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::editOnClick($params, $GLOBALS['BACK_PATH'])) .
						'">' . '<img' . \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif', 'width="11" height="12"') .
						' title="' . $GLOBALS['LANG']->getLL('backendInfoModule.editPage') . '" border="0" alt="" /></a>';
					$info .= str_replace('###LANG_UID###', '0', $viewPageLink);
					$info .= $path;
					// Put into cell:
					$tCells[] = '<td class="' . $status . ' c-leftLine">' . $info . '</td>';
				} else {
					//Normal Languages:
					$tCells[] = '<td class="' . $status . ' c-leftLine">' . $viewPageLink . $path . '</td>';
				}
			}
			$rows++;
			$output .= '
			<tr' . (($rows % 2) ? ' class="odd"' : '' ) . '>
				' . implode('
				', $tCells) . '
			</tr>';
		}
		// first row:
		$firstRowCells[] = '<td style="min-width:300px">' . $GLOBALS['LANG']->getLL('backendInfoModule.title') . ':</td>';
		foreach ($languageList as $language) {
			if ($language['uid'] !== '') {
				$firstRowCells[] = '<td class="c-leftLine">' . $language['title'] . ' [' . $language['uid'] . ']</td>';
			}
		}
		$output = '
			<tr class="bgColor2">
				' . implode('
				', $firstRowCells) . '
			</tr>' . $output;
		$output = '

		<table border="0" cellspacing="0" cellpadding="0" id="langTable">' . $output . '
		</table>';
		return $output;
	}
}