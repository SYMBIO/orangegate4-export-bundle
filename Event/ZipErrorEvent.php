<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 03.12.15
 * Time: 9:48
 */

namespace Symbio\OrangeGate\ExportBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class ZipErrorEvent extends Event
{
    /**
     * @var string
     */
    private $message;

    /**
     * ExportEvent constructor.
     * @param string $message
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
}