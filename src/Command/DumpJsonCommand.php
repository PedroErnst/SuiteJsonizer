<?php

namespace Jsonizer\Command;

use Jsonizer\JsonModel\JsonModel;
use Jsonizer\JsonModel\JsonStructure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Jsonizer\JsonModel\JsonField;
use Jsonizer\JsonModel\JsonModule;
use Jsonizer\JsonModel\JsonRelationship;

class DumpJsonCommand extends Command
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
     * @var JsonStructure
     */
    protected $structure;

    protected $file;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('dump')
            ->setDescription('Dumps a human readable summary of the json structure into the dump folder.');
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
        $this->structure = new JsonStructure();

        $this->structure->load();
        try {
            $this->dumpStructure();
        }
        catch (\Exception $e) {
            $this->output->writeln($e->getMessage());
        }

        return 1;
    }

    protected function dumpStructure()
    {
        $path = __DIR__ . '/../../dump/summary.md';
        $this->file = fopen($path, 'w');
        if (!$this->file) {
            throw new \Exception('Unable to open summary file for writing: ' . $path);
        }

        foreach ($this->structure->modules as $module) {
            $this->writeLine('### ' . $module->name);
            $this->writeLine('');
            $this->writeLine('###### Fields');
            foreach ($module->fields as $field) {
                $this->writeLine('- ' . $field->name . ' (' . $field->type . ')');
            }
            $this->writeLine('');
            $this->writeLine('###### Relationships');
            foreach ($module->relationships as $relationship) {
                $directionIndicator = '<--';
                $name = $relationship->leftAlias;
                $relatedModule = $relationship->leftModule;
                $type = $relationship->type;
                if ($type === 'one-to-many' && $relationship->leftModule !== $module->name) {
                    $type = 'many-to-one';
                }
                if ($type === 'many-to-one' && $relationship->leftModule !== $module->name) {
                    $type = 'one-to-many';
                }
                if ($relationship->leftModule === $module->name) {
                    $name = $relationship->rightAlias;
                    $directionIndicator = '-->';
                    $relatedModule = $relationship->rightModule;
                }
                $this->writeLine("- $directionIndicator $name"
                                 . " ($relatedModule, $type)");
            }
            $this->writeLine('');
        }

        if (!fclose($this->file)) {
            throw new \Exception('Unable to close summary file: ' . $path);
        }
    }

    protected function writeLine($value)
    {
        fwrite($this->file, $value);
        fwrite($this->file, PHP_EOL);
    }
}