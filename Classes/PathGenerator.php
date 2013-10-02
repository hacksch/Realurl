<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2008 AOE media
 * All rights reserved
 *
 * This script is part of the Typo3 project. The Typo3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 *
 * @author  Daniel Pötzinger
 * @author  Tolleiv Nietsch
 * @package realurl
 * @subpackage realurl
 *
 * @todo	check if internal cache array makes sense
 */
class Tx_Realurl_PathGenerator {

	/**
	 * @var int
	 */
	protected $pidForCache;

	/**
	 * @var array
	 */
	protected $extconfArr = array();

	/**
	 * @var array
	 */
	protected $doktypeCache = array();

	/**
	 * @var array
	 */
	protected $conf = array();

	/**
	 * @var tx_realurl
	 */
	protected $pObj;

	/**
	 * @var t3lib_pageSelect
	 */
	protected $sysPage;

		/**
		 *
		 * @param array $conf
		 * @return void
		 */
	public function init(array $conf) {
		$this->conf = $conf;
		$this->extconfArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl']);
	}

	/**
	 *
	 * @param int $pid
	 * @param int $langid
	 * @param int $workspace
	 * @return array	buildPageArray
	 */
	public function build($pid, $langid, $workspace) {
		if ($shortCutPid = $this->checkForShortCutPageAndGetTarget($pid, $langid, $workspace)) {
			if (is_array($shortCutPid) && array_key_exists('path', $shortCutPid) && array_key_exists('rootPid', $shortCutPid)) {
				return $shortCutPid;
			}
			$pid = $shortCutPid;
		}
		$this->pidForCache = $pid;
		$rootline = $this->getRootLine ( $pid, $langid, $workspace );
		$firstPage = $rootline[0];
		$rootPid = $firstPage['uid'];
		$lastPage = $rootline[count($rootline) - 1];

		$pathString = '';
		$external = FALSE;

		if ((int) $lastPage['doktype'] === 3) {
			$pathString = $this->buildExternalUrl($lastPage, $langid, $workspace);
			$external = TRUE;

		} elseif ($lastPage['tx_realurl_pathoverride'] && $overridePath = $this->stripSlashes($lastPage['tx_realurl_pathsegment'])) {
			$parts = explode('/', $overridePath);
			$cleanParts = array_map(array (
				$this,
				'encodeTitle'
			), $parts);
			$nonEmptyParts = array_filter($cleanParts);
			$pathString = implode('/', $nonEmptyParts);
		}
		if (! $pathString) {
			if ($this->getDelegationFieldname($lastPage['doktype'])) {
				$pathString = $this->getDelegationTarget($lastPage);
				if (! preg_match('/^[a-z]+:\/\//', $pathString)) {
					$pathString = 'http://' . $pathString;
				}
				$external = TRUE;
			} else {
				$pathString = $this->buildPath($this->conf['segTitleFieldList'], $rootline);
			}
		}
		return array (
			'path' => $pathString,
			'rootPid' => $rootPid,
			'external' => $external
		);
	}

	/**
	 *
	 * @param string $inputString
	 * @return string
	 */
	protected function stripSlashes($inputString) {
		$outputString = $inputString;
		if (substr($outputString, -1) === '/') {
			$outputString = substr($outputString, 0, -1);
		}
		if (substr($outputString, 0, 1) === '/') {
			$outputString = substr($outputString, 1);
		}
		if ($inputString !== $outputString) {
			return $this->stripSlashes ( $outputString );
		} else {
			return $outputString;
		}
	}

	/**
	 *
	 * @return int Uid for Cache
	 */
	public function getPidForCache() {
		return $this->pidForCache;
	}

