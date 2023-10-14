<?php
namespace BeechIt\NewsTtnewsimport\Command;

/**
 * This file is part of the "news" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
use GeorgRinger\News\Jobs\ImportJobInterface;
use GeorgRinger\News\Utility\ImportJob;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * Controller to import news records
 *
 */
class NewsImportCommandController extends CommandController
{

    /**
     * Import for EXT:news
     *
     * @cli
     * @param int $job
     * @param int $limit
     * @param string $uids
     * @param string $skipMigrated
     */
    public function migrateCommand(int $job = -1, int $limit = 0, string $uids='', string $skipMigrated = 'true')
    {
        $skipMigrated = filter_var($skipMigrated, FILTER_VALIDATE_BOOLEAN);
        $this->outputDashedLine();
        $jobs = $this->getAvailableJobs();
        $jobLabels = array_values($jobs);
        $index = $job;
        if ($index < 0) {
            $index = $this->output->select('Which class to import from??', $jobLabels, null, false, 10);
        } else {
            $index = $jobLabels[$index] ?? '';
        }

        $class = $this->getChosenClass($jobs, $index);
        /* @var $job ImportJobInterface */
        $job = $this->objectManager->get($class);

        $dp = '-';
        if (method_exists($job, 'getDataProviderService')) {
            $srv = $job->getDataProviderService();
            $dp = get_class($srv);
            if (method_exists($srv, 'setOptions')) {
                $srv->setOptions(['limit' => $limit, 'uids' => $uids, 'skipMigrated' => $skipMigrated]);
            }
        }
        $info = $job->getInfo();
        $this->outputLine(sprintf('Use job %s with data provider %s', get_class($job), $dp));
        $this->outputLine('%-20s% -5s ', ['base query:', $info['query'],]);
        $this->outputLine('%-20s% -5s ', ['totalRecordCount:', $info['totalRecordCount']]);
        $this->outputLine('%-20s% -5s ', ['runsToComplete:', $info['runsToComplete'],]);

        $totalRecords = $info['totalRecordCount'];
        $recordsPerRun = $info['increaseOffsetPerRunBy'];

        $offset = 0;
        $runs = 0;
        while ($offset < $totalRecords) {
            $runs += 1;
            $this->output->output('.');
            if ($runs % 50 == 0) {
                $this->output->output("\n");
            }
            $job->run($offset);
            $offset += $recordsPerRun;
        }
        $this->output->output("\n");

        $this->outputDashedLine();
        $this->outputLine();
    }
    /**
     * Get available classes
     *
     * @param array $jobs
     * @param string $index
     * @return string
     */
    protected function getChosenClass(array $jobs, $index)
    {
        $classToBeUsed = null;
        foreach ($jobs as $class => $title) {
            if ($index === $title) {
                $classToBeUsed = $class;
                continue;
            }
        }

        if (is_null($classToBeUsed)) {
            $this->output('<error>Sorry, the class could not be found!</error>');
            $this->sendAndExit();
        }

        return $classToBeUsed;
    }

    /**
     * Retrieve all available import jobs by traversing trough registered
     * import jobs and checking "isEnabled".
     *
     * @return array
     */
    protected function getAvailableJobs()
    {
        $availableJobs = [];
        $registeredJobs = ImportJob::getRegisteredJobs();
        foreach ($registeredJobs as $registeredJob) {
            $jobInstance = $this->objectManager->get($registeredJob['className']);
            if ($jobInstance instanceof ImportJobInterface && $jobInstance->isEnabled()) {
                $availableJobs[$registeredJob['className']] = $GLOBALS['LANG']->sL($registeredJob['title']);
            }
        }

        if (empty($availableJobs)) {
            $this->outputLine('<error>No import jobs available!</error>');
            $this->sendAndExit();
        }

        return $availableJobs;
    }
    /**
     * @param string $char
     */
    protected function outputDashedLine($char = '-')
    {
        $this->outputLine(str_repeat($char, 79));
    }
}
