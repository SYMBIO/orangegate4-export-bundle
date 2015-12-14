<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 02.12.15
 * Time: 12:33
 */
namespace Symbio\OrangeGate\ExportBundle\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Symbio\OrangeGate\ExportBundle\Service\Serializer;
use Symbio\OrangeGate\PageBundle\Entity\Site;
use Symbio\OrangeGate\ClassificationBundle\Entity\Context;
use Symbio\OrangeGate\ClassificationBundle\Entity\Category;
use Symbio\OrangeGate\PageBundle\Entity\Page;
use Symbio\OrangeGate\PageBundle\Entity\Block;
use JMS\Serializer\SerializerBuilder;
use Symbio\OrangeGate\ExportBundle\Exception\InvalidArgumentException;
use Symbio\OrangeGate\PageBundle\Entity\LanguageVersion;


class SerializerTest extends \PHPUnit_Framework_TestCase
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
     * @var Serializer
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
        $site = $this->getSiteEntity();

        $this->assertEquals(
            '{"enabled":true,"name":"\u010cesk\u00e1 vejce","host":"localhost","relative_path":"\/ceska-vejce","is_default":false,"formats":[],'
            . '"locale":"cs","language_versions":{"cs":{"enabled":true,"name":"\u010ce\u0161tina","host":"ceskavejce.agrofert.test.symbiodigital.com",'
            . '"is_default":true,"formats":[],"locale":"cs"}},"slug":"ceska-vejce"}',
            $this->exporter->exportSite($site)
        );
    }

    public function testExportContexts()
    {
        $site = $this->getSiteEntity();

        $repository = $this
            ->getMockBuilder('\Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository
            ->expects($this->never())->method('find')
        ;
        $repository
            ->expects($this->once())->method('findBy')
            ->with(['site' => $site])
            ->willReturn([$this->getContextEntity()])
        ;

        $this->setEmGetRepository($repository);

        $this->assertEquals(
            '[{"name":"\u010cesk\u00e1 vejce","enabled":true,"id":"ceskavejce"}]',
            $this->exporter->exportContextsForSite($site)
        );
    }

    public function testExportCategories()
    {
        $site = $this->getSiteEntity();
        $context = $this->getContextEntity();

        $repository = $this
            ->getMockBuilder('\Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository
            ->expects($this->never())->method('find')
        ;
        $repository
            ->expects($this->exactly(2))->method('findBy')
            ->withConsecutive(
                [['site' => $site]],
                [['context' => [$context]]]
            )
            ->willReturnOnConsecutiveCalls([$context], [$this->getCategoryEntity()])
        ;

        $this->setEmGetRepository($repository);

        $this->assertEquals(
            '[{"name":"Soubory","slug":"soubory","enabled":true,"context":{"name":"\u010cesk\u00e1 vejce","enabled":true,"id":"ceskavejce"},"id":166}]',
            $this->exporter->exportCategoriesForSite($site)
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
        $this->serializer = SerializerBuilder::create()
            ->addMetadataDir(__DIR__ . '/../../Resources/conifg/serializer')
            ->build();

        $this->em = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->exporter = new Serializer($this->serializer, $this->em);
    }

    private function getSiteEntity() {
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
//        $site->setFormats(new ArrayCollection());

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

        return $site;
    }

    private function getContextEntity()
    {
        $context = new Context();
        $context->setId('ceskavejce');
        $context->setEnabled(true);
        $context->setName('Česká vejce');
        $context->setSite($this->getSiteEntity());
        $context->setCreatedAt(new \DateTime('2015-10-15 17:14:22'));
        $context->setUpdatedAt(new \DateTime('2015-11-23 10:46:57'));

        return $context;
    }

    private function getCategoryEntity()
    {
        $category = new Category();
        $category->setId(166);
        $category->setCreatedAt(new \DateTime('2015-10-15 17:14:22'));
        $category->setUpdatedAt(new \DateTime('2015-11-23 10:46:57'));
        $category->setSlug('soubory');
        $category->setName('Soubory');
        $category->setContext($this->getContextEntity());

        return $category;
    }

    private function getRootPageEntity()
    {
        //todo
    }
}
