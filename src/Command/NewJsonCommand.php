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

class NewJsonCommand extends Command
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
     * @var string
     */
    protected $lastType = '';

    /**
     * @var string
     */
    protected $lastModule = '';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Creates a new json file or updates an existing one.');
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
        $this->helper = $this->getHelper('question');

        do {
            $this->processJsonModel();

            $question = new ConfirmationQuestion('Process another model? <info>y</info>/n', true);
        } while ($this->helper->ask($input, $output, $question));

        return 1;
    }

    /**
     *
     */
    protected function processJsonModel()
    {
        $model = $this->setUpNewModel();
        $model->init($this->lastModule);
        $model->updateModel();
        $model->save();
        $this->lastModule = $model->getLastModule();

        $this->output->writeln('Created a new ' . get_class($model));
    }

    /**
     * @return JsonModel
     */
    protected function setUpNewModel()
    {
        $text = "Which type of model? module (m) / field (f) / relationship (r)";
        $question = new Question($text, $this->lastType);

        while (1) {
            $this->output->writeln('');
            $this->lastType = $this->helper->ask($this->input, $this->output, $question);

            if ($this->lastType === 'm') {
                return new JsonModule($this->input, $this->output, $this->helper);
            }
            if ($this->lastType === 'f') {
                return new JsonField($this->input, $this->output, $this->helper);
            }
            if ($this->lastType === 'r') {
                return new JsonRelationship($this->input, $this->output, $this->helper);
            }
            $this->lastType = '';
        }
    }
}