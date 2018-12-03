<?php

namespace Jsonizer\JsonModel;

use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JsonField extends JsonModel
{
    /**
     * JsonField constructor.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param HelperInterface $helper
     */
    public function __construct(InputInterface $input, OutputInterface $output, HelperInterface $helper)
    {
        $this->output = $output;
        $this->input = $input;
        $this->helper = $helper;

        $this->model = [
            'module' => '',
            'name' => '',
            'label' => '',
            'source' => '',
            'required' => false,
            'type' => 'varchar',
            'length' => 255,
            'default' => null,
            'options' => null,
            'parentenum' => null,
            'precision' => null,
            'rows' => null,
            'cols' => null,
        ];

        $this->required = [
            'module',
            'name',
        ];

        $this->options = [
            'module' => $this->getAvailableModules(),
            'required' => ['true', 'false'],
            'type' => ['varchar', 'int', 'link', 'text', 'bool', 'datetime', 'date', 'relate', 'id'
                , 'enum', 'dynamicenum', 'multienum', 'datetimecombo', 'float', 'currency'],
        ];

        $this->path = __DIR__ . '/../../raw/fields/';
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
     * @param $label
     * @return string
     */
    protected function processLabel($name)
    {
        return ucwords(str_replace('_', ' ', $name));
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
        $str = str_replace('AmountUnit', 'Amount', $str);
        return $str;
    }

    /**
     *
     */
    protected function load()
    {
        $loadPath = $this->path;
        $extraMessage = "";
        if (!file_exists($loadPath)) {
            $loadPath = $this->checkForIdenticalFieldInOtherModules();
            if (!$loadPath) {
                return;
            }
            $extraMessage = "<info> in another module, we're assuming the specs are the same</info>";
        }

        $this->model = json_decode(file_get_contents($loadPath), true);
        $this->output->writeln('<comment>An existing model with this name was found</comment>' . $extraMessage);

    }

    protected function checkForIdenticalFieldInOtherModules()
    {
        $thisName = pathinfo($this->path, PATHINFO_FILENAME);
        $dirPath = __DIR__ . '/../../raw/fields';
        $dir = opendir($dirPath);
        while ($fieldDir = readdir($dir)) {
            if (in_array($dir, ['.', '..'])) {
                continue;
            }
            $fieldNames = $this->getFolderJsonFiles($dirPath . '/' . $fieldDir);
            if (in_array($thisName, $fieldNames)) {
                return $dirPath . '/' . $fieldDir . '/' . $thisName . '.json';
            }
        }
        return false;
    }

    /**
     * @return array
     */
    protected function getExistingFieldNames()
    {
        $names = [];
        $dirPath = __DIR__ . '/../../raw/fields';
        $dir = opendir($dirPath);
        while ($fieldDir = readdir($dir)) {
            if (in_array($dir, ['.', '..'])) {
                continue;
            }
            $fieldNames = $this->getFolderJsonFiles($dirPath . '/' . $fieldDir);
            $names = array_merge($names, $fieldNames);
        }
        $names = array_unique($names);
        sort($names);
        return $names;
    }

    /**
     * @return string
     */
    public function getLastModule()
    {
        return $this->model['module'];
    }
}