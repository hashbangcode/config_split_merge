<?php

// src/Command/CreateUserCommand.php
namespace ConfigSplitMerge\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigSplitMerge extends Command
{
    protected static $defaultName = 'drupal:config_split_merge';

    protected function configure()
    {
      $this
        ->setDescription('Merges configurations.')
        ->setHelp('This command will take multiple configurations and merge them together.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $output->writeln('Done');
    }
}
