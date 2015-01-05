<?php
abstract class tx_realurl_abstractDatabase_testcase extends tx_phpunit_database_testcase {
    /**
     * @var string
     */
    private $rootlineFields;

    /**
     * @var string
     */
    private $globalPageOverlayFields;

    /**
     * setUp test-database
     */
    public function setUp() {
        $GLOBALS['TYPO3_DB']->debugOutput = true;
        $GLOBALS['TSFE']->id = 1;
        //caching
        $cacheConfig = $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'];
        $cacheTags = array('extbase_reflection', 'extbase_object', 'extbase_typo3dbbackend_tablecolumns', 'cache_rootline');
        foreach ($cacheTags as $tag) {
            $cacheConfig[$tag] = array('backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend');
        }
        $cacheManager = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Cache\\CacheManager');
        $cacheManager->setCacheConfigurations($cacheConfig);
        $this->createDatabase();
        $this->useTestDatabase();

        // create DB-tables of some needed extensions:
        $extList = array ('core','frontend','realurl');
        $extOptList = array ('templavoila', 'aoe_templavoila', 'languagevisibility', 'aoe_localizeshortcut', 'devlog');
        foreach ($extOptList as $ext) {
            if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($ext)) {
                $extList [] = $ext;
            }
        }
        $this->importExtensions($extList);
        // make sure addRootlineFields has the right content - otherwise we experience DB-errors within test-database
        $this->rootlineFields = $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'];
        $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] = 'tx_realurl_pathsegment,tx_realurl_pathoverride,tx_realurl_exclude';

        // reset pageoverlay fields
        $this->globalPageOverlayFields = $GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'];
        $GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] = 'uid,title,subtitle,nav_title,media,keywords,description,abstract,author,author_email,url,urltype,shortcut,shortcut_mode,tx_realurl_pathsegment,tx_realurl_exclude,tx_realurl_pathoverride';

    }

    /**
     * drop test-database
     */
    public function tearDown() {
        $this->cleanDatabase();
        $this->dropDatabase();
        $this->switchToTypo3Database();

        $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] = $this->rootlineFields;
        $GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] = $this->globalPageOverlayFields;
    }
}
