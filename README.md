# Overview

The module provides a command for retrieving information about catalog media and allows to remove unused images

# Installation

Run the following commands from the project root directory:

```
composer require sivaschenko/magento2-clean-media
bin/magento module:enable Sivaschenko_Media
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
```

## List unused files

```
bin/magento si:catalog:media -u

Unused files:
/i/m/image1.jpg
...
/i/m/image5847.jpg
Media Gallery entries: 17996.
Files in directory: 23717.
Cached images: 353597.
Unused files: 5847.
Missing files: 4.
```

## Remove unused files

```
bin/magento si:catalog:media -r
```

