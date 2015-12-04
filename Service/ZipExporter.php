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
     * @var MediaFileExporter
     */
    private $mediaFileExporter;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var int
     */
    private $errCnt = 0;

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
     * @param $mediaFileExporter
     */
    public function __construct($serializer, $mediaFileExporter)
    {
        $this->serializer = $serializer;
        $this->mediaFileExporter = $mediaFileExporter;
        $this->eventDispatcher = new EventDispatcher();
    }


    /**
     * @param int|Site $site
     * @param string $filename
     * @return bool
     */
    public function exportSiteToFile($site, $filename)
    {
        $this->errCnt = 0;
        try {
            // prepare zip file
            $this->eventDispatcher->dispatch('zip.open.before');
            $zip = $this->createZip($filename);
            $this->eventDispatcher->dispatch('zip.open.after');

            // export db data
            $this->exportDbData($zip, $site);

            // export filesystem
            $this->exportMediaFiles($zip, $site);

            // close archive
            $this->eventDispatcher->dispatch('zip.close.before');
            $zip->close();
            $this->eventDispatcher->dispatch('zip.close.after');
            unset($zip);

        } catch (InvalidArgumentException $e) {
            ++$this->errCnt;
            $this->eventDispatcher->dispatch('zip.error', new ZipErrorEvent('ERROR exporting site: ' . $e->getMessage()));

        } catch (\Exception $e) {
            ++$this->errCnt;
            $this->eventDispatcher->dispatch('zip.error', new ZipErrorEvent('ERROR while exporting site: ' . $e->getMessage()));
            $this->eventDispatcher->dispatch('zip.error', new ZipErrorEvent('Rollbacking...'));

            if (isset($zip)) {
                $zip->close();
            }
            if (file_exists($filename)) {
                unlink($filename);
            }
        }

        return $this->errCnt === 0;
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
     * @return MediaFileExporter
     */
    public function getMediaFileExporter()
    {
        return $this->mediaFileExporter;
    }


    /**
     * @return array
     */
    public function getExportOrder()
    {
        return $this->exportOrder;
    }

    /**
     * @return int
     */
    public function getErrCnt()
    {
        return $this->errCnt;
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
    protected function exportDbItem($zip, $name, $methodName, $site)
    {
        $ev = new ExportEvent($name);

        $this->eventDispatcher->dispatch('export.db.object.before', $ev);
        $zip->addFromString($name . '.json', $this->serializer->$methodName($site));
        $this->eventDispatcher->dispatch('export.db.object.after', $ev);
    }

    /**
     * @param \ZipArchive $zip
     * @param string $archiveFileName
     * @param string $filepath
     */
    protected function exportFile($zip, $archiveFileName, $filepath)
    {
        $ev = new ExportEvent($filepath);

        $this->eventDispatcher->dispatch('zip.file.before', $ev);

        if (is_readable($filepath)) {
            $zip->addFile($filepath, $archiveFileName);
        } else {
            ++$this->errCnt;
            $this->eventDispatcher->dispatch('zip.file.error', new ZipErrorEvent('File ' . $filepath . ' doesn\'t exists or cannot be read'));
        }

        $this->eventDispatcher->dispatch('zip.file.after', $ev);
    }

    /**
     * @param \ZipArchive $zip
     * @param Site $site
     */
    protected function exportDbData($zip, $site)
    {
        $this->eventDispatcher->dispatch('zip.db.before');
        foreach ($this->exportOrder as $item) {
            $this->exportDbItem($zip, $item[0], $item[1], $site);
        }
        $this->eventDispatcher->dispatch('zip.db.after');
    }

    /**
     * @param \ZipArchive $zip
     * @param Site $site
     */
    protected function exportMediaFiles($zip, $site)
    {
        $dirname = 'uploads';
        $zip->addEmptyDir($dirname);

        $this->eventDispatcher->dispatch('zip.files.before');
        foreach ($this->mediaFileExporter->exportMediaFilesForSite($site) as $filename => $filepath) {
            $this->exportFile($zip, $dirname . '/' . $filename, $filepath);
        }
        $this->eventDispatcher->dispatch('zip.files.after');
    }
}