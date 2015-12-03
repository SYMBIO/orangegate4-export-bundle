<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 02.12.15
 * Time: 12:33
 */
namespace Symbio\OrangeGate\ExportBundle\Tests\Service;

use Symbio\OrangeGate\ExportBundle\Service\Exporter;
use Symbio\OrangeGate\PageBundle\Entity\Site;
use JMS\Serializer\SerializerBuilder;
use Symbio\OrangeGate\ExportBundle\Exception\InvalidArgumentException;
use Symbio\OrangeGate\PageBundle\Entity\LanguageVersion;


class ExporterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \JMS\Serializer\Serializer $serializer
     */
    private $serializer;

    /**
     * @var \Doctrine\ORM\EntityManager|Mock $entityManager
     */
    private $em;

    /**
     * @var Exporter
     */
    private $exporter;


    public function testSiteIdArgument()
    {
        $repository = $this
            ->getMockBuilder('\Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository
            ->expects($this->exactly(1))
            ->method('find')
            ->willReturn(new Site());

        $this->setEmGetRepository($repository);

        $this->exporter->exportSite(1);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $site must be id of existing site or Symbio\OrangeGate\PageBundle\Entity\Site instance.
     */
    public function testInvalidSiteIdArgument()
    {
        $repository = $this
            ->getMockBuilder('\Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository
            ->expects($this->exactly(1))
            ->method('find')
            ->willReturn(null);

        $this->setEmGetRepository($repository);

        $this->exporter->exportSite(1);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $site must be id of existing site or Symbio\OrangeGate\PageBundle\Entity\Site instance.
     */
    public function testInvalidSiteArgument()
    {
        $repository = $this
            ->getMockBuilder('\Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository
            ->expects($this->never())->method('find');

        $this->setEmGetRepository($repository);

        $this->exporter->exportSite('string');

    }

    public function testExportSite()
    {
        $site = new Site();
        $site->setId(8);
        $site->setName('Česká vejce');
        $site->setHost('localhost');
        $site->setRelativePath('/ceska-vejce');
        $site->setIsDefault(false);
        $site->setLocale('cs');
        $site->setSlug('ceska-vejce');
        $site->setCreatedAt(new \DateTime('2015-10-15 17:14:22'));
        $site->setUpdatedAt(new \DateTime('2015-10-29 10:20:13'));
        //$site->setFormats([]);

        $lv = new LanguageVersion();
        $lv->setId(5);
        $lv->setSite($site);
        $lv->setName('Čeština');
        $lv->setHost('ceskavejce.agrofert.test.symbiodigital.com');
        $lv->setLocale('cs');
        $lv->setIsDefault(true);
        $lv->setCreatedAt(new \DateTime('2015-10-15 17:14:22'));
        $lv->setUpdatedAt(new \DateTime('2015-11-23 10:46:57'));

        $site->addLanguageVersion($lv);

        $this->assertEquals(
            '{"enabled":true,"name":"\u010cesk\u00e1 vejce","host":"localhost","relative_path":"\/ceska-vejce","is_default":false,"formats":[],'
            . '"locale":"cs","id":8,"language_versions":{"cs":{"enabled":true,"name":"\u010ce\u0161tina","host":"ceskavejce.agrofert.test.symbiodigital.com",'
            . '"is_default":true,formats:[],"locale":"cs","id":5}},"slug":"ceska-vejce"}',
            $this->exporter->exportSite($site)
        );
    }

    public function testExportPages()
    {
        $this->markTestIncomplete('Not implemented yet');
    }


    private function setEmGetRepository($repository)
    {
        $this->em->method('getRepository')->willReturn($repository);

    }

    protected function setUp()
    {
        $this->serializer = SerializerBuilder::create()->build();

        $this->em = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->exporter = new Exporter($this->serializer, $this->em);
    }

}
