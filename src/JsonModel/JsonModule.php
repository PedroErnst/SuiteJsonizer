<?php

namespace Jsonizer\JsonModel;

use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JsonModule extends JsonModel
{
    /**
     * JsonModule constructor.
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
            'name' => '',
            'label' => '',
            'importable' => true,
            'navVisible' => true,
            'type' => 'basic',
        ];

        $this->required = [
            'name',
        ];

        $this->options = [
            'importable' => ['true', 'false'],
            'navVisible' => ['true', 'false'],
        ];

        $this->path = __DIR__ . '/../../raw/modules/';
    }
}