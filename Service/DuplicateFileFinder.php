<?php

/**
 * Adapted from https://gist.github.com/vchenin/35e71c4e65cc8d9da64b37853811e9c7
 */
namespace Sivaschenko\CleanMedia\Service;

class DuplicateFileFinder {

    private $_fileList;
    private $_pathList;
    private $_recursive;

    public function __construct($recursive = true) {
        $this->_fileList = [];
        $this->_pathList = [];
        $this->_recursive = $recursive;
    }

    public function addPath($dirPath) {
        $dirPath = rtrim($dirPath, DIRECTORY_SEPARATOR);
        array_push($this->_pathList, $dirPath);
        return $this;
    }

    public function findDuplicates() {
        foreach ($this->_pathList as $path) {
            if (is_dir($path)) {
                $this->scanDir($path);
            }
        }
        return $this;
    }

    public function isRecursive() {
        return $this->_recursive;
    }

    public function getFileList() {
        return $this->_fileList;
    }

    public function getDuplicateSets()
    {
        return array_filter($this->_fileList, function ($data) {
            return count($data) > 1;
        });
    }

    public function getPathList() {
        return $this->_pathList;
    }

    public function scanDir($dirPath) {
        if ($dh = opendir($dirPath)) {
            while (($fileName = readdir($dh)) !== false) {
                if (in_array($fileName, array('.', '..'))) {
                    continue;
                }
                $fullPath = $dirPath . DIRECTORY_SEPARATOR . $fileName;
                if (is_dir($fullPath) && $this->isRecursive()) {
                    $this->scanDir($fullPath);
                } else if (is_file($fullPath)) {
                    if ($hash = hash_file('md5', $fullPath)) {
                        if (empty($this->_fileList[$hash])) {
                            $this->_fileList[$hash] = [];
                        }
                        array_push($this->_fileList[$hash], $fullPath);
                    }
                }
            }
        }
        closedir($dh);
    }
}
