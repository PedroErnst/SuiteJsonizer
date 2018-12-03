<?php

namespace Jsonizer\JsonModel;

use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JsonRelationship extends JsonModel
{
    /**
     * JsonRelationship constructor.
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
            'type' => 'one-to-many',
            'identifier' => '',
            'number' => '1',
            'leftModule' => '',
            'leftLabel' => '',
            'leftAlias' => '',
            'rightModule' => '',
            'rightLabel' => '',
            'rightAlias' => '',
        ];

        $this->required = [
            'leftModule',
            'rightModule',
            'number',
        ];

        $this->options = [
            'type' => ['one-to-many', 'one-to-one', 'many-to-many', 'many-to-one'],
            'leftModule' => $this->getAvailableModules(),
            'rightModule' => $this->getAvailableModules(),
        ];

        $this->path = __DIR__ . '/../../raw/relationships/';
    }

    /**
     * @param $requiredData
     */
    protected function buildPath($requiredData)
    {
        $identifier = $requiredData['leftModule'] . $requiredData['rightModule'] . '_' . $requiredData['number'];
        $identifier = strtolower(str_replace(' ', '_', $this->processLabel($identifier)));
        $this->path = $this->path . $identifier . '.json';
        $this->model['identifier'] = $identifier;
        $this->model['leftLabel'] = $this->processLabel($requiredData['leftModule']);
        $this->model['rightLabel'] = $this->processLabel($requiredData['rightModule']);
        $this->output->writeln($identifier);
    }

    /**
     *
     */
    public function updateModel()
    {
        foreach ($this->model as $key => $val) {
            if (in_array($key, $this->required)) {
                continue;
            }
            $this->model[$key] = $this->askQuestion($key, $this->model[$key]);
            if (in_array($key, ['leftModule', 'rightModule', 'identifier'])) {
                $this->setLabel();
            }
        }
    }

    /**
     *
     */
    protected function setLabel()
    {
        $aliasPrefillValues = [''];
        if ($this->model['leftLabel'] === '' && $this->model['leftModule'] !== '') {
            $this->model['leftLabel'] = $this->processLabel($this->model['leftModule']);
        }
        if (in_array($this->model['leftAlias'], $aliasPrefillValues) && $this->model['leftModule'] !== '') {
            $this->model['leftAlias'] = lcfirst($this->model['leftModule']);
            if (substr($this->model['leftAlias'], -3) === 'Ref') {
                $this->model['leftAlias'] = substr($this->model['leftAlias'], 0, -3);
            }
        }

        if ($this->model['rightLabel'] === '' && $this->model['rightModule'] !== '') {
            $this->model['rightLabel'] = $this->processLabel($this->model['rightModule']);
        }
        if (in_array($this->model['rightAlias'], $aliasPrefillValues)  && $this->model['rightModule'] !== '') {
            $this->model['rightAlias'] = lcfirst($this->model['rightModule']);
            if (substr($this->model['rightAlias'], -3) === 'Ref') {
                $this->model['rightAlias'] = substr($this->model['rightAlias'], 0, -3);
            }
        }
    }

    /**
     *
     */
    public function save()
    {
        switch ($this->model['type']) {
            case "one-to-one":
                if (isset($this->model['leftSubpanel'])) {
                    unset($this->model['leftSubpanel']);
                }
                if (isset($this->model['rightSubpanel'])) {
                    unset($this->model['rightSubpanel']);
                }
                break;
            case "one-to-many":
                if (isset($this->model['leftSubpanel'])) {
                    unset($this->model['leftSubpanel']);
                }
                if (!isset($this->model['rightSubpanel'])) {
                    $this->model['rightSubpanel'] = 'default';
                }
                break;
            case "many-to-many":
                if (!isset($this->model['leftSubpanel'])) {
                    $this->model['leftSubpanel'] = 'default';
                }
                if (!isset($this->model['rightSubpanel'])) {
                    $this->model['rightSubpanel'] = 'default';
                }
                break;
            default:
                break;
        }
        parent::save();
    }

    /**
     * @return string
     */
    public function getLastModule()
    {
        return $this->model['leftModule'];
    }
}