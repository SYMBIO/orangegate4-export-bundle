<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 04.12.15
 * Time: 15:42
 */

namespace Symbio\OrangeGate\ExportBundle\Tests\Service;


use Symbio\OrangeGate\ExportBundle\Entity\Map;
use Symbio\OrangeGate\ExportBundle\Service\Deserializer;
use Symbio\OrangeGate\PageBundle\Entity\Site;
use Symbio\OrangeGate\PageBundle\Entity\LanguageVersion;
use JMS\Serializer\SerializerBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Symbio\OrangeGate\ClassificationBundle\Entity\Context;
use Symbio\OrangeGate\ClassificationBundle\Entity\Category;
use Symbio\OrangeGate\TranslationBundle\Entity\LanguageToken;
use Symbio\OrangeGate\TranslationBundle\Entity\LanguageTranslation;

class DeserializerTest extends \PHPUnit_Framework_TestCase
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
     * @var Deserializer
     */
    private $deserializer;

    public function testCreateSiteBasics()
    {
        $siteEntity = $this->getSiteEntity();
        $this->em->expects($this->once())->method('persist')->with($siteEntity);
        $this->em->expects($this->once())->method('flush');

        $siteJsonStr = '{"name":"\u010cesk\u00e1 vejce","enabled":true,"host":"localhost","relative_path":"\/ceska-vejce",'
            . '"is_default":false,"locale":"cs","language_versions":{"cs":{"enabled":true,"name":"\u010ce\u0161tina",'
            . '"host":"ceskavejce.agrofert.test.symbiodigital.com","is_default":true,"locale":"cs","id":5}},"formats":[],'
            . '"slug":"ceska-vejce"}';

        $this->assertEquals(
            $siteEntity,
            $this->deserializer->createSite($siteJsonStr)
        );
    }

    public function testLanguageVersionAnnotations()
    {
        $siteEntity = $this->getSiteEntity();

        $siteJsonStr = '{"enabled":true,"name":"\u010ce\u0161tina","host":"ceskavejce.agrofert.test.symbiodigital.com",'
            . '"is_default":true,"locale":"cs"}';

        $this->assertEquals(
            $siteEntity->getLanguageVersion('cs'),
            $this->serializer->deserialize($siteJsonStr, 'Symbio\OrangeGate\PageBundle\Entity\LanguageVersion', 'json')
        );
    }

    public function testCreateContextsForSite()
    {
        $siteEntity = $this->getSiteEntity();
        $contextEntity = $this->getContextEntity();

        $this->em->expects($this->once())->method('persist')->with($contextEntity);
        $this->em->expects($this->once())->method('flush');

        $contextJsonStr = '[{"name":"\u010cesk\u00e1 vejce","enabled":true,"id":"ceskavejce"}]';

        $this->assertEquals(
            [$contextEntity],
            $this->deserializer->createContextsForSite($contextJsonStr, $siteEntity)
        );

    }

    public function testCreateCategoryForSiteSimple()
    {
        $siteEntity = $this->getSiteEntity();
        $contextEntity = $this->getContextEntity();
        $categoryEntity = $this->getCategoryEntity();

        $repository = $this
            ->getMockBuilder('\Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository
            ->expects($this->never())->method('find')
        ;
        $repository
            ->expects($this->once())->method('findBy')
            ->with(['site' => $siteEntity])
            ->willReturn([$contextEntity])
        ;

        $this->em->expects($this->once())->method('getRepository')
            ->with('SymbioOrangeGateClassificationBundle:Context')
            ->willReturn($repository)
        ;
        $this->em->expects($this->exactly(2))->method('persist')
            ->withConsecutive([$categoryEntity], [new Map(null, 'Category', 166, 166, null)]);
        $this->em->expects($this->exactly(2))->method('flush');

        $testJson = '[{"name":"Soubory","slug":"soubory","enabled":true,"context":{"name":"\u010cesk\u00e1 vejce","enabled":true,"id":"ceskavejce"},"id":166}]';

        $this->assertEquals(
            [$categoryEntity],
            $this->deserializer->createCategoriesForSite($testJson, $siteEntity)
        );
    }

    public function testCreateStringsForSite()
    {
        $siteEntity = $this->getSiteEntity();
        $tokenEntity = $this->getTranslationEntity();
        $resEntity = clone $tokenEntity;
        $translationEntity = $resEntity->getTranslations()[0];
        $translationEntity->setLanguageToken($resEntity);

        $jsonStr = '[{"id":248,"token":"ceska-vejce.documents_certificates",'
            . '"translations":[{"id":363,"catalogue":"messages","translation":"Certifik\u00e1ty","language":"cs"}]}]'
        ;

        $this->em->expects($this->exactly(2))->method('persist');
//            ->withConsecutive([$tokenEntity], [$translationEntity]);
        $this->em->expects($this->exactly(2))->method('flush');


        $this->assertEquals(
            [$resEntity],
            $this->deserializer->createStringsForSite($jsonStr, $siteEntity)
        );
    }

    /**
     * @depends testCreateCategoryForSiteSimple
     */
    public function testCreateCategoryForSiteTree()
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    protected function setUp()
    {
        $this->serializer = SerializerBuilder::create()
            ->addMetadataDir(__DIR__ . '/../../Resources/serializer')
            ->build()
        ;

        $this->em = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->deserializer = new Deserializer($this->serializer, $this->em);
    }


    private function getSiteEntity() {
        $site = new Site();
        $site->setName('Česká vejce');
        $site->setHost('localhost');
        $site->setRelativePath('/ceska-vejce');
        $site->setIsDefault(false);
        $site->setLocale('cs');
        $site->setSlug('ceska-vejce');

        $lv = new LanguageVersion();
        $lv->setName('Čeština');
        $lv->setHost('ceskavejce.agrofert.test.symbiodigital.com');
        $lv->setLocale('cs');
        $lv->setIsDefault(true);

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

        return $context;
    }


    private function getCategoryEntity()
    {
        $category = new Category();
        $category->setId(166);
        $category->setSlug('soubory');
        $category->setName('Soubory');
        $category->setContext($this->getContextEntity());

        return $category;
    }

    private function getTranslationEntity()
    {
        $token = new LanguageToken();
        $translation = new LanguageTranslation();

        $translation->setCatalogue('messages');
        $translation->setTranslation('Certifikáty');
        $translation->setLanguage('cs');
//        $token->setId(248);
        $token->setToken('ceska-vejce.documents_certificates');
        $token->addTranslation($translation);
        $token->setSite($this->getSiteEntity());

        return $token;
    }
}
