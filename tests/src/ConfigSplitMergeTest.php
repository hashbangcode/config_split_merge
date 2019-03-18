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
   * Test that the child extraction function works correctly.
   *
   * @dataProvider extractChildrenDataProvider
   */
  public function testExtractChildren($childString, $childArray) {
    $configSplitMerge = new ConfigSplitMerge();
    $childExtractResult = $configSplitMerge->extractChildren($childString);
    $this->assertIsArray($childExtractResult);
    $this->assertEquals(count($childArray), count($childExtractResult));
    $this->assertEquals($childArray, $childExtractResult);
  }

  /**
   * Data provider for testExtractChildren().
   *
   * @see testExtractChildren
   *
   * @return array
   *   The array of data.
   */
  public function extractChildrenDataProvider() {
    return [
      ['child', ['child']],
      ['child1,child2', ['child1', 'child2']],
      ['child2,child1', ['child1', 'child2']],
      ['child1, child2', ['child1', 'child2']],
      ['child1 , child2', ['child1', 'child2']],
      ['child1,child2,child3', ['child1', 'child2', 'child3']],
      ['child1,,,,,,,,,child2', ['child1', 'child2']],
    ];
  }

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
        'children' => 'bar',
        '--config' => __DIR__ . '/data/monkey',
      ]);

      $output = $commandTester->getDisplay();
      $statusCode = $commandTester->getStatusCode();
      $this->assertRegExp('/Parent directory .*data\/monkey\/foo does not exist./', $output);
      $this->assertEquals(1, $statusCode);
  }

  /**
   * Test that performing a dry run doesn't change any files in config.
   */
  public function testPerformingDryRunDoesNothing()
  {
    $this->setupConfigTest('configtest');

    $application = new Application();
    $application->add(new ConfigSplitMerge());

    $command = $application->find('drupal:config_split_merge');
    $commandTester = new CommandTester($command);
    $commandTester->execute([
      'parent' => 'parent',
      'children' => 'child1',
      '--config' => __DIR__ . '/data/config',
      '--dry-run' => TRUE,
    ]);

    $output = $commandTester->getDisplay();

    $this->assertContains('Done', $output);

    $statusCode = $commandTester->getStatusCode();
    $this->assertEquals(0, $statusCode);

    $this->assertFileExists(__DIR__ . '/data/config/default/config_split.config_split.parent.yml');
    $this->assertFileExists(__DIR__ . '/data/config/default/config_split.config_split.child1.yml');
    $this->assertFileExists(__DIR__ . '/data/config/default/config_split.config_split.child2.yml');

    $originalFile = __DIR__ . '/data/configtest/default/config_split.config_split.parent.yml';
    $this->assertFileEquals($originalFile, __DIR__ . '/data/config/default/config_split.config_split.parent.yml');

    // These two files should not exist in default.
    $this->assertFileNotExists(__DIR__ . '/data/config/default/node.type.landing_page.yml');
    $this->assertFileNotExists(__DIR__ . '/data/config/default/core.extension.yml');

    // All of the files should be in their original places.
    $this->assertFileExists(__DIR__ . '/data/config/parent/core.extension.yml');
    $this->assertFileExists(__DIR__ . '/data/config/parent/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/parent/node.type.page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/parent/system.site.yml');

    $this->assertFileExists(__DIR__ . '/data/config/child1/core.extension.yml');
    $this->assertFileExists(__DIR__ . '/data/config/child1/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/child1/system.site.yml');
    $this->assertFileExists(__DIR__ . '/data/config/child2/core.extension.yml');
    $this->assertFileExists(__DIR__ . '/data/config/child2/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/child2/system.site.yml');
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
      'children' => 'child1',
      '--config' => __DIR__ . '/data/config',
    ]);

    $output = $commandTester->getDisplay();

    $this->assertContains('Done', $output);

    $statusCode = $commandTester->getStatusCode();
    $this->assertEquals(0, $statusCode);

    $this->assertFileExists(__DIR__ . '/data/config/default/config_split.config_split.parent.yml');
    $this->assertFileExists(__DIR__ . '/data/config/default/config_split.config_split.child1.yml');
    $this->assertFileExists(__DIR__ . '/data/config/default/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/default/core.extension.yml');

    $this->assertFileNotExists(__DIR__ . '/data/config/parent/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/parent/node.type.page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/parent/system.site.yml');
    $this->assertFileNotExists(__DIR__ . '/data/config/child1/core.extension.yml');

    $this->assertFileNotExists(__DIR__ . '/data/config/child1/node.type.landing_page.yml');
    $this->assertFileNotExists(__DIR__ . '/data/config/child1/core.extension.yml');
    $this->assertFileExists(__DIR__ . '/data/config/child1/system.site.yml');

    $this->assertContains("'node.type.landing_page' => '2034e796-4d2c-43f0-9135-1afc93052380',", $output, '');

    $configSplitFile = __DIR__ . '/data/config/default/config_split.config_split.parent.yml';
    $configSplitFileContents = file_get_contents($configSplitFile);
    $configSplit = Yaml::decode($configSplitFileContents);
    $this->assertEquals($configSplit['blacklist'][0], 'node.type.page');

    $configSplitFile = __DIR__ . '/data/config/default/config_split.config_split.child1.yml';
    $configSplitFileContents = file_get_contents($configSplitFile);
    $configSplit = Yaml::decode($configSplitFileContents);
    $this->assertFalse(isset($configSplit['blacklist'][0]));
  }

  /**
   * Test that the config split merge performs the correct merge operation with
   * more than one child.
   */
  public function testConfigSplitMergePerformsMultipleChildMerge() {
    $this->setupConfigTest('configtest');

    $application = new Application();
    $application->add(new ConfigSplitMerge());

    $command = $application->find('drupal:config_split_merge');
    $commandTester = new CommandTester($command);
    $commandTester->execute([
      'parent' => 'parent',
      'children' => 'child1,child2',
      '--config' => __DIR__ . '/data/config',
    ]);

    $output = $commandTester->getDisplay();

    $this->assertContains('Done', $output);

    $statusCode = $commandTester->getStatusCode();
    $this->assertEquals(0, $statusCode);

    $this->assertFileExists(__DIR__ . '/data/config/default/config_split.config_split.parent.yml');
    $this->assertFileExists(__DIR__ . '/data/config/default/config_split.config_split.child1.yml');
    $this->assertFileExists(__DIR__ . '/data/config/default/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/default/core.extension.yml');

    $this->assertFileNotExists(__DIR__ . '/data/config/parent/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/parent/node.type.page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/parent/system.site.yml');
    $this->assertFileNotExists(__DIR__ . '/data/config/parent/core.extension.yml');

    $this->assertFileNotExists(__DIR__ . '/data/config/child1/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/child1/system.site.yml');
    $this->assertFileNotExists(__DIR__ . '/data/config/child1/core.extension.yml');

    $this->assertFileNotExists(__DIR__ . '/data/config/child2/node.type.landing_page.yml');
    $this->assertFileExists(__DIR__ . '/data/config/child2/system.site.yml');
    $this->assertFileNotExists(__DIR__ . '/data/config/child2/core.extension.yml');

    $this->assertContains("'node.type.landing_page' => '2034e796-4d2c-43f0-9135-1afc93052380',", $output, '');

    $configSplitFile = __DIR__ . '/data/config/default/config_split.config_split.parent.yml';
    $configSplitFileContents = file_get_contents($configSplitFile);
    $configSplit = Yaml::decode($configSplitFileContents);
    $this->assertEquals($configSplit['blacklist'][0], 'node.type.article');
    $this->assertEquals($configSplit['blacklist'][1], 'node.type.page');

    $configSplitFile = __DIR__ . '/data/config/default/config_split.config_split.child1.yml';
    $configSplitFileContents = file_get_contents($configSplitFile);
    $configSplit = Yaml::decode($configSplitFileContents);
    $this->assertFalse(isset($configSplit['blacklist'][0]));
    $this->assertTrue(isset($configSplit['module']['my_custom_payment_module']));
    $this->assertEquals($configSplit['module']['my_custom_payment_module'], 0);
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

  /**
   * PHPUnit teardown funciton.
   */
  public function tearDown() {
    if (file_exists(__DIR__ . '/data/config')) {
      // Remove the config testing directory after a test.
      $this->recurseDeleteDirectory(__DIR__ . '/data/config');
    }
  }

}
