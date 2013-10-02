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

class Tx_Realurl_ConfigurationService {

	/**
	 * @var array
	 */
	protected $confArray = array();

	/**
	 * @var bool
	 */
	protected $useAutoAdjustRootPid = FALSE;

	/**
	 * @var bool
	 */
	protected $enableDevLog = FALSE;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->loadRealUrlConfiguration();
	}

	/**
	 * @return void
	 */
	public function loadRealUrlConfiguration() {
		$extensionConfiguration = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl']);
			// auto configuration
		if ($extensionConfiguration['enableAutoConf'] && !isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'])) {
			$realurlConfigurationGenerator = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_realurl_autoconfgen');
			$realurlConfigurationGenerator->generateConfiguration();
			unset($realurlConfigurationGenerator);
			@require_once(PATH_site . TX_REALURL_AUTOCONF_FILE);
		}
		$this->confArray = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
	}

	/**
	 * @param array $conf
	 * @return void
	 */
	public function setRealUrlConfiguration(array $conf) {
		$this->confArray = $conf;
	}

	/**
	 * @param string $host
	 * @return array $extConf
	 * @throws Exception
	 */
	public function getConfigurationForDomain($host = '') {
		if ($host === '') {
			$host = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');
		}
			// First pass, finding configuration OR pointer string:
		if (isset($this->confArray[$host])) {
			$extConf = $this->confArray[$host];
				// If it turned out to be a string pointer, then look up the real config:
			while (!is_null($extConf) && is_string($extConf)) {
				$extConf = $this->confArray[$this->extConf];
			}
			if (!is_array($extConf)) {
				$extConf = $this->confArray['_DEFAULT'];
				if ($this->multidomain && isset($extConf['pagePath']['rootpage_id'])) {
						// This can't be right!
					unset($extConf['pagePath']['rootpage_id']);
				}
			}
		} else {
			if ($this->enableStrictMode && $this->multidomain) {
				throw new Exception('RealURL strict mode error: multidomain configuration detected and domain \'' . $this->host . '\' is not configured for RealURL. Please, fix your RealURL configuration!', 1379315982);
			}
			$extConf = (array)$this->confArray['_DEFAULT'];
			if ($this->multidomain && isset($extConf['pagePath']['rootpage_id']) && $this->enableStrictMode) {
				throw new Exception('Root PID configured for _DEFAULT namespace, this can cause wrong cache entries and should be avoided', 1379315996);
			}
		}

		if ($this->useAutoAdjustRootPid) {
			unset($extConf['pagePath']['rootpage_id']);
			$extConf['pagePath']['rootpage_id'] = $this->findRootPageId($host);
		}

		return $extConf;
	}

	/**
	 * Attempt to find root page ID for the current host. Processes redirects as well.
	 *
	 * @param string $domain
	 * @return bool|int
	 */
	protected function findRootPageId($domain = '') {
		$rootPageId = FALSE;
			// Search by host
		do {
			$domain = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pid,redirectTo,domainName', 'sys_domain', 'domainName=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($domain, 'sys_domain') . ' AND hidden=0');
			if (count($domain) > 0) {
				if (!$domain[0]['redirectTo']) {
					$rootPageId = intval($domain[0]['pid']);
					if ($this->enableDevLog) {
						\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Found rootpage_id by domain lookup', 'realurl', 0, array('domain' => $domain[0]['domainName'], 'rootpage_id' => $rootPageId));
					}
					break;
				} else {
					$parts = @parse_url($domain[0]['redirectTo']);
					$host = $parts['host'];
				}
			}
		} while (count($domain) > 0);

			// If root page id is not found, try other ways. We can do it only
			// and only if there are no multiple domains. Otherwise we would
			// get a lot of wrong page ids from old root pages, etc.
		if (!$rootPageId && !$this->multidomain) {
				// Try by TS template
			$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pid', 'sys_template', 'root=1 AND hidden=0');
			if (count($rows) == 1) {
				$rootPageId = $rows[0]['pid'];
				if ($this->enableDevLog) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Found rootpage_id by searching sys_template', 'realurl', 0, array('rootpage_id' => $rootPageId));
				}
			}
		}
		return $rootPageId;
	}
}
