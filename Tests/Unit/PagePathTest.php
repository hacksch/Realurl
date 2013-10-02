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
 * @author	Tolleiv Nietsch
 */
class Tx_Realurl_PagePathTest extends Tx_Phpunit_TestCase {

	/**
	 * just test that 0 is returned even if nothing is submitted and realurl returns non-int
	 *
	 * @test
	 * @return void
	 */
	public function defaultLanguageIs0() {
		$mock = $this->getMock('tx_realurl', array());

		$pagePath = new Tx_Realurl_PagePath();

		$pagePathReflection =  new ReflectionClass('Tx_Realurl_PagePath');
		$setConf = $pagePathReflection->getMethod('setConf');
		$setConf->setAccessible(TRUE);
		$setParent = $pagePathReflection->getMethod('setParent');
		$setParent->setAccessible(TRUE);
		$getLanguageVarEncode = $pagePathReflection->getMethod('getLanguageVarEncode');
		$getLanguageVarEncode->setAccessible(TRUE);

		$setConf->invokeArgs($pagePath, array(array()));
		$setParent->invokeArgs($pagePath, array($mock));

		$this->assertEquals(TRUE, 0 === $getLanguageVarEncode->invokeArgs($pagePath, array()), 'Wrong default language');

	}

	/**
	 *
	 * @test
	 * @return void
	 */
	public function basicLanguageDetectionWorks() {
		$mock = $this->getMock('tx_realurl', array());
		$mock->orig_paramKeyValues['L'] = 3;

		$pagePath = new Tx_Realurl_PagePath();

		$pagePathReflection =  new ReflectionClass('Tx_Realurl_PagePath');
		$setConf = $pagePathReflection->getMethod('setConf');
		$setConf->setAccessible(TRUE);
		$setParent = $pagePathReflection->getMethod('setParent');
		$setParent->setAccessible(TRUE);
		$getLanguageVarEncode = $pagePathReflection->getMethod('getLanguageVarEncode');
		$getLanguageVarEncode->setAccessible(TRUE);

		$setConf->invokeArgs($pagePath, array(array('languageGetVar' => 'L')));
		$setParent->invokeArgs($pagePath, array($mock));

		$this->assertEquals(3, $getLanguageVarEncode->invokeArgs($pagePath, array()), 'Wrong language detected');

	}

	/**
	 *
	 * @test
	 * @return void
	 */
	public function abbrevLisTheDefaultAbbreviation() {
		$mock = $this->getMock('tx_realurl', array());
		$mock->orig_paramKeyValues['L'] = 3;

		$pagePath = new Tx_Realurl_PagePath();

		$pagePathReflection =  new ReflectionClass('Tx_Realurl_PagePath');
		$setConf = $pagePathReflection->getMethod('setConf');
		$setConf->setAccessible(TRUE);
		$setParent = $pagePathReflection->getMethod('setParent');
		$setParent->setAccessible(TRUE);
		$getLanguageVarEncode = $pagePathReflection->getMethod('getLanguageVarEncode');
		$getLanguageVarEncode->setAccessible(TRUE);

		$setConf->invokeArgs($pagePath, array(array()));
		$setParent->invokeArgs($pagePath, array($mock));

		$this->assertEquals(3, $getLanguageVarEncode->invokeArgs($pagePath, array()), 'it seems that L is not used as default-parameter for the language detection');

	}

	/**
	 * makes sure that there's no fixed dependency to the "L" anymore
	 *
	 * @test
	 * @return void
	 */
	public function languageAbbrevCanBeChanged() {
		$mock = $this->getMock('tx_realurl', array());
		$mock->orig_paramKeyValues['L'] = 3;
		$mock->orig_paramKeyValues['newL'] = 10;

		$pagePath = new Tx_Realurl_PagePath();

		$pagePathReflection =  new ReflectionClass('Tx_Realurl_PagePath');
		$setConf = $pagePathReflection->getMethod('setConf');
		$setConf->setAccessible(TRUE);
		$setParent = $pagePathReflection->getMethod('setParent');
		$setParent->setAccessible(TRUE);
		$getLanguageVarEncode = $pagePathReflection->getMethod('getLanguageVarEncode');
		$getLanguageVarEncode->setAccessible(TRUE);

		$setConf->invokeArgs($pagePath, array(array('languageGetVar' => 'newL')));
		$setParent->invokeArgs($pagePath, array($mock));

		$this->assertEquals(10, $getLanguageVarEncode->invokeArgs($pagePath, array()), 'seems that we\'re using the wrong GET var to read the language');

	}

	/**
	 * makes sure that the exception (blacklist) work
	 *
	 * @test
	 * @return void
	 */
	public function languageExceptionsWork() {
		$mock = $this->getMock('tx_realurl', array());
		$mock->orig_paramKeyValues['L'] = 3;

		$pagePath = new Tx_Realurl_PagePath();

		$pagePathReflection =  new ReflectionClass('Tx_Realurl_PagePath');
		$setConf = $pagePathReflection->getMethod('setConf');
		$setConf->setAccessible(TRUE);
		$setParent = $pagePathReflection->getMethod('setParent');
		$setParent->setAccessible(TRUE);
		$getLanguageVarEncode = $pagePathReflection->getMethod('getLanguageVarEncode');
		$getLanguageVarEncode->setAccessible(TRUE);

		$setConf->invokeArgs($pagePath, array(array('languageExceptionUids' => '2')));
		$setParent->invokeArgs($pagePath, array($mock));

		$this->assertEquals( 3, $getLanguageVarEncode->invokeArgs($pagePath, array()), 'it seems that a regular request was filtered with the blacklist');

		$mock->orig_paramKeyValues['L'] = 2;
		$this->assertEquals( 0, $getLanguageVarEncode->invokeArgs($pagePath, array()), 'it seems that a excepted / blacklisted language wasn\'t filtered');

	}

	/**
	 * makes sure that the realurl-pre-settings are used if there's no other input
	 *
	 * @test
	 * @return void
	 */
	public function languageIsDetectedFromPreVar() {
		$mock = $this->getMock('tx_realurl', array('getDetectedLanguage'));
		$mock->expects($this->any())->method('getDetectedLanguage')->will($this->returnValue(7));

		$pagePath = new Tx_Realurl_PagePath();

		$pagePathReflection =  new ReflectionClass('Tx_Realurl_PagePath');
		$setConf = $pagePathReflection->getMethod('setConf');
		$setConf->setAccessible(TRUE);
		$setParent = $pagePathReflection->getMethod('setParent');
		$setParent->setAccessible(TRUE);
		$getLanguageVarDecode = $pagePathReflection->getMethod('getLanguageVarDecode');
		$getLanguageVarDecode->setAccessible(TRUE);

		$setConf->invokeArgs($pagePath, array(array()));
		$setParent->invokeArgs($pagePath, array($mock));

		$this->assertEquals(7, $getLanguageVarDecode->invokeArgs($pagePath, array()), 'pregetvar isn\'t used as supposed');
	}
}
