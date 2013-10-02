<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2008 AOE media GmbH
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * WARNING: Never ever run a unit test like this on a live site!
 *
 * @author	Daniel Pötzinger
 * @author	Tolleiv Nietsch
 */
class Tx_Realurl_CacheManagementTest extends Tx_Phpunit_Database_TestCase {

	protected $rootlineFields;

	/**
	 * @return void
	 */
	public function setUp() {
		$GLOBALS['TYPO3_DB']->debugOutput = TRUE;
		$this->createDatabase();
		$this->useTestDatabase();
		$this->importStdDB();

			// make sure addRootlineFields has the right content - otherwise we experience DB-errors within testdb
		$this->rootlineFields = $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'];
		$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] = 'tx_realurl_pathsegment,tx_realurl_pathoverride,tx_realurl_exclude';

			//create relevant tables:
		$extList = array('cms', 'core', 'frontend', 'realurl', 'workspaces');
		$extOptList = array('templavoila', 'languagevisibility');
		foreach ($extOptList as $ext) {
			if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($ext)) {
				$extList[] = $ext;
			}
		}
		$this->importExtensions($extList);

		$this->importDataSet(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl', 'Tests/Fixtures/page-livews.xml'));
		$this->importDataSet(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl', 'Tests/Fixtures/page-ws.xml'));
	}

	/**
	 * @return void
	 */
	public function tearDown() {
		$this->cleanDatabase();
		$this->dropDatabase();
		$GLOBALS['TYPO3_DB']->sql_select_db(TYPO3_db);
		$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] = $this->rootlineFields;
	}

	/**
	 * Basic cache storage / retrieval works as supposed
	 *
	 * @test
	 * @return void
	 */
	public function storeInCache() {
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);

		$cacheReflection =  new ReflectionClass('Tx_Realurl_CacheManagement');
		$delCacheForPid = $cacheReflection->getMethod('delCacheForPid');
		$delCacheForPid->setAccessible(TRUE);

		$cache->setCacheTimeOut(200);
		$cache->setRootPid(1);
		$cache->storeUniqueInCache('9999', 'test9999');
		$this->assertEquals('test9999', $cache->isInCache(9999), 'should be in cache');

		$delCacheForPid->invokeArgs($cache, array(9999));
		$this->assertFalse($cache->isInCache(9999), 'should not be in cache');
	}

	/**
	 * Storing empty paths should work as supposed
	 *
	 * @test
	 * @return void
	 */
	public function storeEmptyInCache() {
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);
		$cacheReflection =  new ReflectionClass('Tx_Realurl_CacheManagement');
		$delCacheForPid = $cacheReflection->getMethod('delCacheForPid');
		$delCacheForPid->setAccessible(TRUE);

		$cache->clearAllCache();
		$cache->setCacheTimeOut(200);
		$cache->setRootPid(1);
		$path = $cache->storeUniqueInCache('9995', '');
		$this->assertEquals('', $path, 'should be empty path');
		$this->assertEquals('', $cache->isInCache(9995), 'should be in cache');

		$path = $cache->storeUniqueInCache('9995', '');
		$this->assertEquals('', $path, 'should be empty path');
		$this->assertEquals('', $cache->isInCache(9995), 'should be in cache');

		$delCacheForPid->invokeArgs($cache, array(9995));
		$this->assertFalse($cache->isInCache(9995), 'should not be in cache');
	}

	/**
	 * Retrieving empty paths works as supposed
	 *
	 * @test
	 * @return void
	 */
	public function getEmptyFromCache() {
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);
		$cache->clearAllCache();
		$cache->setCacheTimeOut(200);
		$cache->setRootPid(1);
		$cache->storeUniqueInCache('9995', '');
		$pidOrFalse = $cache->checkCacheWithDecreasingPath(array(''), $dummy);
		$this->assertEquals($pidOrFalse, 9995, 'should be in cache');
	}

	/**
	 * Cache avoids collisions
	 *
	 * @test
	 * @return void
	 */
	public function storeInCacheCollision() {
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);
		$cache->setCacheTimeOut(200);
		$cache->setRootPid(1);
		$cache->storeUniqueInCache('9999', 'test9999');
		$this->assertEquals('test9999', $cache->isInCache(9999), 'should be in cache');
		$cache->storeUniqueInCache('9998', 'test9999');
		$this->assertEquals ('test9999_9998', $cache->isInCache(9998), 'should be in cache');
	}

	/**
	 * Cache avoids collisions
	 *
	 * @test
	 * @return void
	 */
	public function storeInCacheCollisionInWorkspace() {

			// new cachemgm for live workspace
		$liveCache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);
		$liveCache->setCacheTimeOut(200);
		$liveCache->setRootPid(1);
		$liveCache->storeUniqueInCache('1000', 'test1000');
		$this->assertEquals('test1000', $liveCache->isInCache(1000), 'should be in cache');
		unset($liveCache);

			// new cachemgm with workspace setting
		$workspaceCache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 1, 0);
		$workspaceCache->setCacheTimeOut(200);
		$workspaceCache->setRootPid(1);
			// assuming that 1001 is a different page
		$workspaceCache->storeUniqueInCache('1001', 'test1000');
		$this->assertEquals('test1000_1001', $workspaceCache->isInCache(1001), 'should be in cache');

			// assuming that 1010 is a workspace overlay for 1000
		$workspaceCache->storeUniqueInCache('1010', 'test1000');
		$this->assertEquals('test1000', $workspaceCache->isInCache(1010), 'should be in cache');

			// assuming that 1020 is a workspace overlay for 1002
		$workspaceCache->storeUniqueInCache('1020', 'test1002');
		$this->assertEquals('test1002', $workspaceCache->isInCache(1020), 'should be in cache');
		unset($workspaceCache);

			// new cachemgm without workspace setting
		$liveCache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);
		$liveCache->setCacheTimeOut(200);
		$liveCache->setRootPid(1);
			// now try to add the live record to cache
		$liveCache->storeUniqueInCache('1002', 'test1002');
		$this->assertEquals('test1002', $liveCache->isInCache(1002), 'should be in cache');
		unset($liveCache);
	}

	/**
	 * Cache collision detection makes sure that even if a workspace uses the cache
	 * no false positive collision between LIVE and Workspace is found
	 *
	 * @test
	 * @return void
	 */
	public function storeInCacheNoCollisionInLiveWorkspace() {

			// new cachemgm for live workspace
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 1, 0);
		$cache->setCacheTimeOut(200);
		$cache->setRootPid(1);
		$cache->storeUniqueInCache('1001', 'test1000');
		$this->assertEquals('test1000', $cache->isInCache(1001), 'should be in cache');
		unset($cache);

		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);
		$cache->setCacheTimeOut(200);
		$cache->setRootPid(1);
		$cache->storeUniqueInCache('1000', 'test1000');
		$this->assertEquals('test1000', $cache->isInCache(1000), 'should be in cache and should not collide with the workspace-record');

	}

	/**
	 * Cache should work within several workspaces
	 *
	 * @test
	 * @return void
	 */
	public function storeInCacheWithoutCollision() {
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);
		$cache->clearAllCache();
		$cache->setCacheTimeOut(200);
		$cache->setRootPid(1);
		$cache->storeUniqueInCache('9990', 'sample');
		$this->assertEquals('sample', $cache->isInCache(9990), 'sample should be in cache');
			//store same page in another workspace
		$cache->setWorkspaceId = 2;
		$cache->storeUniqueInCache('9990', 'sample');
		$this->assertEquals ('sample', $cache->isInCache(9990), 'sample should be in cache for workspace=2');
			//	store same page in another workspace
		$cache->setWorkspaceId = 3;
		$cache->storeUniqueInCache('9990', 'sample');
		$this->assertEquals('sample', $cache->isInCache(9990), 'should be in cache for workspace=3');
			//	and in another language also
		$cache->setLanguageId = 1;
		$cache->storeUniqueInCache('9990', 'sample');
		$this->assertEquals('sample', $cache->isInCache(9990), 'should be in cache for workspace=3 and language=1');
	}

	/**
	 * Check retrieval from cache
	 *
	 * @test
	 * @return void
	 */
	public function pathRetrieval() {
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);
		$cache->clearAllCache();
		$cache->setCacheTimeOut(200);
		$cache->setRootPid(1);
		$cache->storeUniqueInCache('9990', 'sample/path1');
		$cache->storeUniqueInCache('9991', 'sample/path1/path2');
		$cache->storeUniqueInCache('9992', 'sample/newpath1/path3');
		$dummy = array();
		$pidOrFalse = $cache->checkCacheWithDecreasingPath(array('sample', 'path1'), $dummy);
		$this->assertEquals($pidOrFalse, '9990', '9990 should be found for path');
		$dummy = array();
		$pidOrFalse = $cache->checkCacheWithDecreasingPath(array('sample', 'path1', 'nothing'), $dummy);
		$this->assertEquals($pidOrFalse, '9990', '9990 should be found for path');
		$dummy = array();
		$pidOrFalse = $cache->checkCacheWithDecreasingPath(array('sample', 'path2'), $dummy);
		$this->assertEquals($pidOrFalse, FALSE, ' should not be found for path');
	}

	/**
	 * Cache-rows should be invalid whenever they're marked as dirty or expired
	 *
	 * @test
	 * @return void
	 */
	public function canDetectRowAsInvalid() {
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);

		$cacheReflection =  new ReflectionClass('Tx_Realurl_CacheManagement');
		$isCacheRowStillValid = $cacheReflection->getMethod('isCacheRowStillValid');
		$isCacheRowStillValid->setAccessible(TRUE);

		$cache->setCacheTimeOut(1);

		$this->assertFalse($isCacheRowStillValid->invokeArgs($cache, array(array('dirty' => '1'), FALSE)), 'should return false');
		$this->assertFalse($isCacheRowStillValid->invokeArgs($cache, array(array('tstamp' => ($GLOBALS['EXEC_TIME'] - 2)), FALSE)), 'should return false');
	}

	/**
	 * Check whether history-handling works as supposed
	 *
	 * @test
	 * @return void
	 */
	public function canStoreAndGetFromHistory() {
		$cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_CacheManagement', 0, 0);
		$cache->clearAllCache();
		$cache->setCacheTimeOut(1);
		$cache->setRootPid(1);
		$cache->storeUniqueInCache('9990', 'sample/path1');

		$dummy = array();
		$pidOrFalse = $cache->checkCacheWithDecreasingPath(array('sample', 'path1'), $dummy);
		$this->assertEquals($pidOrFalse, '9990', '9990 should be found for path');

		sleep(2);
			// back to the future ;)
		$GLOBALS['EXEC_TIME'] = $GLOBALS['EXEC_TIME'] + 2;

		$dummy = array();
		$pidOrFalse = (int) $cache->checkCacheWithDecreasingPath(array('sample', 'path1'), $dummy);
		$this->assertEquals($cache->isInCache($pidOrFalse), FALSE, 'cache should be expired');

		$cache->storeUniqueInCache('9990', 'sample/path1new');
		$dummy = array();
		$pidOrFalse = $cache->checkCacheWithDecreasingPath(array('sample', 'path1new'), $dummy);
		$this->assertEquals($pidOrFalse, '9990', '9990 should be the path');
			//now check history
		$pidOrFalse = $cache->checkHistoryCacheWithDecreasingPath(array('sample', 'path1'), $dummy);
		$this->assertEquals($pidOrFalse, '9990', '9990 should be the pid in history');
	}
}
