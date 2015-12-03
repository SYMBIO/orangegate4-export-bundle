<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 01.12.15
 * Time: 13:58
 */

namespace Symbio\OrangeGate\ExportBundle\Command;

use Symbio\OrangeGate\ExportBundle\Event\ExportEvent;
use Symbio\OrangeGate\ExportBundle\Event\ZipErrorEvent;
use Symbio\OrangeGate\ExportBundle\Event\ZipEvent;
use Symbio\OrangeGate\ExportBundle\Exception\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symbio\OrangeGate\ExportBundle\Service\Exporter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExportSiteCommand extends ContainerAwareCommand implements EventSubscriberInterface
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
            'export.before' => 'beforeExportLog',
            'zip.close.before' => 'beforeCloseLog',
            'zip.error' => 'zipErrorLog',
        ];
    }

    /**
     * Writes Export event to output if possible
     * @param ExportEvent $ev
     */
    public function beforeExportLog(ExportEvent $ev)
    {
        if (null !== $this->output) {
            $this->output->writeln('Exporting ' . $ev->getAction() . '...');
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
     * Writes Export error event to output if possible
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
            ->setName('orangegate:export:site')
            ->setDescription('Exports site with given ID.')
            ->setDefinition([
                new InputArgument('site_id', InputArgument::REQUIRED, 'Site that you want to export'),
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
        $siteId = $input->getArgument('site_id');

        $exporter = $this->getExporter();
        $exporter->getEventDispatcher()->addSubscriber($this);

        try {
            // load site
            $site = $this->loadSite($siteId);

            if ($exporter->exportSiteToFile($site, $filename)) {
                $output->writeln('<info>DONE without errors</info>');
            } else {
                $output->writeln('Done');
            }
        } catch (\Exception $e) {
            $output->writeln('<error>ERROR exporting site: ' . $e->getMessage() . '</error>');
        }
    }

    /**
     * @param int $siteId
     * @return Site
     * @throws \Exception
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
     * @return ZipExporter
     */
    private function getExporter()
    {
        return $this->getContainer()->get('orangegate.export.zipexporter');
    }
}