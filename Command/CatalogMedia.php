<?php
/**
 * @package     Sivaschenko\Media
 * @author      Sergii Ivashchenko <contact@sivaschenko.com>
 * @copyright   2017-2018, Sergii Ivashchenko
 * @license     MIT
 */
namespace Sivaschenko\CleanMedia\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DB\Select;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\Uploader;
use Sivaschenko\CleanMedia\Service\DuplicateFileFinder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\Filesystem\Driver\File;
use Symfony\Component\Console\Input\InputOption;

class CatalogMedia extends Command
{
    /**
     * Input key for removing unused images
     */
    const INPUT_KEY_REMOVE_UNUSED = 'remove_unused';

    /**
     * Input key for removing orphaned media gallery rows
     */
    const INPUT_KEY_REMOVE_ORPHANED_ROWS = 'remove_orphaned_rows';

    /**
     * Input key for listing missing files
     */
    const INPUT_KEY_LIST_MISSING = 'list_missing';

    /**
     * Input key for listing unused files
     */
    const INPUT_KEY_LIST_UNUSED = 'list_unused';

    /**
     * Input key for listing duplicate files
     */
    const INPUT_KEY_LIST_DUPES = 'list_dupes';

    /**
     * Input key for removing duplicate files and updating database
     */
    const INPUT_KEY_REMOVE_DUPES = 'remove_dupes';
    /**
     * @var Filesystem
     */
    public $filesystem;
    /**
     * @var DuplicateFileFinder
     */
    public $duplicateFileFinder;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var File
     */
    private $file;

