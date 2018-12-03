<?php

namespace Jsonizer\Command;

use Jsonizer\JsonModel\JsonModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Jsonizer\JsonModel\JsonField;
use Jsonizer\JsonModel\JsonModule;
use Jsonizer\JsonModel\JsonRelationship;

class BulkJsonCommand extends Command
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var
     */
    protected $helper;

    /**
     * @var
     */
    protected $totalFiles = 0;

    /**
     * @var
     */
    protected $affectedFiles = 0;

    /**
     * @param $path
     * @param $jsonObject
     * @return bool|\stdClass
     *
     *  This function will be called on ALL json files.
     *  Make absolutely sure you only affect the files you intend to modify.
     *  Return false or the modified json object.
     */
    protected function processBulk($path, $jsonObject)
    {
        if (!isset($jsonObject->type)) {
            return false;
        }
        if ($jsonObject->type !== 'string') {
            return false;
        }
        $jsonObject->type = 'varchar';
        return $jsonObject;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('bulk')
            ->setDescription('Bulk updates ALL json files with the bulkProcess() function. ' .
                             'Backup your files before use and consider doing a --dry-run first!')
            ->addArgument(
                'dry-run',
                InputArgument::OPTIONAL,
                'Only shows the number of files that would be affected.'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->input = $input;

        $this->runBulkAction();
        $this->output->writeln("<info>Total files: {$this->totalFiles}</info>");
        $this->output->writeln("<info>Files affected: {$this->affectedFiles}</info>");

        if ($this->input->getArgument('dry-run')) {
            $this->output->writeln('Dry run - no files saved.');
        }

        return 1;
    }

    /**
     *
     */
    protected function runBulkAction()
    {
        $rawFolder = __DIR__ . '/../../raw';
        $this->iterateDirRecursive($rawFolder);
    }

    /**
     * @param $path
     */
    protected function iterateDirRecursive($path)
    {
        $dir = opendir($path);
        while ($entry = readdir($dir)) {
            if (in_array($entry, ['.', '..'])) {
                continue;
            }
            $entryPath = $path . '/' . $entry;
            if (is_dir($entryPath)) {
                $this->iterateDirRecursive($entryPath);
                continue;
            }
            if (pathinfo($entry, PATHINFO_EXTENSION) === 'json') {
                $this->processJsonFile($entryPath);
            }
        }
    }

    /**
     * @param $path
     */
    protected function processJsonFile($path)
    {
        $original = json_decode(file_get_contents($path));
        if ($original) {
            $clone = clone $original;
            $modified = $this->processBulk($path, $clone);
            if ($modified && $modified != $original) {
                $this->affectedFiles++;
                $this->output->writeln($path);
                if (!$this->input->getArgument('dry-run')) {
                    file_put_contents($path, json_encode($modified, JSON_PRETTY_PRINT));
                }
            }
            $this->totalFiles++;
        }
    }
}