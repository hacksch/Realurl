<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Tolleiv Nietsch <nietsch@aoemedia.de>
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
class Tx_Realurl_EnvironmentTest extends Tx_Phpunit_TestCase {

	/**
	 * Just to have some confidence about the system settings ;)
	 *
	 * @test
	 * @return void
	 */
	public function pageOverlayFieldExists() {
		$list = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields']);
		$this->assertEquals(
			TRUE,
			in_array('tx_realurl_pathsegment', $list),
			'tx_realurl_pathsegment missing in $GLOBALS[\'TYPO3_CONF_VARS\'][\'FE\'][\'pageOverlayFields\']'
		);
		$this->assertEquals(
			TRUE,
			in_array('tx_realurl_pathoverride', $list),
			'tx_realurl_pathoverride missing in $GLOBALS[\'TYPO3_CONF_VARS\'][\'FE\'][\'pageOverlayFields\']'
		);
		$this->assertEquals(
			TRUE,
			in_array('tx_realurl_exclude', $list),
			'tx_realurl_exclude missing in $GLOBALS[\'TYPO3_CONF_VARS\'][\'FE\'][\'pageOverlayFields\']'
		);
	}

	/**
	 * Just to have some confidence about the system settings ;)
	 *
	 * @test
	 * @return void
	 */
	public function rootlineFieldExists() {
		$list = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields']);
		$this->assertEquals(
			TRUE,
			in_array('tx_realurl_pathsegment', $list),
			'tx_realurl_pathsegment missing in ,$GLOBALS[\'TYPO3_CONF_VARS\'][\'FE\'][\'addRootLineFields\']'
		);
		$this->assertEquals(
			TRUE,
			in_array('tx_realurl_pathoverride', $list),
			'tx_realurl_pathoverride missing in ,$GLOBALS[\'TYPO3_CONF_VARS\'][\'FE\'][\'addRootLineFields\']'
		);
		$this->assertEquals(
			TRUE,
			in_array('tx_realurl_exclude', $list),
			'tx_realurl_exclude missing in ,$GLOBALS[\'TYPO3_CONF_VARS\'][\'FE\'][\'addRootLineFields\']'
		);
	}
}
