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
 * TODO: add testdatabase xml
 *
 * @author  Daniel Pötzinger
 * @author  Tolleiv Nietsch
 */
class Tx_Realurl_PathGeneratorTest extends Tx_Phpunit_Database_TestCase {

	/**
	 * @var Tx_Realurl_PathGenerator
	 */
	protected $pathGenerator;

	/**
	 * @var ReflectionClass
	 */
	protected $pathGeneratorReflection;

	/**
	 * @var string
	 */
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
		$extOptList = array('templavoila', 'languagevisibility', 'aoe_localizeshortcut');
		foreach ($extOptList as $ext) {
			if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($ext)) {
				$extList[] = $ext;
			}
		}
		$this->importExtensions($extList);

		$this->importDataSet(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl', 'Tests/Fixtures/page-livews.xml'));
		$this->importDataSet(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl', 'Tests/Fixtures/overlay-livews.xml'));
		$this->importDataSet(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl', 'Tests/Fixtures/page-ws.xml'));
		$this->importDataSet(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl', 'Tests/Fixtures/overlay-ws.xml'));

		$this->pathGenerator = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_PathGenerator');
		$this->pathGenerator->init($this->fixture_defaultconfig());
		$this->pathGenerator->setRootPid(1);

		$this->pathGeneratorReflection =  new ReflectionClass('Tx_Realurl_PathGenerator');

		if (!is_object($GLOBALS['TSFE'])) {
			$GLOBALS['TSFE'] = new stdClass();
		}
		if (!is_object($GLOBALS['TSFE']->csConvObj)) {
			$GLOBALS['TSFE']->csConvObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Charset\CharsetConverter');
		}
	}

	/**
	 * @return void
	 */
	public function tearDown() {
		$this->cleanDatabase ();
		$this->dropDatabase ();
		$GLOBALS['TYPO3_DB']->sql_select_db(TYPO3_db);
		$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] = $this->rootlineFields;
	}

	/**
	 * Rootline retrieval needs to work otherwise we can't generate paths
	 *
	 * @test
	 * @return void
	 */
	public function canGetCorrectRootline() {
		$getRootLine = $this->pathGeneratorReflection->getMethod('getRootLine');
		$getRootLine->setAccessible(TRUE);

		$result = $getRootLine->invokeArgs($this->pathGenerator, array(87, 0, 0));

		$count = count($result);
		$first = $result[0];
		$this->assertEquals($count, 4, 'rootline should be 3 long');
		$this->assertTrue(isset($first['tx_realurl_pathsegment']), 'tx_realurl_pathsegment should be set');
		$this->assertTrue(isset($first['tx_realurl_exclude']), 'tx_realurl_exclude should be set');
	}

	/**
	 * Generator works for standard paths
	 *
	 * @test
	 * @return void
	 */
	public function canBuildStandardPaths() {
			// 1) Rootpage
		$result = $this->pathGenerator->build(1, 0, 0);
		$this->assertEquals($result['path'], '', 'wrong path build: root should be empty');

			// 2) Normal Level 2 page
		$result = $this->pathGenerator->build(83, 0, 0);
		$this->assertEquals($result['path'], 'excludeofmiddle', 'wrong path build: should be excludeofmiddle');

			// 3) Page without title informations
		$result = $this->pathGenerator->build(94, 0, 0);
		$this->assertEquals ($result['path'], 'normal-3rd-level/page_94', 'wrong path build: should be normal-3rd-level/page_94 (last page should have default name)');
	}

	/**
	 * Excludes and overrides work as supposed
	 *
	 * @test
	 * @return void
	 */
	public function canBuildPathsWithExcludeAndOverride() {

			// page root->excludefrommiddle->subpage(with pathsegment)
		$result = $this->pathGenerator->build( 85, 0, 0);
		$this->assertEquals($result['path'], 'subpagepathsegment', 'wrong path build: should be subpage');

			// page root->excludefrommiddle->subpage(with pathsegment)
		$result = $this->pathGenerator->build(87, 0, 0);
		$this->assertEquals($result['path'], 'subpagepathsegment/sub-subpage', 'wrong path build: should be subpagepathsegment/sub-subpage');

		$result = $this->pathGenerator->build(80, 0, 0);
		$this->assertEquals($result['path'], 'override/path/item', 'wrong path build: should be override/path/item');

		$result = $this->pathGenerator->build(81, 0, 0);
		$this->assertEquals($result['path'], 'specialpath/withspecial/chars', 'wrong path build: should be specialpath/withspecial/chars');

			// instead of shortcut page the shortcut target should be used within path
		$result = $this->pathGenerator->build( 92, 0, 0);
		$this->assertEquals($result['path'], 'normal-3rd-level/subsection', 'wrong path build: shortcut from uid92 to uid91 should be resolved');

	}

	/**
	 * Excludes and overrides work as supposed
	 *
	 * @test
	 * @return void
	 */
	public function canHandleSelfReferringShortcuts() {
			// shortcuts with a reference to themselfs might be a problem
		$result = $this->pathGenerator->build(95, 0, 0);
		$this->assertEquals( $result['path'], 'shortcut-page', 'wrong path build: shortcut shouldn\'t be resolved' );
	}

	/**
	 * OverridePath is handled right even if it's invalid
	 *
	 * @test
	 * @return void
	 */
	public function invalidOverridePathWillFallBackToDefaultGeneration() {
		$result = $this->pathGenerator->build(82, 0, 0);
		$this->assertEquals($result['path'], 'invalid-overridepath', 'wrong path build: should be invalid-overridepath');
	}

	/**
	 * Languageoverlay is taken into account for pagepaths
	 *
	 * @test
	 * @return void
	 */
	public function canBuildPathsWithLanguageOverlay() {

			// page root->excludefrommiddle->languagemix (austria)
		$result = $this->pathGenerator->build(86, 2, 0);
		$this->assertEquals($result['path'], 'own/url/for/austria', 'wrong path build: should be own/url/for/austria');

			// page root->excludefrommiddle->subpage(with pathsegment)
		$result = $this->pathGenerator->build(85, 2, 0);
		$this->assertEquals($result['path'], 'subpagepathsegment-austria', 'wrong path build: should be subpagepathsegment-austria');

			// page root->excludefrommiddle->subpage (overlay with exclude middle)->sub-subpage
		$result = $this->pathGenerator->build(87, 2, 0);
		$this->assertEquals($result['path'], 'sub-subpage-austria', 'wrong path build: should be subpagepathsegment-austria');

			//for french (5)
		$result = $this->pathGenerator->build(86, 5, 0);
		$this->assertEquals($result['path'], 'languagemix-segment', 'wrong path build: should be languagemix-segment');

			// page root->excludefrommiddle->languagemix (austria)
		$result = $this->pathGenerator->build(101, 5, 0);
		$this->assertEquals($result['path'], 'languagemix-segment/another/vivelafrance', 'wrong path build: should be: languagemix-segment/another/vivelafrance');
	}

	/**
	 * Generating paths per workspace works as supposed
	 *
	 * @test
	 * @return void
	 */
	public function canBuildPathsInWorkspace() {

			// page root->excludefrommiddle->subpagepathsegment-ws
		$result = $this->pathGenerator->build(85, 0, 1);
		$this->assertEquals($result['path'], 'subpagepathsegment-ws', 'wrong path build: should be subpage-ws');

			// page
		$result = $this->pathGenerator->build(86, 2, 1);
		$this->assertEquals($result['path'], 'own/url/for/austria/in/ws', 'wrong path build: should be own/url/for/austria/in/ws');

			//page languagemix in deutsch (only translated in ws)
		$result = $this->pathGenerator->build(86, 1, 1);
		$this->assertEquals($result['path'], 'languagemix-de', 'wrong path build: should be own/url/for/austria/in/ws');

			//page languagemix in deutsch (only translated in ws)
		$result = $this->pathGenerator->build(85, 1, 1);
		$this->assertEquals($result['path'], 'subpage-ws-de', 'wrong path build: should be own/url/for/austria/in/ws');
	}

	/**
	 * Non-latin characters won't break path-generator
	 *
	 * @test
	 * @return void
	 */
	public function canBuildPathIfOverlayUsesNonLatinChars() {

			// some non latin characters are replaced
		$result = $this->pathGenerator->build(83, 4, 0);
		$this->assertEquals($result['path'], 'page-exclude', 'wrong path build: should be pages-exclude');

			// overlay has no latin characters therefore the default record is used
		$result = $this->pathGenerator->build(84, 4, 0);
		$this->assertEquals($result['path'], 'normal-3rd-level', 'wrong path build: should be normal-3rd-level (value taken from default record)');

			// overlay has no latin characters therefore the default record is used
		$result = $this->pathGenerator->build(94, 4, 0);
		$this->assertEquals($result['path'], 'normal-3rd-level/page_94', 'wrong path build: should be normal-3rd-level/page_94 (value from default records and auto generated since non of the pages had relevant chars)');
	}

	/**
	 * Retrieval works for path being a delegation target
	 *
	 * @test
	 * @return void
	 */
	public function canResolvePathFromDeligatedFlexibleURLField() {

		$this->pathGenerator->init($this->fixture_delegationconfig());

			// Test direct delegation
		$result = $this->pathGenerator->build(97, 0, 0);
		$this->assertEquals($result['path'], 'deligation-target', 'wrong path build: deligation should be executed');

			// Test multi-hop delegation
		$result = $this->pathGenerator->build(96, 0, 0);
		$this->assertEquals($result['path'], 'deligation-target', 'wrong path build: deligation should be executed');

	}

	/**
	 * Retrieval works for URL for the external URL Doktype
	 *
	 * @test
	 * @return void
	 */
	public function canResolveURLFromExternalURLField() {

		$this->pathGenerator->init($this->fixture_defaultconfig());

		$result = $this->pathGenerator->build(199, 0, 0);
		$this->assertEquals($result['path'], 'https://www.aoemedia.de', 'wrong path build: external URL is expected');

		$result = $this->pathGenerator->build(199, 4, 0);
		$this->assertEquals($result['path'], 'https://www.aoemedia.de', ' wrong path build: external URL is expected - Chinese records doesn\'t provide own value therefore default-value is used');

		$result = $this->pathGenerator->build(199, 5, 0);
		$this->assertEquals($result['path'], 'https://www.aoemedia.fr', 'wrong path build: external URL is expected - French records is supposed to overlay the url');

	}

	/**
	 * Retrieval works for URL as delegation target
	 *
	 * @test
	 * @return void
	 */
	public function canResolveURLFromDeligatedFlexibleURLField() {

		$this->pathGenerator->init ( $this->fixture_delegationconfig () );

		$result = $this->pathGenerator->build(99, 0, 0);
		$this->assertEquals($result['path'], 'http://www.aoemedia.de', 'wrong path build: deligation should be executed');

	}

	/**
	 * Retrieval works for path being a delegation target
	 *
	 * @test
	 * @return void
	 */
	public function canNotBuildPathForPageInForeignRootline() {
		try {
			$this->pathGenerator->init($this->fixture_defaultconfig());

				// Test direct delegation
			$this->pathGenerator->build(200, 0, 0);
			$this->fail('Exception expected');
		} catch (Exception $e) {
		}
	}

	/**
	 * Basic configuration (strict mode)
	 *
	 * @return array
	 */
	public function fixture_defaultconfig() {
		$conf = array (
			'type' => 'user',
			'userFunc' => 'tx_realurl_advanced->main',
			'spaceCharacter' => '-',
			'cacheTimeOut' => '100',
			'languageGetVar' => 'L',
			'rootpage_id' => '1',
			'strictMode' => 1,
			'segTitleFieldList' => 'alias,tx_realurl_pathsegment,nav_title,title,subtitle'
		);
		return $conf;
	}

	/**
	 * Configuration with enabled delegation function for pagetype 77
	 *
	 */
	public function fixture_delegationconfig() {
		$conf = array (
			'type' => 'user',
			'userFunc' => 'EXT:realurl/class.tx_realurl_advanced.php:&tx_realurl_advanced->main',
			'spaceCharacter' => '-',
			'cacheTimeOut' => '100',
			'languageGetVar' => 'L',
			'rootpage_id' => '1',
			'strictMode' => 1,
			'segTitleFieldList' => 'alias,tx_realurl_pathsegment,nav_title,title,subtitle',
			'delegation' => array (77 => 'url' )
		);
		return $conf;
	}

	/**
	 * Changes current database to test database
	 *
	 * @param string $databaseName	Overwrite test database name
	 * @return object
	 */
	protected function useTestDatabase($databaseName = NULL) {
		$db = $GLOBALS['TYPO3_DB'];

		if ($databaseName) {
			$database = $databaseName;
		} else {
			$database = $this->testDatabase;
		}

		if (! $db->sql_select_db ( $database )) {
			die('Test Database not available');
		}

		return $db;
	}
}
