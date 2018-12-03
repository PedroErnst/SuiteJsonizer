<?php

namespace Jsonizer;

use Jsonizer\Command\BuildJsonCommand;
use Jsonizer\Command\BulkJsonCommand;
use Jsonizer\Command\DumpJsonCommand;
use Jsonizer\Command\NewJsonCommand;
use Symfony\Component\Console\Application;

/**
 * Class Jsonizer
 * @package Jsonizer
 */
class Jsonizer extends Application
{

    /**
     * Jsonizer constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->add(new NewJsonCommand());
        $this->add(new BulkJsonCommand());
        $this->add(new DumpJsonCommand());
        $this->add(new BuildJsonCommand());
    }
}