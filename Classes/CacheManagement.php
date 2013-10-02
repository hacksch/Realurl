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
 * @author	Daniel Poetzinger
 * @author	Tolleiv Nietsch
 *
 * @todo
 * - check if internal cache array can improve speed
 * - move oldlinks to redirects
 * - check last updatetime of pages
 */

/**
 *
 * @author	Daniel Poetzinger
 * @package realurl
 * @subpackage realurl
 */
class Tx_Realurl_CacheManagement {

	/**
	 * @var int
	 */
	protected $workspaceId;

	/**
	 * @var int
	 */
	protected  $languageId;

	/**
	 * @var int
	 */
	protected  $rootPid;

	/**
	 * Timeout (seconds) for cache key entries
	 *
	 * @var int
	 */
	protected  $cacheTimeOut = 1000;

	/**
	 * @var bool
	 */
	protected  $useUnstrictCacheWhere = FALSE;

	/**
	 * @var t3lib_DB
	 */
	protected $dbObj;

	/**
	 * @var array
	 */
	protected static $cache = array();

	/**
	 * Class constructor (PHP4 style)
	 *
	 * @param int $workspace
	 * @param int $languageId
	 */
	public function __construct($workspace, $languageId) {
		$this->workspaceId = $workspace;
		$this->languageId = $languageId;
		$this->useUnstrictCacheWhere = FALSE;
		$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl']);
		if (isset($confArr['defaultCacheTimeOut'])) {
			$this->setCacheTimeOut($confArr['defaultCacheTimeOut']);
		}
		$this->dbObj = $GLOBALS['TYPO3_DB'];
	}

	/**
	 *
	 * @param int $rootPid
	 * @return void
	 */
	public function setRootPid($rootPid) {
		$this->rootPid = $rootPid;
	}

	/**
	 *
	 * @param int $languageId
	 * @return void
	 */
	public function setLanguageId($languageId) {
		$this->languageId = $languageId;
	}

	/**
	 *
	 * @param int $time - in secounds
	 * @return void
	 */
	public function setCacheTimeOut($time) {
		$this->cacheTimeOut = intval($time);
	}

	/**
	 *
	 * @param int $workspaceId
	 * @return void
	 */
	public function setWorkspaceId($workspaceId) {
		$this->workspaceId = $workspaceId;
	}

	/**
	 * @return void
	 */
	public function useUnstrictCacheWhere() {
		$this->useUnstrictCacheWhere = TRUE;
	}

	/**
	 * @return void
	 */
	public function doNotUseUnstrictCacheWhere() {
		$this->useUnstrictCacheWhere = FALSE;
	}

	/**
	 * @return int
	 */
	public function getWorkspaceId() {
		return $this->workspaceId;
	}

	/**
	 * @return int
	 */
	public function getLanguageId() {
		return $this->languageId;
	}

	/**
	 * @return int
	 */
	public function getRootPid() {
		return $this->rootPid;
	}

	/**
	 * important function: checks the path in the cache: if not found the check against cache is repeted without the last pathpart
	 * @param array  $pagePathOrigin  the path which should be searched in cache
	 * @param &$keepPath  -> passed by reference -> array with the n last pathparts which could not retrieved from cache -> they are propably preVars from translated parameters (like tt_news is etc...)
	 *
	 * @return mixed int|bool pageId or False
	 */
	public function checkCacheWithDecreasingPath($pagePathOrigin, &$keepPath) {
		return $this->checkACacheTableWithDecreasingPath($pagePathOrigin, $keepPath, FALSE);
	}

	/**
	 * important function: checks the path in the cache: if not found the check against cache is repeated without the last pathpart
	 *
	 * @param array  $pagePathOrigin  the path which should be searched in cache
	 * @param &$keepPath  -> passed by reference -> array with the n last pathparts which could not retrieved from cache -> they are propably preVars from translated parameters (like tt_news is etc...)	 *
	 * @return mixed int|bool pagid or false
	 */
	public function checkHistoryCacheWithDecreasingPath($pagePathOrigin, &$keepPath) {
		return $this->checkACacheTableWithDecreasingPath($pagePathOrigin, $keepPath, TRUE);
	}

	/**
	 *
	 * @see checkHistoryCacheWithDecreasingPath
	 * @param array $pagePathOrigin
	 * @param array $keepPath
	 * @param boolean $inHistoryTable
	 * @return int
	 */
	protected function checkACacheTableWithDecreasingPath($pagePathOrigin, &$keepPath, $inHistoryTable = FALSE) {
		$sizeOfPath = count($pagePathOrigin);
		$pageId = FALSE;
		for ($i = $sizeOfPath; $i > 0; $i--) {
			if (!$inHistoryTable) {
				$pageId = $this->readCacheForPath(implode('/', $pagePathOrigin));
			} else {
				$pageId = $this->readHistoryCacheForPath(implode('/', $pagePathOrigin));
			}
			if ($pageId !== FALSE) {
					//found something => break;
				break;
			} else {
				array_unshift($keepPath, array_pop($pagePathOrigin));
			}
		}
		return $pageId;
	}

