<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 04.12.15
 * Time: 15:18
 */

namespace Symbio\OrangeGate\ExportBundle\Service;

use Symbio\OrangeGate\ExportBundle\Entity\Map;
use Symbio\OrangeGate\ExportBundle\Exception\InconsistentStateException;
use Symbio\OrangeGate\ExportBundle\Exception\InvalidArgumentException;
use Symbio\OrangeGate\PageBundle\Entity\Site;
use JMS\Serializer\SerializerBuilder;
use Symbio\OrangeGate\ExportBundle\Entity\Import;
use Doctrine\Common\Collections\ArrayCollection;
use Symbio\OrangeGate\PageBundle\Entity\Page;

class Deserializer
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
     * @var null|Import
     */
    protected $import;

    /**
     * Deserializer constructor.
     * @param \JMS\Serializer\Serializer $serializer
     * @param \Doctrine\ORM\EntityManager $entityManager
     */
    public function __construct($serializer, $entityManager)
    {
        if ($serializer === NULL) {
            $this->serializer = SerializerBuilder::create()
                ->addMetadataDir(__DIR__ . '/../Resources/config/serializer')
                ->build();
        } else {
            $this->serializer = $serializer;
        }
        $this->entityManager = $entityManager;
    }

    /**
     * @return null|Import
     */
    public function getImport()
    {
        return $this->import;
    }

    public function startImport()
    {
        $this->import = new Import();
        $this->entityManager->persist($this->import);
        $this->entityManager->flush();
    }


    public function createSite($inputStr)
    {
        $site = $this->serializer->deserialize($inputStr, 'Symbio\OrangeGate\PageBundle\Entity\Site', $this->serializeMethod);
        $this->entityManager->persist($site);
        $this->entityManager->flush();

        return $site;
    }

    public function createContextsForSite($inputStr, $site)
    {
        $contexts = $this->serializer->deserialize(
            $inputStr,
            'ArrayCollection<Symbio\OrangeGate\ClassificationBundle\Entity\Context>',
            $this->serializeMethod
        );

        foreach ($contexts as $context) {
            // attach to current site
            $context->setSite($site);

            // persist to db
            $this->entityManager->persist($context);
        }

        $this->entityManager->flush();

        return $contexts;
    }

    public function createCategoriesForSite($inputStr, $site)
    {
        $contexts = $this->entityManager->getRepository('SymbioOrangeGateClassificationBundle:Context')->findBy([
            'site' => $site
        ]);

        $contextMap = [];

        foreach ($contexts as $context) {
            $contextMap[$context->getId()] = $context;
        }

        unset($contexts);

        $categories = $this->serializer->deserialize(
            $inputStr,
            'ArrayCollection<Symbio\OrangeGate\ClassificationBundle\Entity\Category>',
            $this->serializeMethod
        );

        $this->categoryRecursiveWalker($categories, $contextMap);

        $this->entityManager->flush();

        $this->categoryMapRecursiveWalker($categories);

        $this->entityManager->flush();

        return $categories;
    }

    public function createStringsForSite($inputStr, $site)
    {
        $strings = $this->serializer->deserialize(
            $inputStr,
            'ArrayCollection<Symbio\OrangeGate\TranslationBundle\Entity\LanguageToken>',
            $this->serializeMethod
        );

        foreach ($strings as $string) {
            $string->setSite($site);

            $this->entityManager->persist($string);

            foreach ($string->getTranslations() as $translation) {
                $translation->setLanguageToken($string);
                $this->entityManager->persist($translation);
            }
        }

        $this->entityManager->flush();

        return $strings;
    }

    public function createGalleryForSite($inputStr, $site)
    {
        $items = $this->serializer->deserialize(
            $inputStr,
            'ArrayCollection<Symbio\OrangeGate\MediaBundle\Entity\Gallery>',
            $this->serializeMethod
        );

        foreach ($items as $gallery) {
            $gallery->setSite($site);
            $gallery->setGalleryHasMedias(new ArrayCollection());

            $gallery->oldId = $gallery->getId();

            foreach ($gallery->getTranslations() as $translation) {
                $translation->setObject($gallery);
            }

            $this->entityManager->persist($gallery);
        }

        $this->entityManager->flush();

        foreach ($items as $gallery) {
            $this->addImportMap('Gallery', $gallery->oldId, $gallery->getId());
        }

        $this->entityManager->flush();

        return $items;
    }

    public function createPagesForSite($inputStr, $site)
    {
        $items = $this->serializer->deserialize(
            $inputStr,
            'ArrayCollection<Symbio\OrangeGate\PageBundle\Entity\Page>',
            $this->serializeMethod
        );

        $this->pageRecursiveWalker($site, $items);

        $this->entityManager->flush();

        $this->pageMapRecursiveWalker($items);

        $this->entityManager->flush();

        // todo how to solve target and sources?

        // todo page references in blocks

        return $items;
    }

    public function createMediasForSite($inputStr)
    {
        $items = $this->serializer->deserialize(
            $inputStr,
            'ArrayCollection<Symbio\OrangeGate\MediaBundle\Entity\Media>',
            $this->serializeMethod
        );

        foreach ($items as $media) {
            $category = $this->findObjectByMap('Category', $media->getCategory()->getId());

            $media->setCategory($category);

            // todo take care of img file

            // todo media has gallery

            $this->entityManager->persist($media);
        }

        $this->entityManager->flush();

    }

    protected function addImportMap($objectName, $oldId, $newId)
    {
        $importMap = new Map(null, $objectName, $oldId, $newId, $this->import);
        $this->entityManager->persist($importMap);
    }

    protected function findObjectByMap($objectName, $oldId)
    {
        switch ($objectName) {
            case 'Category':
                $repositoryName = 'SymbioOrangeGateClassificationBundle:Category';
                break;
            case 'Gallery':
                $repositoryName = 'SymbioOrangeGateMediaBundle:Gallery';
                break;
            case 'Page':
                $repositoryName = 'SymbioOrangeGatePageBundle:Page';
                break;
            default:
                throw new \Exception('Unknown map object name: ' . $objectName);
        }

        $map = $this->entityManager->getRepository('SymbioOrangeGateExportBundle:Map')->findOneBy([
            'import' => $this->getImport(),
            'entity' => $objectName,
            'oldId' => $oldId,
        ]);

        if (null === $map) {
            throw new InconsistentStateException('Cannot find map object for oldId: ' . $oldId);
        }

        $newId = $map->getNewId();

        $object = $this->entityManager->getRepository($repositoryName)->find($newId);

        if (null === $object) {
            throw new InconsistentStateException('Cannot find category object for newId: ' . $newId);
        }

        return $object;
    }

    protected function categoryRecursiveWalker($categories, &$contextMap, $parent = null)
    {
        foreach ($categories as $category) {
            $category->setContext($contextMap[$category->getContext()->getId()]);

            $category->oldId = $category->getId();

            if (null !== $parent) {
                $category->setParent($parent);
            }

            $this->entityManager->persist($category);


            $children = $category->getChildren();

            if (null !== $children) {
                $this->categoryRecursiveWalker($children, $contextMap, $category);
            }
        }
    }

    protected function categoryMapRecursiveWalker($categories)
    {
        foreach ($categories as $category) {
            $this->addImportMap('Category', $category->oldId, $category->getId());
        }
    }

    protected function blockRecursiveWalker($page, $blocks, $parent = null)
    {
        foreach ($blocks as $block)
        {
            $block->setSite($page->getSite());

            // this should be solved be exluding in yml...
            if (null !== $block->getParent()) {
                $block->setParent(null);
            }
            if (null !== $parent) {
                $block->setParent($parent);
            }

            foreach ($block->getTranslations() as $translation) {
                $translation->setObject($block);

                // todo whats this?
                if ($translation->getLocale() === null) {
                    throw new \Exception('Unknown locale (ID: ' . $translation->getId() . ') for block ' . $block->getId());
                }

                $this->entityManager->persist($translation);
            }

            $this->blockRecursiveWalker($page, $block->getChildren(), $block);

            $this->entityManager->persist($block);
        }
    }

    protected function pageRecursiveWalker($site, $pages, $parent = null)
    {
        foreach ($pages as $page) {
            $page->oldId = $page->getId();

            $page->setSite($site);

            if (null !== $parent) {
                $page->setParent($parent);
            }

            // blocks
            $this->blockRecursiveWalker($page, $page->getBlocks());

            // translations
            foreach ($page->getTranslations() as $translation) {
                $translation->setObject($page);
            }

            // children
            $this->pageRecursiveWalker($site, $page->getChildren(), $page);

            $this->entityManager->persist($page);
        }
    }

    protected function pageMapRecursiveWalker($pages)
    {
        foreach ($pages as $page) {
            $this->addImportMap('Page', $page->oldId, $page->getId());
        }
    }
}