<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 01.12.15
 * Time: 10:42
 */

namespace Symbio\OrangeGate\ExportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symbio\OrangeGate\ExportBundle\Traits\TimestampableEntity;

/**
 * @ORM\Entity
 * @ORM\Table(name="import__map")
 */
class Map
{
    use TimestampableEntity;

    /**
     * @var integer
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column(type="text", length=127, nullable=false)
     */
    protected $entity;

    /**
     * @var int
     * @ORM\Column(type="integer", name="old_id", nullable=false)
     */
    protected $oldId;

    /**
     * @var int
     * @ORM\Column(type="integer", name="new_id", nullable=false)
     */
    protected $newId;

    /**
     * @var Import
     * @ORM\ManyToOne(targetEntity="Import", inversedBy="maps")
     * @ORM\JoinColumn(name="import_id", referencedColumnName="id", nullable=false)
     */
    protected $import;

    /**
     * Map constructor.
     * @param int $id
     * @param $entity
     * @param $oldId
     * @param $newId
     * @param $import
     */
    public function __construct($id, $entity, $oldId, $newId, $import)
    {
        $this->id = $id;
        $this->entity = $entity;
        $this->oldId = $oldId;
        $this->newId = $newId;
        $this->import = $import;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @param string $entity
     * @return $this
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * @return int
     */
    public function getOldId()
    {
        return $this->oldId;
    }

    /**
     * @param int $oldId
     * @return $this
     */
    public function setOldId($oldId)
    {
        $this->oldId = $oldId;
        return $this;
    }

    /**
     * @return int
     */
    public function getNewId()
    {
        return $this->newId;
    }

    /**
     * @param int $newId
     * @return $this
     */
    public function setNewId($newId)
    {
        $this->newId = $newId;
        return $this;
    }

    /**
     * @return Import
     */
    public function getImport()
    {
        return $this->import;
    }

    /**
     * @param Import $import
     * @return $this
     */
    public function setImport($import)
    {
        $this->import = $import;
        return $this;
    }


}