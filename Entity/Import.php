<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 01.12.15
 * Time: 11:03
 */

namespace Symbio\OrangeGate\ExportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symbio\OrangeGate\ExportBundle\Traits\TimestampableEntity;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 * @ORM\Table(name="import__import")
 */
class Import
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
     * @ORM\Column(type="text", length=32, nullable=false)
     */
    protected $status = 'new';

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="Map", mappedBy="import", cascade={"persist"})
     */
    protected $maps;

    /**
     * Import constructor.
     * @param int|null $id
     */
    public function __construct($id = null)
    {
        $this->id = $id;
        $this->maps = new ArrayCollection();
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
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getMaps()
    {
        return $this->maps;
    }

    /**
     * @param ArrayCollection $maps
     * @return $this
     */
    public function setMaps($maps)
    {
        $this->maps = $maps;
        return $this;
    }
}