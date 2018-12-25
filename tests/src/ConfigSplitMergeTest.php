<?php

namespace ConfigSplitMergeTests\Command;

use ConfigSplitMerge\Command\ConfigSplitMerge;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;
use Drupal\Core\Serialization\Yaml;

class ConfigSplitMergeTest extends TestCase
{

  /**
   * Test that directories that do not exist product an error code.
   */
  public function testDirectoriesThatDoNotExistProduceErrorCode()
  {
      $application = new Application();
      $application->add(new ConfigSplitMerge());

      $command = $application->find('drupal:config_split_merge');
      $commandTester = new CommandTester($command);
      $commandTester->execute([
        'parent' => 'foo',
        'sibling' => 'bar',
        '--config' => __DIR__ . '/data/monkey',
      ]);

      $output = $commandTester->getDisplay();
      $statusCode = $commandTester->getStatusCode();
      $this->assertRegExp('/Parent directory .*data\/monkey\/foo does not exist./', $output);
      $this->assertEquals(1, $statusCode);
  }

  /**
   * Test that the update hook output is correctly generated.
   */
  public function testUpdateHookOutput() {
    $configSplitMerge = new ConfigSplitMerge();

    $uuids = [
      'config.key' => '09876543',
    ];
    $configSplitMerge->setUpdateUuidList($uuids);

    $output = $configSplitMerge->generateUpdateHook();
    $this->assertContains("'config.key' => '09876543',", $output[7], '');
  }

  /**
   * Test that the config split merge performs the correct merge operation.
   */
  public function testConfigSplitMergePerformsAMerge() {
    $this->setupConfigTest('configtest');

    $application = new Application();
    $application->add(new ConfigSplitMerge());

    $command = $application->find('drupal:config_split_merge');
    $commandTester = new CommandTester($command);
    $commandTester->execute([
      'parent' => 'parent',
      'sibling' => 'sibling',
      '--config' => __DIR__ . '/data/config',
    ]);

    $output = $commandTester->getDisplay();

    $this->assertContains('Done', $output);

    $statusCode = $commandTester->getStatusCode();
    $this->assertEquals(0, $statusCode);

    $this->assertFileExists(__DIR__ . '/data/config/default/config_split.config_split.parent.yml');
    $this->assertFileExists(__DIR__ . '/data/config/default/config_split.config_split.sibling.yml');
    $this->assertFileExists(__DIR__ . '/data/config/default/node.type.landing_page.yml');

    $this->assertFileNotExists(__DIR__ . '/data/config/parent/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/parent/node.type.page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/parent/system.site.yml');

    $this->assertFileNotExists(__DIR__ . '/data/config/sibling/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/sibling/system.site.yml');

    $this->assertContains("'node.type.landing_page' => '2034e796-4d2c-43f0-9135-1afc93052380',", $output, '');

    $configSplitFile = __DIR__ . '/data/config/default/config_split.config_split.parent.yml';
    $configSplitFileContents = file_get_contents($configSplitFile);
    $configSplit = Yaml::decode($configSplitFileContents);
    $this->assertEquals($configSplit['blacklist'][0], 'node.type.page');

    $configSplitFile = __DIR__ . '/data/config/default/config_split.config_split.sibling.yml';
    $configSplitFileContents = file_get_contents($configSplitFile);
    $configSplit = Yaml::decode($configSplitFileContents);
    $this->assertFalse(isset($configSplit['blacklist'][0]));
  }

  /**
   * Set up the configuration test by copying one of the test configuration
   * directories.
   *
   * @param string $directory
   *   The test directory.
   */
  public function setupConfigTest($directory) {
    if (file_exists(__DIR__ . '/data/config')) {
      $this->recurseDeleteDirectory(__DIR__ . '/data/config');
    }

    $this->recurseCopyDirectory(__DIR__ . '/data/' . $directory, __DIR__ . '/data/config');
  }

  /**
   * Copy one directory to another.
   *
   * @param string $source
   *   The source directory.
   * @param string $destination
   *   The destination directory.
   */
  public function recurseCopyDirectory($source, $destination) {
    $dir = opendir($source);
    if (!file_exists($destination)) {
      mkdir($destination);
    }

    while(false !== ( $file = readdir($dir)) ) {
      if (($file != '.') && ($file != '..')) {
        if (is_dir($source . '/' . $file)) {
          $this->recurseCopyDirectory($source . '/' . $file,$destination . '/' . $file);
        }
        else {
          copy($source . '/' . $file,$destination . '/' . $file);
        }
      }
    }
    closedir($dir);
  }

  /**
   * Recurse delete a directory.
   *
   * @param string $directory
   *   The directory to delete.
   *
   * @return bool
   *   The output of rmdir().
   */
  public function recurseDeleteDirectory($directory) {
    $files = array_diff(scandir($directory), ['.','..']);
    foreach ($files as $file) {
      if (is_dir("$directory/$file")) {
        $this->recurseDeleteDirectory("$directory/$file");
      } else {
        unlink("$directory/$file");
      }
    }
    return rmdir($directory);
  }

}
