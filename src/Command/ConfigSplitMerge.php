<?php

// src/Command/CreateUserCommand.php
namespace ConfigSplitMerge\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Drupal\Core\Serialization\Yaml;

/**
 * Class ConfigSplitMerge.
 *
 * @package ConfigSplitMerge\Command
 */
class ConfigSplitMerge extends Command
{
  /**
   * The name of the command.
   *
   * @var string
   */
  protected static $defaultName = 'drupal:config_split_merge';

  /**
   * The configuration directory.
   *
   * @var string
   */
  protected $configDirectory;

  /**
   * If this is a dry run or not.
   *
   * @var bool
   */
  protected $dryRun;

  /**
   * The update hook file, or false if none supplied.
   *
   * @var string|bool
   */
  protected $updateHookFile;

  /**
   * The update uuid list.
   *
   * @var array
   */
  protected $updateUuidList;

  /**
   * The parent config directory.
   *
   * @var string
   */
  protected $parentConfig;

  /**
   * The children config directories.
   *
   * @var array
   */
  protected $childrenConfig;

  /**
   * The list of configuration files that need to be deleted.
   *
   * @var array
   */
  protected $filesToDelete;

  /**
   * The list of configuration files that need to be copied.
   *
   * @var array
   */
  protected $filesToCopy;

  /**
   * Configure the command.
   */
  protected function configure()
  {
    $this
      ->setDescription('Merges configurations.')
      ->setHelp('This command will take multiple configurations and merge them together.')
      ->addArgument('parent', InputArgument::REQUIRED, 'Parent Config')
      ->addArgument('children', InputArgument::REQUIRED, 'Children Config')
      ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Set the config directory root.', 'config')
      ->addOption('update-hook-file', 'u', InputOption::VALUE_REQUIRED, 'Where to write the update hook to.', FALSE)
      ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run.');
  }

  /**
   * Extract the input of the command and convert this to object properties.
   *
   * @param InputInterface $input
   *   The input object.
   */
  protected function extractAndSetOptions($input) {
    if ($input->hasOption('config')) {
      $this->configDirectory = $input->getOption('config');
    } else {
      $this->configDirectory = 'config';
    }

    if ($input->hasOption('update-hook-file')) {
      $this->updateHookFile = $input->getOption('update-hook-file');
    } else {
      $this->updateHookFile = FALSE;
    }

    $this->dryRun = $input->getOption('dry-run');

    $this->parentConfig = $input->getArgument('parent');
    $this->childrenConfig = $this->extractChildren($input->getArgument('children'));
  }

  /**
   * Extract the children parameter into an array.
   *
   * @param string $children
   *   The comma separated list of children.
   *
   * @return array
   *   The extracted list of children.
   */
  public function extractChildren($children) {
    $children = explode(',', $children);
    $children = array_filter($children);
    foreach ($children as $id => $child) {
      $children[$id] = trim($child);
    }
    sort($children);
    return $children;
  }

