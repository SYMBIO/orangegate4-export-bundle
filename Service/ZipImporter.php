<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 04.12.15
 * Time: 14:36
 */

namespace Symbio\OrangeGate\ExportBundle\Service;

use Symbio\OrangeGate\ExportBundle\Entity\Import;
use Symbio\OrangeGate\ExportBundle\Event\ExportEvent;
use Symbio\OrangeGate\ExportBundle\Event\ZipErrorEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symbio\OrangeGate\ExportBundle\Exception\InvalidArgumentException;
use Symbio\OrangeGate\PageBundle\Entity\Site;

class ZipImporter
{
    /**
     * @var Deserializer
     */
    private $deserializer;

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
     * @param $deserializer
     */
    public function __construct($deserializer)
    {
        $this->deserializer = $deserializer;
        $this->eventDispatcher = new EventDispatcher();
    }


    /**
     * @param int|Site $site
     * @param string $file
     * @return bool
     */
    public function importSiteFromFile($site, $file)
    {
        $this->errCnt = 0;
        try {
            // prepare zip file
            $this->eventDispatcher->dispatch('zip.open.before');
            $zip = $this->openZip($file);
            $this->eventDispatcher->dispatch('zip.open.after');

            // start new import
            $this->deserializer->setImport(new Import());

            // import db data
            $this->importDbData($zip, $site);

            // close archive
            $this->eventDispatcher->dispatch('zip.close.before');
            $zip->close();
            $this->eventDispatcher->dispatch('zip.close.after');
            unset($zip);

        } catch (\Exception $e) {
            ++$this->errCnt;
            $this->eventDispatcher->dispatch('zip.error', new ZipErrorEvent('ERROR while importing site: ' . $e->getMessage()));

            if (isset($zip)) {
                $zip->close();
            }
        }

        return $this->errCnt === 0;
    }

    /**
     * @return int
     */
    public function getErrCnt()
    {
        return $this->errCnt;
    }
    /**
     * Open files for reading. throws Exception when file doesn't exists or cannot bye open.
     * @param string $filename
     * @return \ZipArchive
     * @throws InvalidArgumentException
     */
    protected function openZip($filename)
    {
        if ('zip' !== mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION), 'UTF8')) {
            throw new InvalidArgumentException('File should be zip file');
        }

        $zip = new \ZipArchive();
        if (TRUE !== ($zip->open($filename))) {
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