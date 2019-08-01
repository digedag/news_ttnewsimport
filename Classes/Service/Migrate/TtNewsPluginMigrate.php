<?php
namespace BeechIt\NewsTtnewsimport\Service\Migrate;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TtNewsPluginMigrate
{

    protected $output;
    protected $stats = [];

    /** @var \TYPO3\CMS\Core\Log\Logger */
    protected $logger;

    /** @var array */
    protected $categories = [];

    /** @var \TYPO3\CMS\Core\Service\FlexFormService */
    protected $flexFormService;

    protected $fieldToCopy = 'header,CType,header_position,header_link,header_layout,bodytext,layout,starttime,endtime,pages,colPos,subheader,spaceBefore,spaceAfter,fe_group,sectionIndex,linkToTop,date,recursive,sys_language_uid';

    protected $newsFlexConfig = array();

    /**
     *
     * @param \TYPO3\CMS\Extbase\Mvc\Cli\ConsoleOutput $output
     */
    public function __construct(\TYPO3\CMS\Extbase\Mvc\Cli\ConsoleOutput $output)
    {
        $this->output = $output;
        $this->logger = GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);
        $this->flexFormService = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Service\\FlexFormService');
        $url = ExtensionManagementUtility::extPath('news_ttnewsimport') . 'Resources/Private/Template/flexform.txt';
        $this->newsFlexConfig = json_decode(GeneralUtility::getUrl($url), true);
        $categories = $this->getDatabaseConnection()->doSelect(
            'uid,import_id,title',
            'sys_category',
            [
                'where' => 'import_source="TT_NEWS_CATEGORY_IMPORT"',
            ]
        );
        foreach ($categories as $catRow) {
            $this->categories[$catRow['import_id']] = $catRow;
        }
    }

    public function run()
    {
        $rows = $this->getTtnewsPluginRows();

        $cnt = 0;
        foreach ($rows as $pluginRow) {
            $cnt += 1;
            if ($pluginRow['news_ttnewsimport_new_id'] == 0) {
                $newId = $this->createPluginBelowExisting($pluginRow);
                if ($newId === 0) {
                    \tx_rnbase_util_Debug::debug($pluginRow, __FILE__.':'.__LINE__); // TODO: remove me
                    $this->output->outputLine(
                        sprintf('An empty content element could not be created for plugin with uid %d on pid %d', $pluginRow['uid'], $pluginRow['pid']));
                    throw new \RuntimeException('An empty content element could not be created');
                }

                $pluginRow['news_ttnewsimport_new_id'] = $newId;
                $this->getDatabaseConnection()->doUpdate('tt_content', 'uid=' . $pluginRow['uid'],
                    ['news_ttnewsimport_new_id' => $newId]);
            }

            $this->migrate($pluginRow);

            $this->output->output('.');
            if ($cnt % 50 == 0) {
                $this->output->output("\n");
            }
        }

        $this->output->outputLine("\nConverted plugins views");
        $this->output->outputLine('%\'-+15s', ['-']);
        foreach ($this->stats as $view => $cnt) {
            $this->output->outputLine('% -10s: % -5s', [$view, $cnt]);
        }
    }

    /**
     * Replace existing tt_news plugins with a new variant
     */
    public function replace()
    {
        $rows = $this->getTtnewsPluginRows();

        foreach ($rows as $pluginRow) {
            $update = [
                'list_type' => 'news_pi1',
                'pi_flexform' => $this->createFlexForm($pluginRow['pi_flexform'])
            ];

            $this->getDatabaseConnection()->doUpdate(
                'tt_content',
                'uid=' . $pluginRow['uid'],
                $update
            );
        }
    }

    /**
     * @param array $row
     */
    protected function migrate(array $row)
    {
        $fieldToBeCopied = explode(',', $this->fieldToCopy);
        $update = array(
            'list_type' => 'news_pi1',
            'pi_flexform' => $this->createFlexForm($row['pi_flexform'])
        );
        foreach ($fieldToBeCopied as $fieldName) {
            $update[$fieldName] = $row[$fieldName];
        }

        // if the content element is a translation and got a parent, set the correct parent
        if ($row['sys_language_uid'] > 0 && $row['l18n_parent'] > 0) {
            $parentRow = $this->getDatabaseConnection()->doSelect(
                'uid',
                'tt_content',
                [
                    'where' => 'news_ttnewsimport_new_id=' . $row['l18n_parent'],
                    'enablefieldsoff' => 1,
                ]
            );
            $parentRow = reset($parentRow);
            if (is_array($parentRow)) {
                $update['l18n_parent'] = $parentRow['uid'];
            }
        }

        $this->getDatabaseConnection()->doUpdate('tt_content', 'uid=' . $row['news_ttnewsimport_new_id'],
            $update);

    }

    /**
     * Create a new flexform based on the old one
     *
     * @param string $flexform
     * @return string
     */
    protected function createFlexForm($flexform)
    {
        $data = $this->flexFormService->convertFlexFormContentToArray($flexform);

        $new = $this->newsFlexConfig;
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'what_to_display':
                    $this->addFieldToArray($new, $this->getValueMap($key, $value), 'switchableControllerActions');
                    if ($value === 'VERSION_PREVIEW') {
                        $this->addFieldToArray($new, '1', 'settings.previewHiddenRecords');
                    }
                    $this->stats[$value] = !isset($this->stats[$value]) ? 1 : $this->stats[$value] + 1;
                    break;
                case 'listOrderBy':
                    $this->addFieldToArray($new, $this->getValueMap($key, $value), 'settings.orderBy');
                    break;
                case 'ascDesc':
                    $this->addFieldToArray($new, $value, 'settings.orderDirection');
                    break;
                case 'categoryMode':
                    $this->addFieldToArray($new, $this->getValueMap($key, $value), 'settings.categoryConjunction');
                    break;
                case 'categorySelection':
                    $this->addFieldToArray($new, $this->getSysCategoryIdList($value), 'settings.categories');
                    break;
                case 'useSubCategories':
                    $this->addFieldToArray($new, (int)$value, 'settings.includeSubCategories');
                    break;
                case 'archive':
                    $this->addFieldToArray($new, $this->getValueMap($key, $value), 'settings.archiveRestriction');
                    break;
                case 'imageMaxWidth':
                    $this->addFieldToArray($new, $value, 'settings.media.maxWidth', 'template');
                    break;
                case 'imageMaxHeight':
                    $this->addFieldToArray($new, $value, 'settings.media.maxHeight', 'template');
                    break;
                case 'listLimit':
                    $this->addFieldToArray($new, $value, 'settings.limit', 'additional');
                    break;
                case 'noPageBrowser':
                    $this->addFieldToArray($new, $value, 'settings.hidePagination', 'additional');
                    break;
                case 'croppingLenght':
                    $this->addFieldToArray($new, $value, 'settings.cropMaxCharacters', 'template');
                    break;
                case 'PIDitemDisplay':
                    $this->addFieldToArray($new, $value, 'settings.detailPid', 'additional');
                    break;
                case 'backPid':
                    $this->addFieldToArray($new, $value, 'settings.backPid');
                    break;
                case 'pages':
                    $this->addFieldToArray($new, $value, 'settings.startingpoint');
                    break;
                case 'recursive':
                    $this->addFieldToArray($new, (int)$value, 'settings.recursive');
                    break;
            }
        }

        return $this->array2xml($new, true);
    }

    protected function getValueMap($key, $field)
    {
        $newValue = '';
        if (!isset($this->valueMap[$key])) {
            $this->logger->error(sprintf('No value map entry for "%s"!', $key));
        } elseif (!isset($this->valueMap[$key][$field])) {
            $this->logger->error(sprintf('No value map entry for "%s" and "%s"!', $key, $field));
        } else {
            $newValue = $this->valueMap[$key][$field];
        }
        return $newValue;
    }

    protected function addFieldToArray(&$array, $value, $field, $sheet = 'sDEF')
    {
        $array['data'][$sheet]['lDEF'][$field]['vDEF'] = $value;
    }

    /**
     * Get the id list of the migrated categories
     *
     * @param string $idList
     * @return string
     */
    protected function getSysCategoryIdList($idList)
    {
        if (empty($idList)) {
            return '';
        }
        $idList = implode(',', \Tx_Rnbase_Utility_Strings::intExplode(',', $idList));

        $newIdList = [];
        $categoryRows = $this->getDatabaseConnection()->doSelect(
            'uid,title,import_id',
            'sys_category',
            [
                'where' => 'deleted=0 AND import_source="TT_NEWS_CATEGORY_IMPORT" AND import_id IN('. $idList . ')',
                'enablefieldsoff' => 1,
            ]
        );
        foreach ($categoryRows as $row) {
            $newIdList[] = $row['uid'];
        }

        return implode(',', $newIdList);
    }

    /** @var array */
    protected $valueMap = array(
        'what_to_display' => array(
            'LIST' => 'News->list',
            'LIST2' => 'News->list',
            'LIST23' => 'News->list',
            'HEADER_LIST' => 'News->list',
            'LATEST' => 'News->list',
            'SINGLE' => 'News->detail',
            'SINGLE2' => 'News->detail',
            'VERSION_PREVIEW' => 'News->detail',
            'AMENU' => 'News->dateMenu',
            'SEARCH' => 'News->searchForm',
            'CATMENU' => 'Category->list'
        ),
        'categoryMode' => array(
            '0' => '0',
            '1' => 'or',
            '2' => 'and',
            '-1' => 'notand',
            '-2' => 'notor'
        ),
        'listOrderBy' => array(
            'datetime' => 'datetime',
            'title' => 'title',
            'archivedate' => 'datetime',
            'author' => 'datetime',
            'type' => 'datetime',
            'random' => 'datetime'
        ),
        'archive' => array(
            '0' => '',
            '1' => 'archived',
            '-1' => 'active'
        )
    );

    protected function array2xml($input, $addPrologue = false)
    {
        $options = array(
            'parentTagMap' => array(
                'data' => 'sheet',
                'sheet' => 'language',
                'language' => 'field',
                'el' => 'field',
                'field' => 'value',
                'field:el' => 'el',
                'el:_IS_NUM' => 'section',
                'section' => 'itemType'
            ),
            'disableTypeAttrib' => 2
        );
        $spaceInd = 4;
        $output = GeneralUtility::array2xml($input, '', 0, 'T3FlexForms', $spaceInd, $options);
        if ($addPrologue) {
            $output = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>' . LF . $output;
        }

        return $output;
    }

    /**
     * Create an empty record below the existing one
     *
     * @param array $row old plugin row
     * @return int uid of the new record
     */
    protected function createPluginBelowExisting(array $row)
    {
        $data = array();
        $data['tt_content']['NEW'] = array(
            'hidden' => 1,
            'pid' => $row['uid'] * -1
        );

        /** @var \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\DataHandling\\DataHandler');
        $dataHandler->start($data, array());
        $dataHandler->admin = 1;
        $dataHandler->process_datamap();
        if (!empty($dataHandler->errorLog)) {
            $this->logger->error('Error(s) while creating the empty content element');
            foreach ($dataHandler->errorLog as $log) {
                $this->logger->error($log);
            }
        }
        return (int)$dataHandler->substNEWwithIDs['NEW'];
    }


    /**
     * @return array
     */
    protected function getTtnewsPluginRows()
    {
        $rows = $this->getDatabaseConnection()->doSelect(
            'tt_content.*',
            ['table'=>'tt_content', 'clause'=>'tt_content JOIN pages ON pages.uid = tt_content.pid'],
            [
                'where' => 'tt_content.deleted=0 AND list_type="9" AND CType="list" AND pages.deleted=0',
                'enablefieldsoff' => 1,
                'orderby' => 'sys_language_uid ASC',
            ]
        );
        return $rows;
    }


    /**
     * @return \Tx_Rnbase_Database_Connection
     */
    protected function getDatabaseConnection()
    {
        return \Tx_Rnbase_Database_Connection::getInstance();
    }
}
