<?php
/**
 * Created by PhpStorm.
 * User: jiri.bazant
 * Date: 04.12.15
 * Time: 15:42
 */

namespace Symbio\OrangeGate\ExportBundle\Tests\Service;


use Symbio\OrangeGate\ExportBundle\Entity\Import;
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
use Symbio\OrangeGate\MediaBundle\Entity\Gallery;
use Symbio\OrangeGate\MediaBundle\Entity\GalleryTranslation;

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
        $resCategory = clone $categoryEntity;
        $resCategory->oldId = $resCategory->getId();

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
            ->withConsecutive([$resCategory], [new Map(null, 'Category', 166, 166, null)]);
        $this->em->expects($this->exactly(2))->method('flush');

        $testJson = '[{"name":"Soubory","slug":"soubory","enabled":true,"context":{"name":"\u010cesk\u00e1 vejce","enabled":true,"id":"ceskavejce"},"id":166}]';

        $this->assertEquals(
            [$resCategory],
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
        $this->em->expects($this->exactly(1))->method('flush');


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
        // todo test import of whole category tree structure

        $this->markTestIncomplete('Not implemented yet');
    }

    public function testCreateGalleryForSite()
    {
        $siteEntity = $this->getSiteEntity();
        $galEntity = $this->getGalleryEntity();
        $galEntity->setSite($siteEntity);
        $galEntity->oldId = 11;

        $jsonStr = '[{"id":11,"name":"testovaci galerie","gallery_has_medias":[],"slug":"testovaci-galerie","enabled":true,'
            . '"translations":{"cs":{"id":5,"locale":"cs","name":"testovaci galerie","slug":"testovaci-galerie"}}}]';

        $this->em->expects($this->exactly(2))->method('persist');
//        tohle nevim proc nefunguje (hlavne pro klic 0)
//        ->withConsecutive(
//            [$galEntityIncomplete],
//            [new Map(null, 'Gallery', $galEntity->getId(), $galEntity->getId(), null)],
//            [$galEntity->getTranslations()[0]]
//        );

        $this->assertEquals(
            [$galEntity],
            $this->deserializer->createGalleryForSite($jsonStr, $siteEntity)
        );
    }

    public function testCreatePagesForSite()
    {
        $siteEntity = $this->getSiteEntity();

        $jsonStr = '{"0":{"id":743,"route_name":"page_slug","request_method":"GET|POST|HEAD|DELETE|PUT","template_code":"subcompany_common",'
            . '"position":1,"decorate":true,"edited":false,"enabled":true,"name":"\u00davod","url":"\/","children":[{"id":889,'
            . '"route_name":"page_slug","request_method":"GET|POST|HEAD|DELETE|PUT","template_code":"subcompany_common","position":1,'
            . '"decorate":true,"edited":false,"enabled":true,"name":"Ke sta\u017een\u00ed","slug":"ke-stazeni","url":"\/ke-stazeni",'
            . '"children":[],"sources":[],"blocks":[],"snapshots":[],"translations":{"cs":{"id":811,"locale":"cs","enabled":true,'
            . '"name":"Ke sta\u017een\u00ed","slug":"ke-stazeni","url":"\/ke-stazeni"}},"slugify_method":{}}],"sources":[],'
            . '"blocks":[{"settings":{"code":"header"},"name":"header","enabled":true,"position":1,"type":"sonata.page.block.container",'
            . '"id":1569,"children":[{"settings":{"buttonText":"V\u00edce o firm\u011b","buttonLink":"\/o-spolecnosti","companyId":283,'
            . '"companyDescription":"company_description","template":"AgrofertSubcompanyBundle:Block:homepage-header.html.twig"},"name":"block_name",'
            . '"enabled":true,"position":1,"type":"subcompany.block.service.homepage.header","id":1684,"children":[],"translations":{'
            . '"cs":{"id":3154,"locale":"cs","settings":{"buttonText":"V\u00edce o firm\u011b","buttonLink":"\/o-spolecnosti",'
            . '"companyId":283,"companyDescription":"company_description","template":"AgrofertSubcompanyBundle:Block:homepage-header.html.twig"},'
            . '"enabled":true}}}],"translations":{"cs":{"id":3025,"locale":"cs","settings":{"code":"header"},"enabled":true}}}],'
            . '"snapshots":[],"translations":{"cs":{"id":678,"locale":"cs","enabled":true,"name":"\u00davod","url": "\/"}},'
            . '"slugify_method":{}}}';

        // todo tests, expectations
        $this->deserializer->createPagesForSite($jsonStr, $siteEntity);
    }

    public function testCreateMediasForSite()
    {
        $siteEntity = $this->getSiteEntity();
        $jsonStr = '[{"provider_metadata":{"filename":"m6.jpg"},"name":"m6.jpg","enabled":true,"provider_name":"sonata.media.provider.image",'
            . '"provider_status":1,"provider_reference":"2b54e201a18f8d7f94e025853085a0a18b452e9a.jpeg","width":640,'
            . '"height":480,"context":"subcompany_product","content_type":"image\/jpeg","size":86447,"id":4193,'
            . '"gallery_has_medias":[],"category":{"name":"subcompany_product","slug":"subcompany-product","enabled":true,'
            . '"description":"subcompany_product","created_at":"2015-10-29T09:57:54+0100","updated_at":"2015-10-29T09:57:54+0100",'
            . '"context":{"name":"subcompany_product","enabled":true,"id":"subcompany_product"},"id":165}}]';

        $repository = $this
            ->getMockBuilder('\Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository
            ->expects($this->once())->method('findOneBy')
            ->with([
                'entity' => 'Category',
                'oldId' => 165,
                'import' => null
            ])
            ->willReturn(new Map(1, 'Category', 165, 166, null))
        ;
        $repository
            ->expects($this->once())->method('find')
            ->with(166)
            ->willReturn($this->getCategoryEntity())
        ;
        $this->em->expects($this->exactly(2))
            ->method('getRepository')
            ->withConsecutive(['SymbioOrangeGateExportBundle:Map'], ['SymbioOrangeGateClassificationBundle:Category'])
            ->willReturn($repository)
        ;

        // todo expectations

        // todo assert return

        $this->deserializer->createMediasForSite($jsonStr, $siteEntity);
    }



    protected function setUp()
    {
        $this->serializer = SerializerBuilder::create()
            ->addMetadataDir(__DIR__ . '/../../Resources/config/serializer')
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

    private function getGalleryEntity()
    {
        $galTrans = new GalleryTranslation();
        $galTrans->setName('testovaci galerie');
        $galTrans->setSlug('testovaci-galerie');
        $galTrans->setLocale('cs');

        $gallery = new Gallery();
        $gallery->setId(11);
        $gallery->setName('testovaci galerie');
        $gallery->setSlug('testovaci-galerie');
        $gallery->setEnabled(true);
        $gallery->addTranslation($galTrans);

        return $gallery;
    }
}
