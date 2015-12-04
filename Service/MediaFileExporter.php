<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 03.12.15
 * Time: 18:33
 */

namespace Symbio\OrangeGate\ExportBundle\Service;

use Doctrine\ORM\EntityManager;
use Sonata\MediaBundle\Twig\Extension\MediaExtension;

class MediaFileExporter
{
    /**
     * @var Sonata\MediaBundle\Twig\Extension\MediaExtension
     */
    protected $twigMedia;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var string
     */
    protected $webRoot;

    /**
     * MediaFileExporter constructor.
     * @param \Sonata\MediaBundle\Twig\Extension\MediaExtension $twigMedia
     * @param \Doctrine\ORM\EntityManager $entityManager
     */
    public function __construct($twigMedia, $entityManager, $rootDir)
    {
        $this->twigMedia = $twigMedia;
        $this->entityManager = $entityManager;
        $this->webRoot = $rootDir . '/../web';
    }

    /**
     * @param $site
     * @return array
     */
    public function exportMediaFilesForSite($site)
    {

        $contexts = $this->entityManager->getRepository('SymbioOrangeGateClassificationBundle:Context')->findBy([
            'site' => $site
        ]);

        $media = $this->entityManager->getRepository('SymbioOrangeGateMediaBundle:Media')->findBy([
            'context' => $contexts
        ]);

        $paths = [];
        foreach($media as $item) {
            $paths[$item->getProviderReference()] = $this->webRoot . $this->twigMedia->path($item, 'reference');
        }

        return $paths;
    }
}