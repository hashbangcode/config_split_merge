Config Split Merge
==================

A tool to merge together separate configurations for Drupal 8 sites.

Taking a primary site this tool will analise the configuration available and perform the following actions.

- If the configuration exists in the primary and not in the secondary then leave it alone and add it to the primary 
config split blacklist.
- If the configuration exists in both sites and there is no difference between the two sites then the configuration is 
placed into a default configuration area.
- If the configuration exists in both sites and that configuration is different then add this configuration to the 
configuration split grey list for both sites and make a copy in the default configuration.
- If the configuration exists in both sites, contains a single difference, and that difference is just the uuid, then
copy the primary configuration to the default configuration area and delete the sibling configuration file. This step 
also generates output in the form of a update hook that can be used to update the uuid for all sites.

Install
-------

Just install the tool using composer into your Drupal codebase.

    composer require --dev hashbangcode/config_split_merge

Once you have used it you can then remove the tool from your site.

    composer remove hashbangcode/config_split_merge

Setup
-----

You first need to ensure that the configuration split config files have been created for the configurations you want
to merge. You don't need to install the configuration splits on all sites, but the config files need to exist.

The tool needs minimal setup, you just need to ensure that the configuration directory structure looks like this.

    /config/default
    /config/default/config_split.config_split.parent.yml
    /config/default/config_split.config_split.siblibng.yml
    /config/parent
    /config/sibling

You can alter the location of the 'default' directory by passing the --config flag.

Usage
-----

__NOTE__: This tool will alter the config directories in-situ, so make sure you have adequate backups before hand!

Run the tool by passing the parent and sibling directories that need analysis.

    ./vendor/bin/config_split_merge drupal:config_split_merge parent sibling

By default the configuration area is assumed to be at 'config', relative to the other two directories. You can set
this location by passing in the --config flag.

    ./vendor/bin/config_split_merge drupal:config_split_merge parent sibling --config=sync/config

If applicable, the tool will also output the result of any config that is shared between sites, but that needs to be
altered in the database before it can be used on one of the sites. This is in the form of an update hook that can be
added to the install profile or module of your choice.

    <?php
    
    /**
     * Fix the uuid information on certain configs.
     */
    function my_module_update_8001() {
      $fieldChanges = [
        'node.type.landing_page' => '2034e796-4d2c-43f0-9135-1afc93052380',
      ];
    
      foreach ($fieldChanges as $field => $uuid) {
        $config = \Drupal::service('config.factory')->getEditable($field);
        $config->set('uuid', $uuid)->save();
      }
    }
    
You can get the tool to put this output in a file with the --update-hook-file flag.

Arguments
---------

The tool takes two arguments.

__parent__ - The parent directory that will be treated as the primary site.

__sibling__ - The sibling directory that will be treated as the sub-site.

Flags
-----

The following flags can be passed to the tool

|Flag|Shortcut|Default|Description|
|----|--------|-------|-----------|
|--config|-c|config|Set the config directory root.|
|--update-hook-file|-u|FALSE|If set then this file of this name will be created and filled with the update hook. Otherwise it will be output to screen.|
|--dry-run|-d|FALSE|Perform a dry run, alters no files.|

@TODO
-----
This tool comes from a proof of concept that seemed to work for a given situation. As a result there are absolutely 
things that could be improved or altered.

- Better output/reporting.
- Allow the tool to run on more than two directories.
- Incorporate a config ignore option to separate configs for certain sites.
- Refactor code.
- More tests!
