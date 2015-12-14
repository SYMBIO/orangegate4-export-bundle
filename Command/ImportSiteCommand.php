<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 01.12.15
 * Time: 13:58
 */

namespace Symbio\OrangeGate\ExportBundle\Command;

use Symbio\OrangeGate\ExportBundle\Event\ImportEvent;
use Symbio\OrangeGate\ExportBundle\Event\ZipErrorEvent;
use Symbio\OrangeGate\ExportBundle\Event\ZipEvent;
use Symbio\OrangeGate\ExportBundle\Exception\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symbio\OrangeGate\ExportBundle\Service\ZipImporter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ImportSiteCommand extends ContainerAwareCommand implements EventSubscriberInterface
{
    /**
     * @var null|OutputInterface
     */
    private $output;

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'import.db.object.before' => 'beforeImportLog',
            'zip.close.before' => 'beforeCloseLog',
            'zip.error' => 'zipErrorLog',
            'zip.file.error' => 'zipErrorLog',
            'zip.files.before' => 'beforeFilesLog'
        ];
    }

    /**
     * Writes Import event to output if possible
     * @param ImportEvent $ev
     */
    public function beforeImportLog(ImportEvent $ev)
    {
        if (null !== $this->output) {
            $this->output->writeln('Importing ' . $ev->getAction() . '...');
        }
    }

    /**
     * Writes Before zip close event to output if possible
     */
    public function beforeCloseLog()
    {
        if (null !== $this->output) {
            $this->output->writeln('Closing zip...');
        }
    }

    /**
     * Writes log before files starts to be imported
     */
    public function beforeFilesLog()
    {
        $this->output->writeln('Importing media files...');
    }

    /**
     * Writes Import error event to output if possible
     * @param ZipErrorEvent $ev
     */
    public function zipErrorLog(ZipErrorEvent $ev)
    {
        if (null !== $this->output) {
            $this->output->writeln('<error>' . $ev->getMessage() . '</error>');
        }
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('orangegate:import:site')
            ->setDescription('Imports site with given ID.')
            ->setDefinition([
                new InputArgument('file', InputArgument::REQUIRED, 'Archive fully-qualified file name'),
            ])
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', '2048M');

        $this->output = $output;

        $filename = $input->getArgument('file');

        $importer = $this->getImporter();
        $importer->getEventDispatcher()->addSubscriber($this);

        try {

            if ($importer->importSiteFromFile($filename)) {
                $output->writeln('<info>DONE without errors</info>');
            } else {
                $output->writeln('Done');
            }
        } catch (\Exception $e) {
            $output->writeln('<error>ERROR importing site: ' . $e->getMessage() . '</error>');
        }
    }

    /**
     * @param int $siteId
     * @throws InvalidArgumentException
     */
    private function loadSite($siteId)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();

        $site = $em->getRepository('SymbioOrangeGatePageBundle:Site')->find($siteId);
        if (null === $site) {
            throw new InvalidArgumentException('Site with given id do not exists!');
        }

        return $site;
    }

    /**
     * @return ZipImporter
     */
    private function getImporter()
    {
        return $this->getContainer()->get('orangegate.export.zipimporter');
    }
}