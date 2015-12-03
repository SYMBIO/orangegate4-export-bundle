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
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
    }


    public function exportSite($site)
    {
        $site = $this->getSite($site);

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
        throw new \Exception('Not implemented yet');
    }

    public function exportStringsForSite($site)
    {
        throw new \Exception('Not implemented yet');
    }

    public function exportContextsForSite($site)
    {
        throw new \Exception('Not implemented yet');
    }

    public function exportCategoriesForSite($site)
    {
        throw new \Exception('Not implemented yet');
    }

    public function exportMediaForSite($site)
    {
        throw new \Exception('Not implemented yet');
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
}