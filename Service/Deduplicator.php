<?php

namespace Sivaschenko\CleanMedia\Service;

use Magento\MediaStorage\Model\File\Uploader;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Class Deduplicator
 * @package Sivaschenko\CleanMedia\Service
 */
class Deduplicator
{
    protected $resultVarchar = 0;
    protected $resultGallery = 0;
    protected $unlinked = 0;
    protected $bytesFreed = 0;

    /**
     * @param array $duplicateSets
     * @param ResourceConnection $resource
     * @param OutputInterface $output
     * @return null
     * @throws \Exception
     */
    public function deduplicateSets(
        array $duplicateSets,
        ResourceConnection $resource,
        OutputInterface $output
    ) {
        $db = $resource->getConnection();

        foreach ($duplicateSets as $duplicateSet) {
            $originalFullPath = array_shift($duplicateSet);
            foreach ($duplicateSet as $duplicateFullPath) {
                $originalDispersed = Uploader::getDispersionPath(basename($originalFullPath)) . DIRECTORY_SEPARATOR . basename($originalFullPath);
                $duplicateDispersed = Uploader::getDispersionPath(basename($duplicateFullPath)) . DIRECTORY_SEPARATOR . basename($duplicateFullPath);

                if (file_exists($originalFullPath) && file_exists($duplicateFullPath)) {
                    $db->beginTransaction();
                    $this->resultVarchar += $db->update($resource->getTableName('catalog_product_entity_varchar'),
                        ['value' => $originalDispersed], $db->quoteInto('value = ?', $originalDispersed));
                    $this->resultGallery += $db->update($resource->getTableName('catalog_product_entity_media_gallery'),
                        ['value' => $originalDispersed], $db->quoteInto('value = ?', $duplicateDispersed));
                    $db->commit();

                    $output->writeln('Replaced ' . $duplicateDispersed . ' with ' . $originalDispersed . ' (' . $resultVarchar . '/' . $resultGallery . ')');
                    $this->bytesFreed += filesize($duplicateFullPath);
                    unlink($duplicateFullPath);
                    $this->unlinked++;
                    if (file_exists($duplicateFullPath)) {
                        throw new \Exception('File ' . $duplicateFullPath . ' not deleted; permissions issue?');
                    }
                } else {
                    if (!file_exists($duplicateFullPath)) {
                        $output->writeln('Duplicate file ' . $duplicateFullPath . ' does not exist.');
                    }
                    if (!file_exists($originalFullPath)) {
                        $output->writeln('Original file ' . $originalFullPath . ' does not exist.');
                    }
                }
            }
        }
    }

    /**
     * @return int
     */
    public function getResultVarchar(): int
    {
        return $this->resultVarchar;
    }

    /**
     * @return int
     */
    public function getResultGallery(): int
    {
        return $this->resultGallery;
    }

    /**
     * @return int
     */
    public function getUnlinked(): int
    {
        return $this->unlinked;
    }

    /**
     * @return int
     */
    public function getBytesFreed(): int
    {
        return $this->bytesFreed;
    }
}