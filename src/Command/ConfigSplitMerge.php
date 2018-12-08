<?php

// src/Command/CreateUserCommand.php
namespace ConfigSplitMerge\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ConfigSplitMerge extends Command
{
    protected static $defaultName = 'drupal:config_split_merge';

    protected $parentConfig;
    protected $siblingConfig;

    public function __construct($parentConfig = '', $siblingConfig = '')
    {
        parent::__construct();

        $this->parentConfig = $parentConfig;
        $this->siblingConfig = $siblingConfig;
    }

    protected function configure()
    {
      $this
        ->setDescription('Merges configurations.')
        ->setHelp('This command will take multiple configurations and merge them together.')
        ->addArgument('parent', InputArgument::REQUIRED, 'Parent Config')
        ->addArgument('sibling', InputArgument::REQUIRED, 'Sibling Config')
        ->addOption('config', null, InputOption::VALUE_REQUIRED,
        'Set the config directory root.',
        'config'
    );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $output->writeln('Done');
    }
}
