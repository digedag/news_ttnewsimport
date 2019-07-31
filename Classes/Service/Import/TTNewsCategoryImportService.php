<?php
namespace BeechIt\NewsTtnewsimport\Service\Import;
/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use GeorgRinger\News\Domain\Service\CategoryImportService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * tt_news category import service
 *
 * @package TYPO3
 * @subpackage tx_news
 * @author Lorenz Ulrich <lorenz.ulrich@visol.ch>
 */
class TTNewsCategoryImportService extends CategoryImportService {

	/**
	 * @var \Tx_Rnbase_Database_Connection
	 */
	protected $databaseConnection;

	public function __construct() {
		parent::__construct();
//		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
		$this->databaseConnection = \Tx_Rnbase_Database_Connection::getInstance();
	}

	public function import(array $importArray) {
		// import categories
		parent::import($importArray);

		$this->migrateTtNewsCategoryMountsToSysCategoryPerms('be_groups');
		$this->migrateTtNewsCategoryMountsToSysCategoryPerms('be_users');

	}

	/**
	 * Migrate tt_news_categorymounts to category_pems in either be_groups or be_users
	 *
	 * @param string $table either be_groups or be_users
	 */
	public function migrateTtNewsCategoryMountsToSysCategoryPerms($table) {
		/** @var \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler */
		$dataHandler = GeneralUtility::makeInstance('TYPO3\CMS\Core\DataHandling\DataHandler');
		$dataHandler->admin = TRUE;

		/* assign imported categories to be_groups or be_users */
		$whereClause = 'tt_news_categorymounts != \'\'' . BackendUtility::deleteClause($table);
//		$beGroupsOrUsersWithTtNewsCategorymounts = $this->databaseConnection->exec_SELECTgetRows('*', $table, $whereClause);
		$beGroupsOrUsersWithTtNewsCategorymounts = $this->databaseConnection->doSelect('*', $table, [
		    'where'=>$whereClause,
		    'enablefieldsoff' => 1
		]);

		$data = [];

		foreach ((array)$beGroupsOrUsersWithTtNewsCategorymounts as $beGroupOrUser) {
			$ttNewsCategoryPermissions = GeneralUtility::trimExplode(',', $beGroupOrUser['tt_news_categorymounts']);
			$sysCategoryPermissions = [];
			foreach ($ttNewsCategoryPermissions as $ttNewsCategoryPermissionUid) {
				$whereClause = 'import_source = \'TT_NEWS_CATEGORY_IMPORT\' AND import_id = ' . $ttNewsCategoryPermissionUid;
				$sysCategory = $this->databaseConnection->doSelect('uid', 'sys_category', [
				    'where' => $whereClause,
				    'enablefieldsoff' => 1
				]);
				$sysCategory = reset($sysCategory);
				if (!empty($sysCategory)) {
					$sysCategoryPermissions[] = $sysCategory['uid'];
				}
			}
			if (count($sysCategoryPermissions)) {
				$data[$table][$beGroupOrUser['uid']] = [
					'category_perms' => implode(',', $sysCategoryPermissions) . ',' . $beGroupOrUser['category_perms']
				];
			}
		}
		$dataHandler->start($data, []);
		$dataHandler->process_datamap();
	}

}
