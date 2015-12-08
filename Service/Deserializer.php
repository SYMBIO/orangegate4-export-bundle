<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 04.12.15
 * Time: 15:18
 */

namespace Symbio\OrangeGate\ExportBundle\Service;

use Symbio\OrangeGate\ExportBundle\Entity\Map;
use Symbio\OrangeGate\ExportBundle\Exception\InvalidArgumentException;
use Symbio\OrangeGate\PageBundle\Entity\Site;
use JMS\Serializer\SerializerBuilder;
use Symbio\OrangeGate\ExportBundle\Entity\Import;

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
                ->addMetadataDir(__DIR__ . '/../Resources/serializer')
                ->build()
            ;
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

    /**
     * @param null|Import $import
     * @return $this
     */
    public function setImport($import)
    {
        $this->import = $import;
        return $this;
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
            $this->entityManager->flush();

            foreach ($string->getTranslations() as $translation) {
                $translation->setLanguageToken($string);
                $this->entityManager->persist($translation);
            }

            $this->entityManager->flush();
        }

        return $strings;
    }

    protected function addImportMap($objectName, $oldId, $newId)
    {
        $importMap = new Map(null, $objectName, $oldId, $newId, $this->import);
        $this->entityManager->persist($importMap);
        $this->entityManager->flush();
    }

    protected function categoryRecursiveWalker($categories, &$contextMap, $parent = null)
    {
        foreach ($categories as $category) {
            $category->setContext($contextMap[$category->getContext()->getId()]);

            $oldId = $category->getId();

            if (null !== $parent) {
                $category->setParent($parent);
            }

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->addImportMap('Category', $oldId, $category->getId());

            $children = $category->getChildren();

            if (null !== $children) {
                $this->categoryRecursiveWalker($children, $contextMap, $category);
            }
        }
    }
}