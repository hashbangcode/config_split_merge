<?php

namespace ConfigSplitMergeTests\Command;

use ConfigSplitMerge\Command\ConfigSplitMerge;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;

class ConfigSplitMergeTest extends TestCase
{
    public function testExecute()
    {
        $application = new Application();
        $application->add(new ConfigSplitMerge());


        $command = $application->find('drupal:config_split_merge');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
          'parent' => 'one',
          'sibling' => 'two',
          '--config' => 'three',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertContains('Done', $output);


    }
}
