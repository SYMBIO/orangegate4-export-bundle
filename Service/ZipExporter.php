<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 03.12.15
 * Time: 10:07
 */

namespace Symbio\OrangeGate\ExportBundle\Service;

use Symbio\OrangeGate\ExportBundle\Event\ExportEvent;
use Symbio\OrangeGate\ExportBundle\Event\ZipErrorEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symbio\OrangeGate\ExportBundle\Exception\InvalidArgumentException;
use Symbio\OrangeGate\PageBundle\Entity\Site;

class ZipExporter
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var array
     */
    private $exportOrder = [
        ['site', 'exportSite'],
        ['contexts', 'exportContextsForSite'],
        ['categories', 'exportCategoriesForSite'],
        ['pages', 'exportPagesForSite'],
        ['translations', 'exportStringsForSite'],
        ['gallery', 'exportGalleryForSite'],
        ['media', 'exportMediaForSite'],
    ];

    /**
     * ZipExporter constructor.
     * @param $serializer
     */
    public function __construct($serializer)
    {
        $this->serializer = $serializer;
        $this->eventDispatcher = new EventDispatcher();
    }


    /**
     * @param int|Site $site
     * @param string $filename
     * @return bool
     */
    public function exportSiteToFile($site, $filename)
    {
        try {
            // prepare zip file
            $this->eventDispatcher->dispatch('zip.open.before');
            $zip = $this->createZip($filename);
            $this->eventDispatcher->dispatch('zip.open.after');

            // export
            foreach ($this->exportOrder as $item) {
                $this->exportItem($zip, $item[0], $item[1], $site);
            }

            // close archive
            $this->eventDispatcher->dispatch('zip.close.before');
            $zip->close();
            $this->eventDispatcher->dispatch('zip.close.after');
            unset($zip);

            return true;

        } catch (InvalidArgumentException $e) {
            $this->eventDispatcher->dispatch('zip.error', new ZipErrorEvent('ERROR exporting site: ' . $e->getMessage()));

        } catch (\Exception $e) {
            $this->eventDispatcher->dispatch('zip.error', new ZipErrorEvent('ERROR while exporting site: ' . $e->getMessage()));

            $this->eventDispatcher->dispatch('zip.error', new ZipErrorEvent('Rollbacking...'));

            if (isset($zip)) {
                $zip->close();
            }
            if (file_exists($filename)) {
                unlink($filename);
            }
        }

        return false;
    }

    /**
     * @return Serializer
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * @return EventDispatcher
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * @return array
     */
    public function getExportOrder()
    {
        return $this->exportOrder;
    }

    /**
     * @param array $exportOrder
     * @return $this
     */
    public function setExportOrder($exportOrder)
    {
        $this->exportOrder = $exportOrder;
        return $this;
    }

    /**
     * Open files for writing. throws Exception when file exists or cannot bye open.
     * @param string $filename
     * @return \ZipArchive
     * @throws InvalidArgumentException
     */
    protected function createZip($filename)
    {
        if ('zip' !== mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION), 'UTF8')) {
            throw new InvalidArgumentException('File should be zip file');
        }

        $zip = new \ZipArchive();
        if (TRUE !== ($zip->open($filename, \ZipArchive::CREATE))) {
            throw new InvalidArgumentException('Cannot open file!');
        }

        return $zip;
    }

    /**
     * @param \ZipArchive $zip
     * @param string $name
     * @param string $methodName
     * @param int|Site $site
     */
    protected function exportItem($zip, $name, $methodName, $site) {
        $this->eventDispatcher->dispatch('export.before', new ExportEvent($name));
        $zip->addFromString($name . '.json', $this->serializer->$methodName($site));
        $this->eventDispatcher->dispatch('export.after', new ExportEvent($name));
    }

}