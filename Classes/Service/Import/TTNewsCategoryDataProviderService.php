<?php
namespace BeechIt\NewsTtnewsimport\Service\Import;

/***************************************************************
*  Copyright notice
*
*  (c) 2011 Nikolas Hagelstein <nikolas.hagelstein@gmail.com>
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
use GeorgRinger\News\Service\Import\DataProviderServiceInterface;

/**
 * tt_news category ImportService
 *
 * @package TYPO3
 * @subpackage news_ttnewsimport
 */
class TTNewsCategoryDataProviderService implements DataProviderServiceInterface, \TYPO3\CMS\Core\SingletonInterface {

	protected $importSource = 'TT_NEWS_CATEGORY_IMPORT';

	/**
	 * Get total count of category records
	 *
	 * @return integer
	 */
	public function getTotalRecordCount() {
	    $rows = \Tx_Rnbase_Database_Connection::getInstance()->doSelect('count(uid) as cnt', 'tt_news_cat', [
	        'enablefieldsoff' => 1,
	        'where' => 'deleted=0',
	    ]);
	    $count = $rows[0]['cnt'];
// 	    $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)',
// 			'tt_news_cat',
// 			'deleted=0'
// 		);

// 		list($count) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
// 		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		return (int)$count;
	}

	/**
	 * Get the partial import data, based on offset + limit
	 *
	 * @param integer $offset offset
	 * @param integer $limit limit
	 * @return array
	 */
	public function getImportData($offset = 0, $limit = 200) {
		$importData = [];
		$db = \Tx_Rnbase_Database_Connection::getInstance();

		$rows = $db->doSelect('*', 'tt_news_cat', [
		    'enablefieldsoff' => 1,
		    'where' => 'deleted=0',
		    'offset' => $offset,
		    'limit' => $limit,
		]);
		
// 		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',
// 			'tt_news_cat',
// 			'deleted=0',
// 			'',
// 			'',
// 			$offset . ',' . $limit
// 		);

//		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
		foreach ($rows as $row) {
			$importData[] = array(
				'pid' => $row['pid'],
				'hidden' => $row['hidden'],
				'tstamp' => $row['tstamp'],
				'crdate' => $row['crdate'],
				'starttime' => $row['starttime'],
				'endtime'  => $row['endtime'],
				'title_lang_ol'  => $row['title_lang_ol'],
				'title'	=>	$row['title'],
				'description' => $row['description'],
				'image' => $row['image'] ? 'uploads/pics/' . $row['image'] : '',
				'shortcut' => $row['shortcut'],
				'single_pid' => $row['single_pid'],
				'parentcategory' => $row['parent_category'],
				'import_id' =>  $row['uid'],
				'import_source' => $this->importSource
			);
		}
//		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		return $importData;
	}
}