	/**
	 *
	 * @param int $id
	 * @param int $langid
	 * @param int $workspace
	 * @param int $reclevel
	 * @return boolean
	 */
	protected function checkForShortCutPageAndGetTarget($id, $langid = 0, $workspace = 0, $reclevel = 0) {
		if ($this->conf['renderShortcuts']) {
			return FALSE;
		}

		static $cache = array();
		$paramhash = intval($id) . '_' . intval($langid) . '_' . intval($workspace) . '_' . intval($reclevel);

		if (isset($cache[$paramhash])) {
			return $cache[$paramhash];
		}

		$returnValue = FALSE;

		if ($reclevel > 20) {
			$returnValue =  FALSE;
		}
			// check defaultlang since overlays should not contain this (usually)
		$this->initSysPage(0, $workspace);
		$result = $this->sysPage->getPage($id);

			// if overlay for the of shortcuts is requested
		if ($this->extconfArr['localizeShortcuts'] && \TYPO3\CMS\Core\Utility\GeneralUtility::inList ($GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'], 'shortcut') && $langid) {

			$resultOverlay = $this->getPageOverlay($id, $langid);
			if ($resultOverlay['shortcut']) {
				$result['shortcut'] = $resultOverlay['shortcut'];
			}
		}

		if ((int) $result['doktype'] === 4) {
			switch ($result['shortcut_mode']) {
					// first subpage
				case '1':
					if ($reclevel > 10) {
						$returnValue = FALSE;
					}
					$where = 'pid="' . $id . '"';
					$query = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'pages', $where, '', 'sorting', '0,1');
					if ($query) {
						$resultfirstpage = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($query);
						$subpageShortCut = $this->checkForShortCutPageAndGetTarget($resultfirstpage['uid'], $langid, $workspace, $reclevel + 1);
					}
					if ($subpageShortCut !== FALSE) {
						$returnValue = $subpageShortCut;
					} else {
						$returnValue = $resultfirstpage['uid'];
					}
					break;
					// random
				case '2' :
					$returnValue = FALSE;
					break;
				default :
					if ((int) $result['shortcut'] === $id) {
						$returnValue = FALSE;
					} else {
							// look recursive:
						$subpageShortCut = $this->checkForShortCutPageAndGetTarget($result['shortcut'], $langid, $workspace, $reclevel + 1);
						if ($subpageShortCut !== FALSE) {
							$returnValue = $subpageShortCut;
						} else {
							$returnValue = $result['shortcut'];
						}
					}
					break;
			}
		} elseif ($this->getDelegationFieldname($result['doktype'])) {

			$target = $this->getDelegationTarget($result, $langid, $workspace);
			if (is_numeric($target)) {
				$res = $this->checkForShortCutPageAndGetTarget($target, $langid, $workspace, $reclevel - 1);
					//if the recursion fails we keep the original target
				if ($res === FALSE) {
					$res = $target;
				}
			} else {
				$res = $result['uid'];
			}
			$returnValue = $res;
		} else {
			$returnValue = FALSE;
		}

		$cache[$paramhash] = $returnValue;
		return $returnValue;
	}

	/**
	 * set the rootpid that is used for generating the path. (used to stop rootline on that pid)
	 *
	 * @param int $id
	 * @return void
	 */
	public function setRootPid($id) {
		$this->rootPid = $id;
	}

		/**
		 * @param tx_realurl $pObj
		 * @return void
		 */
	public function setParentObject(tx_realurl $pObj) {
		$this->pObj = $pObj;
	}

	/**
	 * @param int $pid    Pageid of the page where the rootline should be retrieved
	 * @param int $langId
	 * @param int $wsId
	 * @param string $mpvar
	 * @throws Exception
	 * @return mixed    array with rootline for pid
	 */
	protected function getRootLine($pid, $langId, $wsId, $mpvar = '') {
			// Get rootLine for current site (overlaid with any language overlay records).
		$this->initSysPage ( $langId, $wsId );
		$rootLine = $this->sysPage->getRootLine ( $pid, $mpvar );
			// only return rootline to the given rootpid
		$rootPidFound = FALSE;
		while ( ! $rootPidFound && count ( $rootLine ) > 0 ) {
			$last = array_pop ( $rootLine );
			if ((int) $last['uid'] === $this->rootPid) {
				$rootPidFound = TRUE;
				$rootLine[] = $last;
				break;
			}
		}
		if (! $rootPidFound) {
			if ((int) $this->conf['strictMode'] === 1) {
				throw new Exception ( 'The rootpid ' . $this->rootPid . '.configured for pagepath generation was not found in the rootline for page' . $pid );
			}
			return $rootLine;
		}

		$siteRootLine = array ();
		$c = count ( $rootLine );
		foreach ( $rootLine as $val ) {
			$c --;
			$siteRootLine[$c] = $val;
		}
		return $siteRootLine;
	}

