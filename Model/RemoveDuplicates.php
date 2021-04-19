<?php

namespace Sivaschenko\CleanMedia\Model;

use Magento\MediaStorage\Model\File\Uploader;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;

class RemoveDuplicates
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    /**
     * @param array $duplicateSets
     * @param OutputInterface $output
     * @throws \Exception
     */
    public function execute(
        array $duplicateSets,
        OutputInterface $output
    ) {
        $errors = [];
        $resultVarchar = 0;
        $resultGallery = 0;
        $unlinked = 0;
        $bytesFreed = 0;
        $db = $this->resource->getConnection();

        foreach ($duplicateSets as $duplicateSet) {
            $originalFullPath = array_shift($duplicateSet);
            foreach ($duplicateSet as $duplicateFullPath) {
                $originalDispersed = Uploader::getDispersionPath(basename($originalFullPath)) . DIRECTORY_SEPARATOR . basename($originalFullPath);
                $duplicateDispersed = Uploader::getDispersionPath(basename($duplicateFullPath)) . DIRECTORY_SEPARATOR . basename($duplicateFullPath);

                if (file_exists($originalFullPath) && file_exists($duplicateFullPath)) {
                    $db->beginTransaction();
                    $resultVarchar += $db->update($this->resource->getTableName('catalog_product_entity_varchar'),
                        ['value' => $originalDispersed], $db->quoteInto('value = ?', $originalDispersed));
                    $resultGallery += $db->update($this->resource->getTableName('catalog_product_entity_media_gallery'),
                        ['value' => $originalDispersed], $db->quoteInto('value = ?', $duplicateDispersed));
                    $db->commit();

                    $output->writeln('Replaced ' . $duplicateDispersed . ' with ' . $originalDispersed . ' (' . $resultVarchar . '/' . $resultGallery . ')');
                    $bytesFreed += filesize($duplicateFullPath);
                    unlink($duplicateFullPath);
                    $unlinked++;
                    if (file_exists($duplicateFullPath)) {
                        $errors[] = 'File ' . $duplicateFullPath . ' not deleted; permissions issue?';
                    }
                } else {
                    if (!file_exists($duplicateFullPath)) {
                        $errors[] = 'Duplicate file ' . $duplicateFullPath . ' does not exist.';
                    }
                    if (!file_exists($originalFullPath)) {
                        $errors[] = 'Original file ' . $originalFullPath . ' does not exist.';
                    }
                }
            }
        }
        return [
            'varchar' => $resultVarchar,
            'gallery' => $resultGallery,
            'unlinked' => $unlinked,
            'bytes' => $bytesFreed,
            'errors' => $errors
        ];
    }
}
