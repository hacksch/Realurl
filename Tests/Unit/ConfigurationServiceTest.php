<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 (dev@aoemedia.de)
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
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
class Tx_Realurl_ConfigurationServiceTest extends Tx_Phpunit_TestCase {

	/**
	* @var Tx_Realurl_ConfigurationService
	*/
	protected $configurationService;

	/**
	 * @return void
	 */
	public function setUp() {
			$this->configurationService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Realurl_ConfigurationService');
	}

	/**
	 * @test
	 * @return void
	 */
	public function canGetDefaultConfiguration() {
		$conf = $this->getMultiDomainConfigurationFixture();
		$this->configurationService->setRealUrlConfiguration($conf);
		$this->assertEquals(
			$this->configurationService->getConfigurationForDomain(),
			$conf['_DEFAULT'],
			' wrong configuration returned'
		);
		$this->assertEquals(
			$this->configurationService->getConfigurationForDomain('notconfigured.com'),
			$conf['_DEFAULT'],
			' wrong configuration returned'
		);
	}

	/**
	 * @test
	 * @return void
	 */
	public function canGetHostConfigurationForConfiguredHost() {
		$conf = $this->getMultiDomainConfigurationFixture();
		$this->configurationService->setRealUrlConfiguration($conf);
		$this->assertEquals(
			$this->configurationService->getConfigurationForDomain('www.domain1.com'),
			$conf['www.domain1.com'],
			' wrong configuration returned'
		);
	}

	/**
	 * @return array
	 */
	protected function getMultiDomainConfigurationFixture() {
		$conf = array(
			'_DEFAULT' => array(
				'init' => array(
					'enableCHashCache' => 1,
					'appendMissingSlash' => 'ifNotFile',
					'enableUrlDecodeCache' => 1,
					'enableUrlEncodeCache' => 1,
					'respectSimulateStaticURLs' => 0,
					'postVarSet_failureMode' => 'redirect_goodUpperDir',
				),
				'redirects_regex' => array (
				),
				'preVars' => array(
				),
				'pagePath' => array (
					'type' => 'user',
					'userFunc' => 'EXT:realurl/class.tx_realurl_advanced.php:&tx_realurl_advanced->main',
					'spaceCharacter' => '-',
					'cacheTimeOut' => '100',
					'languageGetVar' => 'L',
					'rootpage_id' => '1',
					'segTitleFieldList' => 'alias,tx_realurl_pathsegment,nav_title,title,subtitle',
				),
			)
		);
		$conf['www.domain1.com'] = $conf['_DEFAULT'];
		$conf['www.domain1.com']['pagePath']['rootpage_id'] = 19;
		return $conf;
	}
}