	/**
	 * checks if the user is logged in backend
	 *
	 * @return bool
	 */
	protected function isBackendLogin() {
		return is_object($GLOBALS['BE_USER']);
	}

	/**
	 * builds the path based on the rootline
	 * @param $segment configuration wich field from database should use
	 * @param $rootline The rootLine  from the actual page
	 * @return array with rootLine and path
	 */
	protected function buildPath($segment, $rootline) {
		$segment = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $segment);
		$path = array ();
		$size = count ( $rootline );
		$rootline = array_reverse ( $rootline );
			//do not include rootpage itself, except it is only the root and filename is set
		if ($size > 1 || $rootline[0]['tx_realurl_pathsegment'] === '') {
			array_shift ( $rootline );
			$size = count ( $rootline );
		}
		$i = 1;
		foreach ( $rootline as $key => $value ) {
				//check if the page should exlude from path (if not last)
			if ($value['tx_realurl_exclude'] && $i !== $size) {
			} else {
					//the normal way
				$pathSeg = $this->getPathSeg ( $value, $segment );
				if (strcmp ( $pathSeg, '' ) === 0) {
					if ((strcmp($pathSeg, '') === 0) && $value['_PAGES_OVERLAY']) {
						$pathSeg = $this->getPathSeg($this->getDefaultRecord($value), $segment);
					}
					if (strcmp($pathSeg, '') === 0) {
						$pathSeg = 'page_' . $value['uid'];
					}
				}
				$path[] = $pathSeg;
			}
			$i ++;
		}
			//build the path
		$path = implode('/', $path);
		return $path;
	}

	/**
	 *
	 * @param array $pageRec
	 * @param array $segments
	 * @return string
	 */
	protected function getPathSeg($pageRec, $segments) {
		$retVal = '';
		foreach ($segments as $segmentName) {
			if ($this->encodeTitle($pageRec[$segmentName]) !== '') {
				$retVal = $this->encodeTitle($pageRec[$segmentName]);
				break;
			}
		}
		return $retVal;
	}

	/**
	 *
	 * @param array $l10nrec
	 * @return arrray
	 */
	protected function getDefaultRecord($l10nrec) {
		$lang = $this->sysPage->sys_language_uid;
		$this->sysPage->sys_language_uid = 0;
		$rec = $this->sysPage->getPage($l10nrec['uid']);
		$this->sysPage->sys_language_uid = $lang;
		return $rec;
	}

	/**
	 *
	 * @param int $doktype
	 * @return boolean
	 */
	public function isDelegationDoktype($doktype) {
		if (! array_key_exists($doktype, $this->doktypeCache)) {
			$this->doktypeCache[$doktype] = ($this->getDelegationFieldname($doktype)) ? TRUE : FALSE;
		}
		return $this->doktypeCache[$doktype];
	}

	/**
	 *
	 * @param int $doktype
	 * @return string
	 */
	protected function getDelegationFieldname($doktype) {
		if (is_array($this->conf['delegation'] ) && array_key_exists($doktype, $this->conf['delegation'])) {
			return $this->conf['delegation'][$doktype];
		} else if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['delegate']) && array_key_exists($doktype, $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['delegate'])) {
			return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['delegate'][$doktype];
		} else {
			return FALSE;
		}
	}

	/**
	 *
	 * @param array $record
	 * @param int $langid
	 * @param int $workspace
	 * @return int
	 */
	protected function getDelegationTarget($record, $langid = 0, $workspace = 0) {

		$fieldname = $this->getDelegationFieldname($record['doktype']);

		if (! array_key_exists($fieldname, $record)) {
			$this->initSysPage($langid, $workspace);
			$record = $this->sysPage->getPage($record['uid']);
		}

		$parts = explode( ' ', $record[$fieldname]);

		return $parts[0];
	}

	/*******************************
	 *
	 * Helper functions
	 *
	 ******************************/
	/**
	 * Convert a title to something that can be used in an page path:
	 * - Convert spaces to underscores
	 * - Convert non A-Z characters to ASCII equivalents
	 * - Convert some special things like the 'ae'-character
	 * - Strip off all other symbols
	 * Works with the character set defined as "forceCharset"
	 *
	 * @param	string		Input title to clean
	 * @return	string		Encoded title, passed through rawurlencode() = ready to put in the URL.
	 * @see rootLineToPath()
	 */
	protected function encodeTitle($title) {
			// Fetch character set:
		$charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->defaultCharSet;
			// Convert to lowercase:
		$processedTitle = $GLOBALS['TSFE']->csConvObj->conv_case($charset, $title, 'toLower');
			// Convert some special tokens to the space character:
		$space = isset($this->conf['spaceCharacter']) ? $this->conf['spaceCharacter'] : '-';
			// convert spaces
		$processedTitle = preg_replace('/[\s+]+/', $space, $processedTitle);
			// Convert extended letters to ascii equivalents:
		$processedTitle = $GLOBALS['TSFE']->csConvObj->specCharsToASCII($charset, $processedTitle);
			// Strip the rest
		if ($this->pObj->extConf['init']['enableAllUnicodeLetters']) {
				// Warning: slow!!!
			$processedTitle = preg_replace('/[^\p{L}0-9' . ($space ? preg_quote($space) : '') . ']/u', $space, $processedTitle);
		} else {
			$processedTitle = preg_replace('/[^a-zA-Z0-9' . ($space ? preg_quote($space) : '') . ']/', $space, $processedTitle);
		}
		$processedTitle = preg_replace('/\\' . $space . '+/', $space, $processedTitle);
		$processedTitle = trim($processedTitle, $space);
		if ($this->conf['encodeTitle_userProc']) {
			$params = array (
				'pObj' => &$this,
				'title' => $title,
				'processedTitle' => $processedTitle
			);
			$processedTitle = \TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($this->conf['encodeTitle_userProc'], $params, $this);
		}
			// Return encoded URL:
		return ($processedTitle);
	}

	/**
	 *
	 *
	 * @param int $langId
	 * @param int $workspace
	 * @return void
	 */
	protected function initSysPage($langId, $workspace) {
		if (! is_object($this->sysPage)) {
			/**
			 * Initialize the page-select functions.
			 * don't use $GLOBALS['TSFE']->sys_page here this might
			 * lead to strange side-effects due to the fact that some
			 * members of sys_page are modified.
			 *
			 * I also opted against "clone $GLOBALS['TSFE']->sys_page"
			 * since this might still cause race conditions on the object
			 */
			$this->sysPage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('t3lib_pageSelect');
		}
		$this->sysPage->sys_language_uid = $langId;
		if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($workspace) && $workspace > 0) {
			$this->sysPage->versioningWorkspaceId = $workspace;
			$this->sysPage->versioningPreview = 1;
		} else {
			$this->sysPage->versioningWorkspaceId = 0;
			$this->sysPage->versioningPreview = FALSE;
		}
	}

	/**
	 *
	 * @param array $page
	 * @param int $langid
	 * @param int $workspace
	 * @return string
	 */
	protected function buildExternalUrl($page, $langid = 0, $workspace = 0) {

			// FIXME BUGGY!!!
			// check defaultlang since overlays should not contain this (usually)
		$this->initSysPage(0, $workspace);
		$fullPageArr = $this->sysPage->getPage($page['uid']);
		if (is_array($fullPageArr) && $langid > 0) {
			$fullPageArr = $this->sysPage->getPageOverlay($fullPageArr, $langid);
		}

		$prefix = FALSE;
		$prefixItems = $GLOBALS['TCA']['pages']['columns']['urltype']['config']['items'];
		if (is_array($prefixItems)) {
			foreach ($prefixItems as $prefixItem) {
				if (intval ($prefixItem['1']) == intval ($fullPageArr['urltype'])) {
					$prefix = $prefixItem['0'];
					break;
				}
			}
		}

		if (! $prefix) {
			$prefix = 'http://';
		}
		return $prefix . $fullPageArr['url'];
	}

	/**
	 *
	 * @param int $id
	 * @param int $langid
	 * @return array
	 */
	protected function getPageOverlay($id, $langid = 0) {
		$relevantLangId = $langid;
		if ($this->extconfArr['useLanguagevisibility'] && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('languagevisibility')) {
			$relevantLangId = tx_languagevisibility_feservices::getOverlayLanguageIdForElementRecord($id, 'pages', $langid);
		}
		return $this->sysPage->getPageOverlay($id, $relevantLangId);
	}
}
