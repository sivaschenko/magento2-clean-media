<?php

namespace Sivaschenko\CleanMedia\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Sivaschenko\CleanMedia\Model\RemoveDuplicates;
use Sivaschenko\CleanMedia\Model\GetDuplicates;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class Duplicates extends Command
{
    /**
     * Input key for removing duplicate files and updating database
     */
    const INPUT_KEY_REMOVE = 'remove';

    /**
     * @var Filesystem
     */
    public $filesystem;

    /**
     * @var GetDuplicates
     */
    public $getDuplicates;

    /**
     * @var RemoveDuplicates
     */
    private $removeDuplicates;

    /**
     * @param Filesystem $filesystem
     * @param GetDuplicates $getDuplicates
     * @param RemoveDuplicates $removeDuplicates
     */
    public function __construct(
        Filesystem $filesystem,
        GetDuplicates $getDuplicates,
        RemoveDuplicates $removeDuplicates
    ) {
        $this->filesystem = $filesystem;
        $this->getDuplicates = $getDuplicates;
        $this->removeDuplicates = $removeDuplicates;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('sivaschenko:catalog-media:duplicates')
            ->setDescription('List or/and remove media duplicates')
            ->addOption(
                self::INPUT_KEY_REMOVE,
                'r',
                InputOption::VALUE_NONE,
                'Remove duplicated files and update database'
            );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $duplicateSets = $this->getDuplicates->execute([$this->getProductMediaPath()]);

        $output->writeln('Duplicated media files:');
        foreach ($duplicateSets as $duplicates) {
            $output->writeln('---');
            foreach ($duplicates as $duplicate) {
                $output->writeln($duplicate);
            }
        }

        if ($input->getOption(self::INPUT_KEY_REMOVE)) {
            $result = $this->removeDuplicates->execute($duplicateSets, $output);

            foreach ($result['errors'] as $errorMessage) {
                $output->writeln($errorMessage);
            }

            $output->writeln($result['unlinked'] . ' duplicated files have been deleted');
            $output->writeln($result['varchar'] . ' rows have been updated in the catalog_product_entity_varchar table');
            $output->writeln($result['gallery'] . ' rows have been updated in the catalog_product_entity_media_gallery table');
            $output->writeln(round($result['bytes'] / 1024 / 1024) . ' Mb has been freed.');
        }
    }

    /**
     * @return string
     */
    private function getMediaPath(): string
    {
        return $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
    }

    /**
     * @return string
     */
    private function getProductMediaPath(): string
    {
        return $this->getMediaPath() . 'catalog/product/';
    }
}
