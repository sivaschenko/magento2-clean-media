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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
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
     * @var Filesystem
     */
    public $filesystem;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param ResourceConnection $resource
     * @param Filesystem $filesystem
     */
    public function __construct(
        ResourceConnection $resource,
        Filesystem $filesystem,
    ) {
        $this->resource = $resource;
        $this->filesystem = $filesystem;
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
                self::INPUT_KEY_LIST_MISSING,
                'm',
                InputOption::VALUE_NONE,
                'List missing media files'
            )->addOption(
                self::INPUT_KEY_LIST_UNUSED,
                'u',
                InputOption::VALUE_NONE,
                'List unused media files'
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

        $output->writeln(sprintf('Media Gallery entries: %s.', count($mediaGalleryPaths)));
        $output->writeln(sprintf('Files in directory: %s.', count($files)));
        $output->writeln(sprintf('Cached images: %s.', $cachedFiles));
        $output->writeln(sprintf('Unused files: %s.', $unusedFiles));
        $output->writeln(sprintf('Missing files: %s.', count($missingFiles)));
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
    private function getMediaPath(): string
    {
        return $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
    }
}
