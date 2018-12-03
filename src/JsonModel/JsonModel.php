<?php

namespace Jsonizer\JsonModel;

use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class JsonModel
{
    /**
     * @var array
     */
    protected $model = [];

    /**
     * @var array
     */
    protected $required = [];

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var string
     */
    protected $path = '';

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
     * @param $var
     * @return mixed
     */
    public function get($var)
    {
        return isset($this->model[$var]) ? $this->model[$var] : '';
    }

    /**
     *
     */
    public function init($lastModule)
    {
        $requiredData = [];
        foreach ($this->required as $requiredKey) {
            if (in_array($requiredKey, ['module', 'leftModule']) && $lastModule) {
                $requiredVal = $this->askQuestion($requiredKey, $lastModule);
            } else {
                $requiredVal = $this->askQuestion($requiredKey, '', false);
            }
            if ($requiredKey === 'module') {
                $this->output->writeln('Current module fields: <info>' . implode(', ', $this->getAvailableFields($requiredVal)) . '</info>');
            }
            $requiredData[$requiredKey] = $requiredVal;
        }
        $this->buildPath($requiredData);
        $this->output->writeln($this->path);
        $this->load();

        foreach ($requiredData as $key => $val) {
            $this->model[$key] = $val;
        }
    }

    /**
     * @param $requiredData
     */
    protected function buildPath($requiredData)
    {
        foreach ($requiredData as $val) {
            $this->path .= $val . '/';
        }
        $this->path = rtrim($this->path, '/') . '.json';
    }

    /**
     *
     */
    protected function load()
    {
        if (!file_exists($this->path)) {
            return;
        }

        $this->model = json_decode(file_get_contents($this->path), true);
        $this->output->writeln('<comment>An existing model with this name was found</comment>');

    }

    /**
     *
     */
    public function save()
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir) && !is_dir($dir)) {
            $this->output->writeln('Created directory: ' . $dir);
        }
        file_put_contents($this->path, json_encode($this->model, JSON_PRETTY_PRINT));
    }

    /**
     *
     */
    public function updateModel()
    {
        foreach ($this->model as $key => $val) {

            if ($key === 'name') {
                $this->setLabel();
            }
            if (in_array($key, $this->required)) {
                continue;
            }
            $this->model[$key] = $this->askQuestion($key, $val);
        }
    }

    /**
     * @param $key
     * @param $val
     * @return mixed
     */
    public function askQuestion($key, $val, $allowEmpty = true)
    {
        $default = $val ?: $this->model[$key];
        do {
            $question = new Question("$key (<info>{$this->displayValue($default)}</info>): ", $default);
            if (isset($this->options[$key])) {
                $question->setAutocompleterValues($this->options[$key]);
                if (count($this->options[$key]) < 20) {
                    $this->output->writeln("Valid options are: " . implode(',', $this->options[$key]));
                }
            } elseif ($key === 'name' && isset($this->model['module'])) {
                $question->setAutocompleterValues($this->getExistingFieldNames());
            }
            $answer = $this->helper->ask($this->input, $this->output, $question);
            if ($this->model[$key] === '' && isset($this->options[$key]) && !in_array($answer, $this->options[$key])) {
                $this->output->writeln("<error>$answer is not a valid option!</error>");
                continue;
            }
            if (!$answer && !$allowEmpty) {
                $this->output->writeln("<error>You must provide a value!</error>");
                continue;
            }
            return $answer;
        } while (1);
    }

    /**
     * @return array
     */
    protected function getExistingFieldNames()
    {
        return [];
    }

    /**
     * @param $val
     * @return string
     */
    public function displayValue($val)
    {
        if ($val === false) {
            return "false";
        }
        if ($val === true) {
            return "true";
        }
        if ($val === null) {
            return "null";
        }
        return $val;
    }

    /**
     *
     */
    protected function setLabel()
    {
        $this->model['label'] = $this->processLabel($this->model['name']);
        if (isset($this->model['source'])) {
            $this->model['source'] = $this->processSource($this->model['name']);
        }
    }

    /**
     * @param $name
     * @return string
     */
    protected function processLabel($name)
    {
        $re = '/(?#! splitCamelCase Rev:20140412)
            # Split camelCase "words". Two global alternatives. Either g1of2:
              (?<=[a-z])      # Position is after a lowercase,
              (?=[A-Z])       # and before an uppercase letter.
            | (?<=[A-Z])      # Or g2of2; Position is after uppercase,
              (?=[A-Z][a-z])  # and before upper-then-lower case.
            /x';
        $a = preg_split($re, $name);
        return ucfirst(implode(' ', $a));
    }

    /**
     * @param $label
     * @return string
     */
    protected function processSource($name)
    {
        $str = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
        if (in_array($str, ['validForStart', 'validForEnd'])) {
            return 'validFor';
        }
        if (substr($name, 0, 3) === 'at_') {
            return '@' . lcfirst(substr($str, 2));
        }
        return $str;
    }

    /**
     * @return array
     */
    protected function getAvailableModules()
    {
        return $this->getFolderJsonFiles(__DIR__ . '/../../raw/modules');
    }

    /**
     * @return array
     */
    protected function getAvailableFields($module)
    {
        return $this->getFolderJsonFiles(__DIR__ . '/../../raw/fields/' . $module);
    }

    /**
     * @param $folder
     * @return array
     */
    public function getFolderJsonFiles($folder)
    {
        $modules = [];
        if (is_dir($folder)) {
            $dir = opendir($folder);
            while (false !== ($file = readdir($dir))) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                    $modules[] = pathinfo($file, PATHINFO_FILENAME);
                }
            }
        }
        sort($modules);
        return $modules;
    }

    public function getLastModule()
    {
        return '';
    }
}