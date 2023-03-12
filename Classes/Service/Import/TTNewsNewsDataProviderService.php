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

use BeechIt\NewsTtnewsimport\Service\Util\LinkProcessor;
use GeorgRinger\News\Service\Import\DataProviderServiceInterface;
use Sys25\RnBase\Database\Connection;
use Sys25\RnBase\Utility\Strings;
use Sys25\RnBase\Utility\TSFAL;
use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * tt_news ImportService
 *
 * @package TYPO3
 * @subpackage news_ttnewsimport
 */
class TTNewsNewsDataProviderService implements DataProviderServiceInterface, \TYPO3\CMS\Core\SingletonInterface  {

	protected $importSource = 'TT_NEWS_IMPORT';
	protected $linkProcessor;
	public function __construct()
	{
		$this->linkProcessor = new LinkProcessor();
	}

	/**
	 * Get total record count
	 *
	 * @return integer
	 */
	public function getTotalRecordCount() {

		$options = [
	        'enablefieldsoff' => 1,
	        'where' => 'deleted=0 AND t3ver_oid = 0 AND t3ver_wsid = 0',
	    ];
		if ($uids = $this->getOption('uids')) {
			$options['where'] = sprintf('%s AND uid IN (%s)', $options['where'], $uids);
		}
	    $rows = Connection::getInstance()->doSelect('count(uid) as cnt', 'tt_news', $options);
	    $count = $rows[0]['cnt'];

		if ($limit = $this->getOption('limit')) {
			return $limit;
		}
		return (int)$count;
	}

	private function getOption($name, $default = null)
	{
		return $this->options[$name] ?? $default;
	}

	/**
	 * Get the partial import data, based on offset + limit
	 *
	 * @param integer $offset offset
	 * @param integer $limit limit
	 * @return array
	 */
	public function getImportData($offset = 0, $limit = 50) {
		$importData = [];

		$options = [
		    'enablefieldsoff' => 1,
		    'where' => 'deleted=0 AND t3ver_oid = 0 AND t3ver_wsid = 0',
		    'orderby' => 'sys_language_uid ASC',
		    'offset' => $offset,
		    'limit' => $limit,
		];
		if ($uids = $this->getOption('uids')) {
			$options['where'] = sprintf('%s AND uid IN (%s)', $options['where'], $uids);
		}

		$rows = Connection::getInstance()->doSelect('*', 'tt_news', $options);

		foreach ($rows as $row) {
		        
			$importData[] = array(
				'pid' => $row['pid'],
				'hidden' => $row['hidden'],
				'tstamp' => $row['tstamp'],
				'crdate' => $row['crdate'],
				'cruser_id' => $row['cruser_id'],
				'l10n_parent' => $row['l18n_parent'],
				'sys_language_uid' => $row['sys_language_uid'],
				'sorting' => array_key_exists('sorting', $row) ? $row['sorting'] : 0,
				'starttime' => $row['starttime'],
				'endtime'  => $row['endtime'],
				'fe_group'  => $row['fe_group'],
				'title' => $row['title'],
				'teaser' => $row['short'],
				'bodytext' => str_replace('###YOUTUBEVIDEO###', '', $row['bodytext']),
				'datetime' => $row['datetime'],
				'archive' => $row['archivedate'],
				'author' => $row['author'],
				'author_email' => $row['author_email'],
				'type' => $row['type'],
				'keywords' => $row['keywords'],
				'externalurl' => $row['ext_url'],
				'internalurl' => $row['page'],
				'categories' => $this->getCategories($row['uid']),
				'media' => $this->getMedia($row),
				'related_files' => $this->getFiles($row),
				'related_links' => array_key_exists('tx_tlnewslinktext_linktext', $row) ? $this->getRelatedLinksTlNewsLinktext($row['links'], $row['tx_tlnewslinktext_linktext']) : $this->getRelatedLinks($row['links']),
				'content_elements' => $row['tx_rgnewsce_ce'],
				'import_id' => $row['uid'],
				'import_source' => $this->importSource
			);
		}
//		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		return $importData;
	}

