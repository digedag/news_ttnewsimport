<?php
namespace BeechIt\NewsTtnewsimport\Command;

/*
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

use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * A Command Controller which provides help for available commands
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class TtNewsPluginMigrateCommandController extends CommandController
{

    const WHERE_CLAUSE = 'tt_content.deleted=0 AND list_type="9" AND CType="list"';

    /**
     * Check if any plugin needs to be migrated
     */
    public function checkCommand()
    {
        $this->outputDashedLine();
        $this->outputLine('List of plugins:');
        $this->outputLine('%-2s% -5s %s',
            [' ', $this->getNewsPluginCount('tt_content.hidden=0 AND news_ttnewsimport_new_id != ""'), 'already migrated']);
        $this->outputLine('%-2s% -5s %s', [
            ' ',
            $this->getNewsPluginCount('tt_content.hidden=1 AND news_ttnewsimport_new_id != ""'),
            'already migrated but hidden'
        ]);
        $this->outputLine('%-2s% -5s %s',
            [' ', $this->getNewsPluginCount('tt_content.hidden=0 AND news_ttnewsimport_new_id = ""'), 'not yet migrated']);
        $this->outputLine('%-2s% -5s %s', [
            ' ',
            $this->getNewsPluginCount('tt_content.hidden=1 AND news_ttnewsimport_new_id = ""'),
            'not yet migrated and hidden'
        ]);
        $this->outputDashedLine();
        $this->outputLine();
    }

    /**
     * Create news plugins below each tt_news plugin
     */
    public function runCommand()
    {
        /** @var \BeechIt\NewsTtnewsimport\Service\Migrate\TtNewsPluginMigrate $migrate */
        $migrate = $this->objectManager->get(
            'BeechIt\\NewsTtnewsimport\\Service\\Migrate\\TtNewsPluginMigrate', $this->output);
        $migrate->run();
    }

    /**
     * REPLACE tt_news plugins
     */
    public function replaceCommand()
    {
        /** @var \BeechIt\NewsTtnewsimport\Service\Migrate\TtNewsPluginMigrate $migrate */
        $migrate = $this->objectManager->get(
            'BeechIt\\NewsTtnewsimport\\Service\\Migrate\\TtNewsPluginMigrate', $this->output);
        $migrate->replace();
    }

    /**
     * Remove tt_news plugins
     *
     * @param bool $delete Set to TRUE to delete the plugins instead of hiding
     */
    public function removeOldPluginsCommand($delete = false)
    {
        $db = \Tx_Rnbase_Database_Connection::getInstance();
        $update = [($delete ? 'deleted' : 'hidden') => 2];
        $res = $db->doUpdate('tt_content', self::WHERE_CLAUSE, $update);
        $this->output->output("\nFinished. Affected rows: %d\n", [$res]);
        $this->outputDashedLine();
        $this->outputLine();
    }

    public function resetOldPluginsCommand()
    {
        $db = \Tx_Rnbase_Database_Connection::getInstance();
        $fields = ['deleted', 'hidden'];
        foreach ($fields as $field) {
            $update = [$field => 0];
            $res = $db->doUpdate('tt_content', $field.'=2 AND list_type="9" AND CType="list"', $update);
            //            $res = $this->getDatabaseConnection()->exec_UPDATEquery('tt_content', $field.'=2 AND list_type="9" AND CType="list"', $update);
            $this->output->output("\n%s affected rows: %d", [$field, $res]);
        }
        $this->output->output("\nFinished.\n");
    }

    /**
     * Remove tt_news records and categories
     *
     */
    public function removeOldNewsCommand()
    {
        $db = \Tx_Rnbase_Database_Connection::getInstance();
        $update = ['deleted' => 2];
        $res = $db->doUpdate('tt_news', 'deleted=0', $update);
        $this->output->output("\nAffected rows in tt_news: %d", [$res]);
        $res = $db->doUpdate('tt_news_cat', 'deleted=0', $update);
        $this->output->output("\nAffected rows in tt_news_cat: %d", [$res]);
        $this->output->output("\nFinished.\n");
    }

    public function resetOldNewsCommand()
    {
        $db = \Tx_Rnbase_Database_Connection::getInstance();
        $update = ['deleted' => 0];
        $res = $db->doUpdate('tt_news', 'deleted=2', $update);
        $this->output->output("Affected rows in tt_news: %d", [$res]);
        $res = $db->doUpdate('tt_news_cat', 'deleted=2', $update);
        $this->output->output("\nAffected rows in tt_news_cat: %d\n", [$res]);

        $this->output->output("\nFinished.\n");
    }

    /**
     * Get count of tt_news plugins
     *
     * @param string $additionalWhere
     * @return int
     */
    protected function getNewsPluginCount($additionalWhere)
    {
        $rows = $this->getDatabaseConnection()->doSelect(
            'count(tt_content.uid) as cnt',
            ['table'=>'tt_content', 'clause'=>'tt_content JOIN pages ON pages.uid = tt_content.pid'],
            [
                'where' => self::WHERE_CLAUSE . ' AND pages.deleted = 0 AND ' . $additionalWhere,
                'enablefieldsoff' => 1,
            ]
        );
        return (int) $rows[0]['cnt'];
    }

    /**
     * @param string $char
     */
    protected function outputDashedLine($char = '-')
    {
        $this->outputLine(str_repeat($char, 79));
    }

    /**
     * @return \Tx_Rnbase_Database_Connection
     */
    protected function getDatabaseConnection()
    {
        return \Tx_Rnbase_Database_Connection::getInstance();
    }
}
