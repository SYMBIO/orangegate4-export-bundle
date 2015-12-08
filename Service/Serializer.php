<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 02.12.15
 * Time: 11:02
 */

namespace Symbio\OrangeGate\ExportBundle\Service;

use Symbio\OrangeGate\ExportBundle\Exception\InvalidArgumentException;
use Symbio\OrangeGate\PageBundle\Entity\Site;
use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\SerializerBuilder;

class Serializer
{
    /**
     * @var \JMS\Serializer\Serializer
     */
    protected $serializer;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var string
     */
    protected $serializeMethod = 'json';


    /**
     * Exporter constructor.
     * @param \JMS\Serializer\Serializer $serializer
     * @param \Doctrine\ORM\EntityManager $entityManager
     */
    public function __construct($serializer, $entityManager)
    {
        if ($serializer === NULL) {
           $this->serializer = SerializerBuilder::create()
            ->addMetadataDir(__DIR__ . '/../Resources/serializer')
            ->build()
        ;
        } else {
            $this->serializer = $serializer;
        }

        $this->entityManager = $entityManager;
    }


    public function exportSite($site)
    {
        $site = $this->getSite($site);

        // todo
        $site->setCreatedAt(null);
        $site->setUpdatedAt(null);

        foreach ($site->getLanguageVersions() as $lv) {
            $lv->setCreatedAt(null);
            $lv->setUpdatedAt(null);
        }

        return $this->serialize($site);
    }

    public function exportPagesForSite($site)
    {
        $site = $this->getSite($site);

        $pages = $this->entityManager->getRepository('SymbioOrangeGatePageBundle:Page')->findBy([
            'site' => $site,
            'parent' => null
        ]);

        foreach ($pages as $page) {
            $this->pageWalker($page);
        }

        return $this->serialize($pages);
    }

    public function exportStringsForSite($site)
    {
        $site = $this->getSite($site);

        $strings = $this->entityManager->getRepository('SymbioOrangeGateTranslationBundle:LanguageToken')->findBy([
            'site' => $site
        ]);

        foreach ($strings as $string) {
            $string->setSite(null);
        }

        return $this->serialize($strings);
    }

    public function exportContextsForSite($site)
    {
        $site = $this->getSite($site);

        $contexts = $this->entityManager->getRepository('SymbioOrangeGateClassificationBundle:Context')->findBy([
            'site' => $site
        ]);

        foreach ($contexts as $context) {
            $context->setCreatedAt(null);
            $context->setUpdatedAt(null);
            $context->setSite(null);
        }

        return $this->serialize($contexts);
    }

    public function exportCategoriesForSite($site)
    {
        $site = $this->getSite($site);

        $contexts = $this->entityManager->getRepository('SymbioOrangeGateClassificationBundle:Context')->findBy([
            'site' => $site
        ]);

        $categories = $this->entityManager->getRepository('SymbioOrangeGateClassificationBundle:Category')->findBy([
            'context' => $contexts
        ]);

        foreach ($categories as $category) {
            //todo unable to do this
//            $category->setCreatedAt(null);
//            $category->setUpdatedAt(null);
            $context = $category->getContext();
            $context->setCreatedAt(null);
            $context->setUpdatedAt(null);
            $context->setSite(null);
        }

        return $this->serialize($categories);
    }

    public function exportMediaForSite($site)
    {
        $site = $this->getSite($site);

        $contexts = $this->entityManager->getRepository('SymbioOrangeGateClassificationBundle:Context')->findBy([
            'site' => $site
        ]);

        $media = $this->entityManager->getRepository('SymbioOrangeGateMediaBundle:Media')->findBy([
            'context' => $contexts
        ]);

        foreach ($media as $item) {
            $item->setCreatedAt(null);
            $item->setUpdatedAt(null);
            $category = $item->getCategory();
//            $category->setCreatedAt(null);
//            $category->setUpdatedAt(null);
            $context = $category->getContext();
            $context->setCreatedAt(null);
            $context->setUpdatedAt(null);
            $context->setSite(null);
        }

        return $this->serialize($media);
    }

    public function exportGalleryForSite($site)
    {
        $site = $this->getSite($site);

        $media = $this->entityManager->getRepository('SymbioOrangeGateMediaBundle:Gallery')->findBy([
            'site' => $site
        ]);

        foreach ($media as $category) {
            $category->setCreatedAt(null);
            $category->setUpdatedAt(null);
            $category->setSite(null);
        }

        return $this->serialize($media);
    }

    /**
     * @return JMS\Serializer\Serializer
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * @param JMS\Serializer\Serializer $serializer
     * @return $this
     */
    public function setSerializer($serializer)
    {
        $this->serializer = $serializer;
        return $this;
    }

    /**
     * @return Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @param Doctrine\ORM\EntityManager $entityManager
     * @return $this
     */
    public function setEntityManager($entityManager)
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * @return string
     */
    public function getSerializeMethod()
    {
        return $this->serializeMethod;
    }

    /**
     * @param string $serializeMethod
     * @return $this
     */
    public function setSerializeMethod($serializeMethod)
    {
        $this->serializeMethod = $serializeMethod;
        return $this;
    }

    /**
     * @param int|Site $site
     * @return Site
     * @throws InvalidArgumentException
     */
    protected function getSite($site)
    {
        if (is_int($site)) {
            $site = $this->entityManager->getRepository('SymbioOrangeGatePageBundle:Site')->find($site);
        }

        if ($site instanceof Site) {
            return $site;
        } else {
            throw new InvalidArgumentException('$site must be id of existing site or Symbio\OrangeGate\PageBundle\Entity\Site instance.');
        }
    }

    /**
     * @param $data
     * @return string json string of serialized data
     */
    protected function serialize($data)
    {
        return $this->serializer->serialize($data, $this->serializeMethod);
    }

    protected function pageWalker($page) {
        $page->setSite(null);
        $page->setCreatedAt(null);
        $page->setUpdatedAt(null);
        $page->setSnapshots(new ArrayCollection());

        foreach ($page->getBlocks() as $block) {
            $this->blockWalker($block);
        }

        foreach ($page->getChildren() as $child) {
            $this->pageWalker($child);
        }
    }

    protected function blockWalker($block) {
        $block->setSite(null);
        $block->setCreatedAt(null);
        $block->setUpdatedAt(null);
//        $block->setSnapshots(new ArrayCollection());

        foreach ($block->getChildren() as $child) {
            $this->blockWalker($child);
        }
    }
}