    /**
     * Constructor
     *
     * @param ResourceConnection $resource
     * @param File $file
     * @param Filesystem $filesystem
     * @param DuplicateFileFinder $duplicateFileFinder
     */
    public function __construct(
        ResourceConnection $resource,
        File $file,
        Filesystem $filesystem,
        DuplicateFileFinder $duplicateFileFinder
    )
    {
        $this->resource = $resource;
        $this->file = $file;
        $this->filesystem = $filesystem;
        $this->duplicateFileFinder = $duplicateFileFinder;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('sivaschenko:catalog:media')
            ->setDescription('Get information about catalog product media')
            ->addOption(
                self::INPUT_KEY_REMOVE_UNUSED,
                'r',
                InputOption::VALUE_NONE,
                'Remove unused product images'
            )->addOption(
                self::INPUT_KEY_REMOVE_ORPHANED_ROWS,
                'o',
                InputOption::VALUE_NONE,
                'Remove orphaned media gallery rows'
            )->addOption(
                self::INPUT_KEY_REMOVE_DUPES,
                'x',
                InputOption::VALUE_NONE,
                'Remove duplicated files and update database'
            )->addOption(
                self::INPUT_KEY_LIST_MISSING,
                'm',
                InputOption::VALUE_NONE,
                'List missing media files'
            )->addOption(
                self::INPUT_KEY_LIST_UNUSED,
                'u',
                InputOption::VALUE_NONE,
                'List unused media files'
            )->addOption(
                self::INPUT_KEY_LIST_DUPES,
                'd',
                InputOption::VALUE_NONE,
                'List duplicated files'
            );
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mediaGalleryPaths = $this->getMediaGalleryPaths();

        $db = $this->resource->getConnection();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->getMediaPath(),
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
            )
        );

        $files = [];
        $unusedFiles = 0;
        $cachedFiles = 0;

        if ($input->getOption(self::INPUT_KEY_LIST_UNUSED)) {
            $output->writeln('Unused files:');
        }
        /** @var $info \SplFileInfo */
        foreach ($iterator as $info) {
            $filePath = str_replace($this->getMediaPath(), '', $info->getPathname());
            if (strpos($filePath, '/cache') === 0) {
                $cachedFiles++;
                continue;
            }
            $files[] = $filePath;
            if (!in_array($filePath, $mediaGalleryPaths)) {
                $unusedFiles++;
                if ($input->getOption(self::INPUT_KEY_LIST_UNUSED)) {
                    $output->writeln($filePath);
                }
                if ($input->getOption(self::INPUT_KEY_REMOVE_UNUSED)) {
                    unlink($info->getPathname());
                }
            }
        }

        if ($input->getOption(self::INPUT_KEY_REMOVE_UNUSED)) {
            $output->writeln('Unused files were removed!');
        }

        $missingFiles = array_diff($mediaGalleryPaths, $files);
        if ($input->getOption(self::INPUT_KEY_LIST_MISSING)) {
            $output->writeln('Missing media files:');
            $output->writeln(implode("\n", $missingFiles));
        }

        if ($input->getOption(self::INPUT_KEY_REMOVE_ORPHANED_ROWS)) {
            $db->delete($this->resource->getTableName(Gallery::GALLERY_TABLE), ['value IN (?)' => $missingFiles]);
        }

        $duplicatedFiles = [];
        if ($input->getOption(self::INPUT_KEY_LIST_DUPES)) {
            $resultVarchar = 0;
            $resultGallery = 0;
            $unlinked = 0;
            $bytesFreed = 0;
            $duplicateSets = $this->duplicateFileFinder->addPath($this->getProductMediaPath())->findDuplicates()->getDuplicateSets();

            foreach ($duplicateSets as $duplicateSet) {
                $originalFullPath = array_shift($duplicateSet);
                foreach($duplicateSet as $duplicateFullPath) {
                    $originalDispersed = Uploader::getDispersionPath(basename($originalFullPath)) . DIRECTORY_SEPARATOR . basename($originalFullPath);
                    $duplicateDispersed = Uploader::getDispersionPath(basename($duplicateFullPath)) . DIRECTORY_SEPARATOR . basename($duplicateFullPath);

                    if(file_exists($originalFullPath) && file_exists($duplicateFullPath)) {
                        $db->beginTransaction();
                        $resultVarchar += $db->update($this->resource->getTableName('catalog_product_entity_varchar'), ['value' => $originalDispersed], $db->quoteInto('value = ?', $originalDispersed));
                        $resultGallery += $db->update($this->resource->getTableName('catalog_product_entity_media_gallery'), ['value' => $originalDispersed], $db->quoteInto('value = ?', $duplicateDispersed));
                        $db->commit();

                        $output->writeln('Replaced ' . $duplicateDispersed . ' with ' . $originalDispersed . ' (' . $resultVarchar . '/' . $resultGallery . ')');
                        $bytesFreed += filesize($duplicateFullPath);
                        unlink($duplicateFullPath);
                        $unlinked++;
                        if(file_exists($duplicateFullPath)) {
                            throw new \Exception('File ' . $duplicateFullPath . ' not deleted; permissions issue?');
                        }
                    } else {
                        if(!file_exists($duplicateFullPath)) {
                            $output->writeln('Duplicate file ' . $duplicateFullPath . ' does not exist.');
                        }
                        if(!file_exists($originalFullPath)) {
                            $output->writeln('Original file ' . $originalFullPath . ' does not exist.');
                        }
                    }
                }
            }

            $output->writeln($unlinked . ' duplicated files have been deleted');
            $output->writeln($resultVarchar . ' rows have been updated in the catalog_product_entity_varchar table');
            $output->writeln($resultGallery . ' rows have been updated in the catalog_product_entity_media_gallery table');
            $output->writeln(round($bytesFreed / 1024 / 1024) . ' Mb has been freed.');
        }

        $output->writeln(sprintf('Media Gallery entries: %s.', count($mediaGalleryPaths)));
        $output->writeln(sprintf('Files in directory: %s.', count($files)));
        $output->writeln(sprintf('Cached images: %s.', $cachedFiles));
        $output->writeln(sprintf('Unused files: %s.', $unusedFiles));
        $output->writeln(sprintf('Missing files: %s.', count($missingFiles)));
        if ($input->getOption(self::INPUT_KEY_LIST_DUPES)) {
            $output->writeln(sprintf('Duplicated files: %s.', count($duplicatedFiles)));
        }
    }

    /**
     * @return array
     */
    private function getMediaGalleryPaths()
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(Gallery::GALLERY_TABLE))
            ->reset(Select::COLUMNS)->columns('value');

        return $connection->fetchCol($select);
    }

    /**
     * @return string
     */
    protected function getMediaPath(): string
    {
        return $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
    }

    /**
     * @return string
     */
    protected function getProductMediaPath(): string
    {
        return $this->getMediaPath() . 'catalog/product/';
    }
}