  /**
   * Execute the command.
   *
   * @param InputInterface $input
   *   The input object.
   * @param OutputInterface $output
   *   The output object.
   * @return int|null
   *   The return.
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->extractAndSetOptions($input);

    if ($this->dryRun) {
      $output->writeln('<comment>Performing dry run. No files will be changed.</comment>');
    }

    if ($output->isVerbose()) {
      $output->writeln('<comment>Verbose mode is on!</comment>');
    }

    $destinationDirectory = $this->configDirectory . '/default';

    $parentDirectory = $this->configDirectory . '/' . $this->parentConfig;
    $parentConfigSplitFile = $destinationDirectory . '/config_split.config_split.' . $this->parentConfig . '.yml';

    if (!file_exists($parentDirectory)) {
      $output->writeln('<error>Parent directory ' . $parentDirectory . ' does not exist.</error>');
      // Return an error condition.
      return 1;
    }

    // Load all of the files in the parent directory to check them.
    $parentFiles = array_diff(scandir($parentDirectory), array('..', '.'));

    // Set up the config type lists for parent.
    $parentBlacklist = [];
    $parentGraylist = [];

    foreach ($this->childrenConfig as $childConfig) {
      $childDirectory = $this->configDirectory . '/' . $childConfig;
      $childConfigSplitFile = $destinationDirectory . '/config_split.config_split.' . $childConfig . '.yml';

      // Set up the config type lists for parent this child.
      $childBlacklist = [];
      $childGraylist = [];

      foreach ($parentFiles as $filename) {
        // Check to see if we have a yml file.
        if (strstr($filename, '.yml') !== FALSE) {
          // Extract the configuration item name.
          $configName = str_replace('.yml', '', $filename);

          if (file_exists($childDirectory . '/' . $filename) && file_exists($parentDirectory . '/' . $filename)) {
            // File also exists in the children directory.
            // Get the contents of both of the files.
            $parentConfigItemFileContents = file_get_contents($parentDirectory . '/' . $filename);
            $parentArray = Yaml::decode($parentConfigItemFileContents);
            $childConfigItemFileContents = file_get_contents($childDirectory . '/' . $filename);
            $childConfigItemArray = Yaml::decode($childConfigItemFileContents);

            // Calculate the difference between the two files.
            $difference = \Drupal\Component\Utility\DiffArray::diffAssocRecursive($parentArray, $childConfigItemArray);

            if (count($difference) == 1 && isset($difference['uuid'])) {
              // If we have exactly 1 difference and that difference is the uuid then process it.
              // Extract the uuid from the parent and write this to a file.
              $uuid = $parentArray['uuid'];

              $updateUuidList[$configName] = $uuid;

              // Copy the parent file to the destination directory.
              $this->addCopyFile($parentDirectory . '/' . $filename, $destinationDirectory . '/' . $filename);

                // Delete both of the files.
              $this->filesToDelete[] = $parentDirectory . '/' . $filename;
              $this->filesToDelete[] = $childDirectory . '/' . $filename;
            }
            elseif (count($difference) > 0 && !isset($difference['uuid'])) {
              // This is not a uuid change and as it's different we just need to gray list and copy this version to
              // the default config.
              $parentGraylist[] = $configName;
              $childGraylist[] = $configName;
              $this->addCopyFile($parentDirectory . '/' . $filename, $destinationDirectory . '/' . $filename);
            }
            elseif (count($difference) == 0) {
              // If we have exactly no difference between the two files then just move it.
              // Copy the parent file to the destination directory.
              $this->addCopyFile($parentDirectory . '/' . $filename, $destinationDirectory . '/' . $filename);

              // Delete both of the files.
              $this->filesToDelete[] = $parentDirectory . '/' . $filename;
              $this->filesToDelete[] = $childDirectory . '/' . $filename;
            }
          }
          else {
            // Config doesn't exist in child, make sure it doesn't also exist in the default configuration.
            if (!file_exists($destinationDirectory . '/' . $filename)) {
              // Add it to blacklist.
              $parentBlacklist[] = $configName;
            }
          }
        }
      }

      // Run the same checks on the child directory.
      $files = array_diff(scandir($childDirectory), array('..', '.'));

      foreach ($files as $filename) {
        // Check to see if we have a yml file.
        if (strstr($filename, '.yml') !== FALSE) {
          // Extract the configuration name.
          $configName = str_replace('.yml', '', $filename);

          if (!file_exists($parentDirectory . '/' . $filename)) {
            if (!file_exists($destinationDirectory . '/' . $filename)) {
              $childBlacklist[] = $configName;
            }
          }
        }
      }

      if (count($childBlacklist) > 0 || count($childGraylist) > 0 && !$this->dryRun) {
        $this->updateConfigurationSplitFile($childConfigSplitFile, $childBlacklist, $childGraylist);
      }
    }

    if ($output->isVerbose()) {
      $output->writeln(print_r($this->filesToDelete));
      $output->writeln(print_r($this->filesToCopy));
    }

    if (!$this->dryRun) {
      // We have finished analysis of the config files, perform any file
      // operations. We only do this if this is NOT a dry run. The copy
      // operation must happen first.
      $this->copyConfigFiles();
      $this->deleteConfigFiles();
    }

    // Export the data to our config split files.
    if (count($parentBlacklist) > 0 || count($parentGraylist) > 0 && !$this->dryRun) {
      $this->updateConfigurationSplitFile($parentConfigSplitFile, $parentBlacklist, $parentGraylist);
    }

    // Set the update uuid list and generate the hook.
    $this->setUpdateUuidList($updateUuidList);
    $updateHook = $this->generateUpdateHook();

    if ($this->updateHookFile == FALSE) {
      $output->writeln($updateHook);
    }
    else {
      file_put_contents($this->updateHookFile, implode(PHP_EOL, $updateHook));
    }

    $output->writeln('<info>Done</info>');
    return 0;
  }

  /**
   * Helper function to delete config files.
   */
  protected function deleteConfigFiles()
  {
    foreach ($this->filesToDelete as $file) {
      if (file_exists($file)) {
        unlink($file);
      }
    }
  }

