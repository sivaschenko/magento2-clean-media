# Overview

Refer to: https://github.com/baldwin-agency/magento2-module-image-cleanup which is a more active project doing more / similar work.

I'd say, use that instead :)

The module provides a command for retrieving information about catalog media files.
The original source github repo seems to have been abandoned.

You can use this form, and install via composer:

1. composer config repositories.github.repo.repman.io composer https://github.repo.repman.io
2. composer require sivaschenko/magento2-clean-media

I will maintain this as I need to for self usage.    

```
bin/magento si:catalog:media

Media Gallery entries: 17996.
Files in directory: 23717.
Cached images: 353597.
Unused files: 5847.
Missing files: 4.
Duplicated files: 157.
```

The following options include more details in the output:
 - list all unused files with `-u` option
 - list all files referenced in database but missing in filesystem with `-m` option
 - list all duplicated files with `-d` option

Also it allows to clean up filesystem and db:
 - remove unused files with `-r` option
 - remove database rows referencing non-existing files with `-o` option
 - remove duplicated files and replace references in database with `-x` option

# Installation

Run the following commands from the project root directory:

```
composer require sivaschenko/magento2-clean-media
bin/magento module:enable Sivaschenko_CleanMedia
bin/magento setup:upgrade
```

# Usage

## Information about media

```
bin/magento si:catalog:media

Media Gallery entries: 17996.
Files in directory: 23717.
Cached images: 353597.
Unused files: 5847.
Missing files: 4.
Duplicated files: 1.
```

## List missing files

```
bin/magento si:catalog:media -m

Missing media files:
/i/m/image1.jpg
/i/m/image2.jpg
/i/m/image3.jpg
/i/m/image4.jpg

Media Gallery entries: 17996.
Files in directory: 23717.
Cached images: 353597.
Unused files: 5847.
Missing files: 4.
Duplicated files: 1.
```

## List unused files

```
bin/magento si:catalog:media -u

Unused file: /i/m/image5847.jpg
...

Media Gallery entries: 17996.
Files in directory: 23717.
Cached images: 353597.
Unused files: 5847.
Missing files: 4.
Duplicated files: 1.
```

## Remove unused files

```
bin/magento si:catalog:media -r

Unused "/m/i/mixer.glb" was removed
```

## List duplicated files

```
bin/magento si:catalog:media -m

Duplicate "/i/m/image5847.jpg" to "/i/m/image5007.jpg"

Media Gallery entries: 17996.
Files in directory: 23717.
Cached images: 353597.
Unused files: 5847.
Missing files: 4.
Duplicated files: 1.

Removed unused files: 1.
Disk space freed: 1 Mb
```

## Remove duplicated files

```
bin/magento si:catalog:media -x

Duplicate "/p/u/pub_1.jpg" was removed

Media Gallery entries: 2.
Files in directory: 4.
Cached images: 189.
Unused files: 2.
Missing files: 0.
Duplicated files: 1.

Removed duplicated files: 1.
Updated catalog_product_entity_varchar rows: 1
Updated catalog_product_entity_media_gallery rows: 1
Disk space freed: 1 Mb
```