	/**
	 * Stores the path in cache and checks if that path is unique, if not this function makes the path unique by adding some numbers
	 * (throws error if caching fails)
	 *
	 * @param int $pid
	 * @param string $buildedPath
	 * @param bool $disableCollisionDetection
	 * @return string unique path in cache
	 */
	public function storeUniqueInCache($pid, $buildedPath, $disableCollisionDetection = FALSE) {
		$this->dbObj->sql_query('BEGIN');
		if ($this->isInCache($pid) === FALSE) {
			$this->checkForCleanupCache($pid, $buildedPath);
				//do cleanup of old cache entries:
			$ignore = $pid;
			$workspace = $this->getWorkspaceId();
			if ($workspace > 0) {
				$record = \TYPO3\CMS\Backend\Utility\BackendUtility::getLiveVersionOfRecord('pages', $pid, 'uid');
				if (!is_array($record)) {
					$record = \TYPO3\CMS\Backend\Utility\BackendUtility::getWorkspaceVersionOfRecord($workspace, 'pages', $pid, '*');
				}
				if (is_array($record)) {
					$ignore = $record['uid'];
				}
			}

			if ($this->readCacheForPath($buildedPath, $ignore) && !$disableCollisionDetection) {
				$buildedPath .= '_' . $pid;
			}
				//do insert
			$data['tstamp'] = $GLOBALS['EXEC_TIME'];
			$data['path'] = $buildedPath;
			$data['mpvar'] = '';
			$data['workspace'] = $this->getWorkspaceId();
			$data['languageid'] = $this->getLanguageId();
			$data['rootpid'] = $this->getRootPid();
			$data['pageid'] = $pid;

			if ($this->dbObj->exec_INSERTquery("tx_realurl_cache", $data)) {
				//TODO ... yeah we saved something in the database - any further problems?
			} else {
				//TODO ... d'oh database didn't like us - what's next?
			}
		}
		$this->dbObj->sql_query('COMMIT');
		return $buildedPath;
	}

	/**
	 * checks cache and looks if a path exist (in workspace, rootpid, language)
	 *
	 * @param string $pagePath
	 * @param int $ignoreUid
	 * @return mixed string|bool unique path in cache
	 */
	protected function readCacheForPath($pagePath, $ignoreUid = NULL) {

		if (is_numeric($ignoreUid)) {
			$where = 'path=' . $this->dbObj->fullQuoteStr($pagePath, 'tx_realurl_cache') . ' AND pageid != "' . intval($ignoreUid) . '" ';
		} else {
			$where = 'path=' . $this->dbObj->fullQuoteStr($pagePath, 'tx_realurl_cache') . ' ';
		}
		$where .= $this->getAddCacheWhere(TRUE);
		if (method_exists($this->dbObj, 'exec_SELECTquery_master')) {
				// Force select to use master server in t3p_scalable
			$res = $this->dbObj->exec_SELECTquery_master('*', 'tx_realurl_cache', $where);
		} else {
			$res = $this->dbObj->exec_SELECTquery('*', 'tx_realurl_cache', $where);
		}
		if ($res) {
			$result = $this->dbObj->sql_fetch_assoc($res);
		}
		if ($result['pageid']) {
			return $result['pageid'];
		} else {
			return FALSE;
		}
	}

	/**
	 * checks cache and looks if a path exist (in workspace, rootpid, language)
	 *
	 * @param string Path
	 * @return string unique path in cache
	 */
	protected function readHistoryCacheForPath($pagePath) {
		$where = 'path=' . $this->dbObj->fullQuoteStr($pagePath, 'tx_realurl_cachehistory') . $this->getAddCacheWhere(TRUE);
		$res = $this->dbObj->exec_SELECTquery('*', 'tx_realurl_cachehistory', $where);
		if ($res) {
			$result = $this->dbObj->sql_fetch_assoc($res);
		}
		if ($result['pageid']) {
			return $result['pageid'];
		} else {
			return FALSE;
		}
	}

	/**
	 * check if a pid has allready a builded path in cache (for workspace,language, rootpid)
	 *
	 * @param int $pid
	 * @return mixed - false or pagepath
	 */
	public function isInCache($pid) {
		$return = FALSE;
		$row = $this->getCacheRowForPid($pid);
		if (is_array($row) && $this->isCacheRowStillValid($row)) {
			$return = $row['path'];
		}
		return $return;
	}