  /**
   * Helper function to copy config files.
   */
  protected function copyConfigFiles()
  {
    foreach ($this->filesToCopy as $file) {
      if (file_exists($file['source'])) {
        copy($file['source'], $file['destination']);
      }
    }
  }

  /**
   * Helper function to allow the easy update of the copy array.
   */
  protected function addCopyFile($source, $destination) {
    $this->filesToCopy[] = [
      'source' => $source,
      'destination' => $destination,
    ];
  }

  /**
   * Update the configuration split file.
   *
   * @param string $configSplitFile
   *   The config split file.
   * @param array $blacklist
   *   The black list.
   * @param array $graylist
   *   They gray list.
   */
  public function updateConfigurationSplitFile($configSplitFile, $blacklist, $graylist)
  {
    $configSplitFileContents = file_get_contents($configSplitFile);
    $configSplit = Yaml::decode($configSplitFileContents);

    if (count($blacklist) > 0) {
      $configSplit['blacklist'] = array_unique(array_merge($configSplit['blacklist'], $blacklist));
      sort($configSplit['blacklist']);
    }

    if (count($graylist) > 0) {
      $configSplit['graylist'] = array_unique(array_merge($configSplit['graylist'], $graylist));
      sort($configSplit['graylist']);
    }

    if (!$this->dryRun) {
      file_put_contents($configSplitFile, Yaml::encode($configSplit));
    }
  }

  /**
   * Set the update uuid list.
   *
   * @param array $updateUuidList
   *   The uuid list.
   */
  public function setUpdateUuidList($updateUuidList)
  {
    $this->updateUuidList = $updateUuidList;
  }

  /**
   * Get the uuid list.
   *
   * @return array
   *   The uuid list.
   */
  public function getUpdateUuidList()
  {
    return $this->updateUuidList;
  }

  /**
   * Generate the update hook needed to move config from one site to another.
   */
  public function generateUpdateHook()
  {
    if (empty($this->getUpdateUuidList())) {
      return [];
    }

    $output = [];
    $output[] = '<?php';
    $output[] = '';
    $output[] = '/**';
    $output[] = ' * Fix the uuid information on certain configs.';
    $output[] = ' */';
    $output[] = 'function my_module_update_8001() {';
    $output[] = '  $fieldChanges = [';
    foreach ($this->getUpdateUuidList() as $config => $uuid) {
      $output[] = '    \'' . $config . '\' => \'' . $uuid . '\',';
    }
    $output[] = '  ];';
    $output[] = '';
    $output[] = '  foreach ($fieldChanges as $field => $uuid) {';
    $output[] = '    $config = \Drupal::service(\'config.factory\')->getEditable($field);';
    $output[] = '    $config->set(\'uuid\', $uuid)->save();';
    $output[] = '  }';
    $output[] = '}';

    return $output;
  }
}