	/**
	 * Parses the related files
	 *
	 * @param array $row
	 * @return array
	 */
	protected function getFiles(array $row) {
		$relatedFiles = array();

		// tx_damnews_dam_media
		if (!empty($row['tx_damnews_dam_media'])) {

			// get DAM items
			$files = $this->getDamItems($row['uid'], 'tx_damnews_dam_media');
			foreach ($files as $damUid => $file) {
				$relatedFiles[] = array(
					'file' => $file
				);
			}
		}

		if (!empty($row['news_files'])) {
			$files = GeneralUtility::trimExplode(',', $row['news_files']);

			foreach ($files as $file) {
				$relatedFiles[] = array(
					'file' => 'uploads/media/' . $file
				);
			}
		}

		return $relatedFiles;
	}

	/**
	 * Get correct categories of a news record
	 *
	 * @param integer $newsUid news uid
	 * @return array
	 */
	protected function getCategories($newsUid) {
		$categories = array();

		$rows = \Tx_Rnbase_Database_Connection::getInstance()->doSelect('*', 'tt_news_cat_mm', [
		    'enablefieldsoff' => 1,
		    'where' => 'uid_local=' . $newsUid,
		]);
		
// 		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',
// 			'tt_news_cat_mm',
// 			'uid_local=' . $newsUid);

//		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
		foreach ($rows as $row) {
			$categories[] = $row['uid_foreign'];
		}

//		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		return $categories;
	}

	/**
	 * Get correct media elements to be imported
	 *
	 * @param array $row news record
	 * @return array
	 */
	protected function getMedia(array $row) {
		$media = array();
		$count = 0;

		// tx_damnews_dam_images
		if (!empty($row['tx_damnews_dam_images'])) {

			// get DAM data
			$files = $this->getDamItems($row['uid'], 'tx_damnews_dam_images');

			$captions = GeneralUtility::trimExplode(chr(10), $row['imagecaption'], FALSE);
			$alts = GeneralUtility::trimExplode(chr(10), $row['imagealttext'], FALSE);
			$titles = GeneralUtility::trimExplode(chr(10), $row['imagetitletext'], FALSE);

			foreach ($files as $damUid => $file) {
				$media[] = array(
					'title' => $titles[$count],
					'alt' => $alts[$count],
					'caption' => $captions[$count],
					'image' => $file,
					'showinpreview' => (int)$count == 0
				);
				$count++;
			}
		}
		if (!empty($row['tx_mktools_fal_images'] ?? '')) {
			/** @var \TYPO3\CMS\Core\Resource\FileReference[] $files */
			$files = TSFAL::getReferences('tt_news', $row['uid'], 'tx_mktools_fal_images');
			$captions = Strings::trimExplode(chr(10), $row['imagecaption'], FALSE);
			$alts = Strings::trimExplode(chr(10), $row['imagealttext'], FALSE);
			$titles = Strings::trimExplode(chr(10), $row['imagetitletext'], FALSE);
			foreach ($files as $fileRef) {
//print_r(['m'=> $files, 'f' => $fileRef->getOriginalFile(), 'uid' => $row['uid']]);
				$media[] = array(
					'title' => $titles[$count] ?? $fileRef->getTitle(),
					'alt' => $alts[$count] ?? '',
					'caption' => $captions[$count] ?? '',
					'image' => $fileRef->getOriginalFile()->getUid(),
					'showinpreview' => (int)$count == 0
				);
				$count++;
			}
			print_r(['m'=> $media]);
		}

		if (!empty($row['image'])) {
			$images = GeneralUtility::trimExplode(',', $row['image'], TRUE);
			$captions = GeneralUtility::trimExplode(chr(10), $row['imagecaption'], FALSE);
			$alts = GeneralUtility::trimExplode(chr(10), $row['imagealttext'], FALSE);
			$titles = GeneralUtility::trimExplode(chr(10), $row['imagetitletext'], FALSE);

			$i = 0;
			foreach ($images as $image) {
				$media[] = array(
					'title' => $titles[$i],
					'alt' => $alts[$i],
					'caption' => $captions[$i],
					'image' => 'uploads/pics/' . $image,
					'type' => 0,
					'showinpreview' => (int)$count == 0
				);
				$i ++;
				$count ++;
			}
		}

		$media = array_merge($media, $this->getMultimediaItems($row));

		return $media;
	}