	/**
	 *
	 * @param int $pid
	 * @return array
	 */
	public function getCacheRowForPid($pid) {

		$cacheKey = $this->getCacheKey($pid);
		if (isset($this->cache[$cacheKey]) && is_array($this->cache[$cacheKey])) {
			return $this->cache[$cacheKey];
		}

		$row = FALSE;
		$where = 'pageid=' . intval($pid) . $this->getAddCacheWhere();
		if (method_exists($this->dbObj, 'exec_SELECTquery_master')) {
				// Force select to use master server in t3p_scalable
			$query = $this->dbObj->exec_SELECTquery_master('*', 'tx_realurl_cache', $where);
		} else {
			$query = $this->dbObj->exec_SELECTquery('*', 'tx_realurl_cache', $where);
		}
		if ($query) {
			$row = $this->dbObj->sql_fetch_assoc($query);
		}

		if (is_array($row)) {
			$this->cache[$cacheKey] = $row;
		}

		return $row;
	}

	/**
	 *
	 * @param int $pid
	 * @return array
	 */
	public function getCacheHistoryRowsForPid($pid) {
		$rows = array();
		$where = 'pageid=' . intval($pid) . $this->getAddCacheWhere();
		$query = $this->dbObj->exec_SELECTquery('*', 'tx_realurl_cachehistory', $where);
		while ($row = $this->dbObj->sql_fetch_assoc($query)) {
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 *
	 * @param integer $pid
	 * @param string $newPath
	 * @return void
	 */
	protected function checkForCleanupCache($pid, $newPath) {
		$row = $this->getCacheRowForPid($pid);
		if (!is_array($row)) {
			return FALSE;
		} elseif (!$this->isCacheRowStillValid($row)) {
			if ($newPath != $row['path']) {
				$this->insertInCacheHistory($row);
			}
			$this->delCacheForPid($row['pageid']);
		}
	}

	/**
	 *
	 * @param array $row
	 * @return boolean
	 */
	public function isCacheRowStillValid($row) {
		$rowIsValid = TRUE;
		if ($row['dirty'] == 1) {
			$rowIsValid = FALSE;
		} elseif (($this->cacheTimeOut > 0) && (($row['tstamp'] + $this->cacheTimeOut) < $GLOBALS['EXEC_TIME'])) {
			$rowIsValid = FALSE;
		}
		return $rowIsValid;
	}

	/**
	 *
	 * @param int $pid
	 * @return void
	 */
	protected function delCacheForPid($pid) {
		$this->cache[$this->getCacheKey($pid)] = FALSE;
		$where = 'pageid=' . intval($pid) . $this->getAddCacheWhere();
		$this->dbObj->exec_DELETEquery('tx_realurl_cache', $where);
	}

	/**
	 *
	 * @param int $pid
	 * @return void
	 */
	public function delCacheForCompletePid($pid) {
		$where = 'pageid=' . intval($pid) . ' AND workspace=' . intval($this->getWorkspaceId());
		$this->dbObj->exec_DELETEquery('tx_realurl_cache', $where);
	}

	/**
	 *
	 * @param int $pid
	 * @return void
	 */
	public function markAsDirtyCompletePid($pid) {
		$where = 'pageid=' . intval($pid) . ' AND workspace=' . intval($this->getWorkspaceId());
		$this->dbObj->exec_UPDATEquery('tx_realurl_cache', $where, array('dirty' => 1));
	}

	/**
	 *
	 * @param array $row
	 * @return void
	 */
	public function insertInCacheHistory($row) {
		unset($row['dirty']);
		$row['tstamp'] = $GLOBALS['EXEC_TIME'];
		$this->dbObj->exec_INSERTquery('tx_realurl_cachehistory', $row);
	}

	/**
	 *
	 * @return void
	 */
	public function clearAllCache() {
		$this->dbObj->exec_DELETEquery('tx_realurl_cache', '1=1');
		$this->dbObj->exec_DELETEquery('tx_realurl_cachehistory', '1=1');
	}

	/**
	 *
	 * @return void
	 */
	public function clearAllCacheHistory() {
		$this->dbObj->exec_DELETEquery('tx_realurl_cachehistory', '1=1');
	}

	/**
	 * get where for cache table selects based on internal vars
	 *
	 * @param boolean $withRootPidCheck - is required when selecting for paths -> which should be unique for RootPid
	 * @return string -where clause
	 */
	protected function getAddCacheWhere($withRootPidCheck = FALSE) {
		if ($this->useUnstrictCacheWhere) {
				// without the additional keys, for compatibility reasons
			$where = '';
		} else {
			$where = ' AND workspace IN (0,' . intval($this->getWorkspaceId()) . ') AND languageid=' . intval($this->getLanguageId());
		}
		if ($withRootPidCheck) {
			$where .= ' AND rootpid=' . intval($this->getRootPid());
		}
		return $where;
	}

	/**
	 * Get cache key
	 *
	 * @param int $pid
	 * @return string
	 */
	protected function getCacheKey($pid) {
		return implode('-', array($pid, $this->getRootPid(), $this->getWorkspaceId(), $this->getLanguageId()));
	}
}
