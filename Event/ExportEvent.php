<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 03.12.15
 * Time: 9:48
 */

namespace Symbio\OrangeGate\ExportBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class ExportEvent extends Event
{
    /**
     * @var string
     */
    private $action;
    /**
     * ExportEvent constructor.
     * @param string $action
     */
    public function __construct($action)
    {
        $this->action = $action;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }
}