	/**
	 * Get link elements to be imported
	 *
	 * @param string $newsLinks
	 * @return array
	 */
	protected function getRelatedLinks($newsLinks) {
		return $this->linkProcessor->extractRelatedLinks($newsLinks);
	}

	/**
	 * Get link elements to be imported when using EXT:tl_news_linktext
	 * This extension adds an additional field for link texts that are separated by a line break
	 *
	 * @param string $newsLinks
	 * @param string $newsLinksTexts
	 * @return array
	 */
	protected function getRelatedLinksTlNewsLinktext($newsLinks, $newsLinksTexts) {
		$links = array();

		if (empty($newsLinks)) {
			return $links;
		}

		$newsLinks = str_replace("\r\n", "\n", $newsLinks);
		$newsLinksTexts = str_replace("\r\n", "\n", $newsLinksTexts);

		$linkList = GeneralUtility::trimExplode("\n", $newsLinks, TRUE);
		$linkTextList = GeneralUtility::trimExplode("\n", $newsLinksTexts, TRUE);

		$iterator = 0;
		foreach ($linkList as $uri) {
			$links[] = array(
				'uri' => $uri,
				'title' => array_key_exists($iterator, $linkTextList) ? $linkTextList[$iterator] : $uri,
				'description' => '',
			);
			$iterator++;
		}
		return $links;
	}

	/**
	 * Get DAM file names
	 *
	 * @param $newsUid
	 * @param $field
	 * @return array
	 */
	protected function getDamItems($newsUid, $field) {

		$files = array();

		$res = $GLOBALS['TYPO3_DB']->exec_SELECT_mm_query('tx_dam.uid, tx_dam.file_name, tx_dam.file_path',
			'tx_dam', 'tx_dam_mm_ref', 'tt_news',
			'AND tx_dam_mm_ref.tablenames="tt_news" AND tx_dam_mm_ref.ident="'.$field.'" ' .
			'AND tx_dam_mm_ref.uid_foreign="' . $newsUid . '"', '', 'tx_dam_mm_ref.sorting_foreign ASC');

		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$files[$row['uid']] = $row['file_path'].$row['file_name'];
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		return $files;
	}

	/**
	 * Parse row for custom plugin info
	 *
	 * @param $row current row
	 * @return array
	 */
	protected function getMultimediaItems($row) {

		$media = array();

		/**
		 * Ext:jg_youtubeinnews
		 */
		if (!empty($row['tx_jgyoutubeinnews_embed'])) {
			if (preg_match_all('#((http|https)://)?([a-zA-Z0-9\-]*\.)+youtube([a-zA-Z0-9\-]*\.)+[a-zA-Z0-9]{2,4}(/[a-zA-Z0-9=.?&_-]*)*#i', $row['tx_jgyoutubeinnews_embed'], $matches)) {
				$matches = array_unique($matches[0]);
				foreach ($matches as $url) {
					$urlInfo = parse_url($url);
					$media[] = array(
						'type' => \Tx_News_Domain_Model_Media::MEDIA_TYPE_MULTIMEDIA,
						'multimedia' => $url,
						'title' => $urlInfo['host'],
					);
				}
			}
		}

		return $media;
	}

	private $options = [];
	public function setOptions(array $options = [])
	{
		$this->options = $options;
	}

}
