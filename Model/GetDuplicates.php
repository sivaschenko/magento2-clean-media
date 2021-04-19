<?php

/**
 * Adapted from https://gist.github.com/vchenin/35e71c4e65cc8d9da64b37853811e9c7
 */
namespace Sivaschenko\CleanMedia\Model;

class GetDuplicates
{
    /**
     * @param bool $isRecursive
     * @return array
     */
    public function execute($paths, $isRecursive = true)
    {
        $duplicates = [];
        foreach ($paths as $path) {
            $path = rtrim($path, DIRECTORY_SEPARATOR);
            if (is_dir($path)) {
                $duplicates = array_merge_recursive($duplicates, $this->scanDir($path, $isRecursive));
            }
        }
        return array_filter($duplicates, function ($data) {
            return count($data) > 1;
        });
    }

    /**
     * @param string $path
     * @param bool $isRecursive
     * @return array
     */
    private function scanDir($path, $isRecursive = true)
    {
        $dir = opendir($path);
        if (!$dir) {
            return [];
        }
        $duplicates = [];
        while (($fileName = readdir($dir)) !== false) {
            if (in_array($fileName, array('.', '..'))) {
                continue;
            }
            $fullPath = $path . DIRECTORY_SEPARATOR . $fileName;
            if (is_dir($fullPath) && $isRecursive) {
                $duplicates = array_merge_recursive($duplicates, $this->scanDir($fullPath));
            } else {
                if (is_file($fullPath)) {
                    $hash = hash_file('md5', $fullPath);
                    $files[$hash] = $fullPath;
                }
            }
        }
        closedir($dir);
        return $duplicates;
    }
}
