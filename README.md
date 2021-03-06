# KeyMedia integration for eZ Publish

## Installation

### Dependencies
_eZ on the Edge_ extension from [Github](https://github.com/KeyteqLabs/ezote)

* Exceed 1.1 installations should use `legacy` branch
* Exceed 2 installations should use latest `master`


### Using composer

`composer.phar install keyteqlabs/keymedia-extension --save`

### Checkout from github

`git clone git@github.com:KeyteqLabs/keymedia-extension.git /my/ez/extension/keymedia`

### Navigate to the extension.

`cd /my/ez/extension/keymedia`

### Install sql

`mysql -u username -p -h host databasename < sql/mysql/install.sql`

### Regenerate autoloads and clear cache

`php bin/php/ezpgenerateautoloads.php; php bin/php/ezcache.php --clear-all --purge`
	
### Connect to KeyMedia

Head over to _Admin Dashboard_ -> _KeyMedia_ and add your KeyMedia API connection information.

### Make keymedia available in eZOe

In your ezoe.ini in settings/override you must add the following:

```ini
[EditorSettings]
Plugins[]=keymedia

[EditorLayout]
Buttons[]=keymedia
```

The keymedia button could be placed anywhere in the editor. See the eZOe doc on how to arrange buttons.

Setting up versions the user should be able to produce, is done in the keymedia.ini file. Make a keymedia.ini.append.php in the siteacces or the override folder.
This is the possible settings with examples:

```ini
# Disable using focal point scaler strategy
[Scaler]
FocalPoint=false

[Brightcove]
PlayerId=98234987523
PlayerKey=OM932489MGXCV009CXVOM3

# Defines what backend to use for tinymce integration
DefaultBackend=1

[EditorVersion]
# Defines a list of versions that are available in ezoe editor to crop. Must at least have one version
VersionList[]
VersionList[]=small
VersionList[]=medium
VersionList[]=large

# Defines a list of css classes that are available for user in editor
# Separate actual css class and readable name with "|"
ClassList[]
ClassList[]=left|Left adjusted
ClassList[]=right|Right adjusted
ClassList[]=center

# Defines a list of view modes that are available for user in editor.
# The mode name is the same as template file-name. E.g. design/standard/templates/content/datatype/view/ezxmltags/keymedia-embed.tpl
# Separate actual css class and readable name with "|"
ViewModes[]
ViewModes[]=keymedia-embed|Embeded view

# Size definition of each version.
# 0 means that it's unbound. Only one dimension can have an unbound size (means 0x0 is not allowed)

[small]
Size=160x120

[medium]
Size=300x100

[large]
Size=500x0
```

## Developing

Install dev dependencies and run tests:

```
composer.phar install
phpunit tests/
```

In order to run all unit tests the repo need to be placed under `ezpublish/extension/keymedia`,
it will then use the underlying ezpublish installation for extending `eZPersistentObject`

## Usage

### Lookup on tags
```php
<?php
$backend = Backend::first(array('id' => $backendId));
// One tag
$results = $backend->tagged('a tag');
// Multiple tags
$results = $backend->tagged(array('first', 'second'));
// Match either tag
$results = $backend->tagged(array('first', 'second'), array('operator' => 'or'));
// Limit results
$results = $backend->tagged(array('first', 'second'), array('limit' => 1));
```

### Test it in your browser

Its simple! Just head over to _mydomain.com/ezote/delegate/keymedia/user_test/tags_ and it will read your installations available KeyMedia connections
and let you do tag search in them.
You can look at this example class for code as well, its in _modules/user_test/UserTest.php_.

## Upgrade old beta version

KeyMedia was initially named *ezr_keymedia*, and if you have a copy of the extension named as that you must do a few steps:

* Change git-repo to the aforementioned one (if running from git)
* Git pull (if running from git)
* Rename extension; `mv extension/ezr_keymedia extension/keymedia`
* Run sql-upgrade; `mysql -u username -p -h host databasename < sql/mysql/upgrade-1.0.sql`
* Regenerate autoloads and purge cache
