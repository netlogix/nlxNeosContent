<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use DateTime;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use nlxNeosContent\Error\FaultyNeosSectionDataException;
use nlxNeosContent\Error\NeosContentFetchException;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

class ContentExchangeService
{
    public function __construct(
        #[Autowire(service: 'serializer')]
        private readonly SerializerInterface $serializer,
        private readonly CmsSlotsDataResolver $cmsSlotsDataResolver,
        private readonly ClientInterface $neosClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws GuzzleException
     */
    public function getAlternativeCmsSectionsFromNeos(
        string $nodeIdentifier,
        string $languageId,
        string $salesChannelId
    ): CmsSectionCollection {
        $elements = $this->fetchNeosContentByNodeIdentifierAndDimension(
            $nodeIdentifier,
            $languageId,
            $salesChannelId
        );

        return $this->createCmsSectionFromElementsArray($elements);
    }

    public function fetchCmsSectionsFromNeosByPath(string $pathInfo, SalesChannelContext $salesChannelContext): CmsSectionCollection
    {
        $response = $this->neosClient->get(sprintf("/neos/shopware-api/content-by-path/%s", trim($pathInfo, '/')), [
            'headers' => [
                'x-sw-language-id' => $salesChannelContext->getLanguageId(),
                'x-sw-sales-channel-id' => $salesChannelContext->getSalesChannelId(),
            ]
        ]);

        return $this->createCmsSectionFromElementsArray($response->getBody()->getContents());
    }

    private function createCmsSectionFromElementsArray(string $elements): CmsSectionCollection
    {
#        return $this->serializer->denormalize($elements, CmsSectionCollection::class,'json');

        $sections = $this->serializer->decode($elements, 'json');
        $cmsSectionCollection = new CmsSectionCollection();
        $position = 0;

        foreach ($sections as $sectionData) {
            if (!is_array($sectionData)) {
                // Log error and continue with next section to prevent breaking the storefront
                $this->logger->error(
                    new FaultyNeosSectionDataException(
                        message: "Provided section data from Neos is not an array",
                        code: 1763629733,
                        previous: null,
                        sectionData: $sectionData
                    )
                );
                continue;
            }
            $cmsSection = $this->createCmsSectionFromSectionData($sectionData, $position, Uuid::randomHex());
            if ($cmsSection === null) {
                continue;
            }

            $cmsSectionCollection->add($cmsSection);
            $position++;
        }

        return $cmsSectionCollection;
    }

    public function createCmsSectionFromSectionData(
        array $sectionData,
        int $position,
        string $sectionId
    ): ?CmsSectionEntity {
        if (!isset($sectionData['type'])) {
            throw new \RuntimeException('Section type is not set');
        }

        $cmsSection = new CmsSectionEntity();
        $fieldVisibility = new FieldVisibility([

        ]);
        $cmsSection->setType($sectionData['type']);
        $cmsSection->setPosition($position);
        $cmsSection->setVersionId(DEFAULTS::LIVE_VERSION);
        $cmsSection->setSizingMode($sectionData['sizingMode'] ?? 'boxed');
        $cmsSection->setMobileBehavior($sectionData['mobileBehavior'] ?? 'wrap');
        $cmsSection->setBackgroundColor($sectionData['backgroundColor'] ?? '');
        $cmsSection->setBackgroundMediaMode($sectionData['backgroundMediaMode'] ?? 'cover');
        $cmsSection->setId($sectionId);
        $cmsSection->internalSetEntityData('cms_section', $fieldVisibility);
        $cmsSection->setBlocks($this->blockCollectionFromArray($sectionData['blocks'] ?? [], $sectionId));

        return $cmsSection;
    }

    public function blockCollectionFromArray(array $blocks, string $cmsSectionId): CmsBlockCollection
    {
        $cmsBlockCollection = new CmsBlockCollection();
        $blockPosition = 0;
        foreach ($blocks as $blockData) {
            $cmsBlock = $this->createCmsBlockFromBlockData($blockData, $blockPosition, $cmsSectionId);
            if ($cmsBlock === null) {
                continue;
            }

            $cmsBlock->setCreatedAt(new DateTime());
            $cmsBlock->setVisibility([
                'mobile' => true,
                'desktop' => true,
                'tablet' => true
            ]);
            $cmsBlock->setLocked(true);
            $cmsBlock->setCmsSectionVersionId(DEFAULTS::LIVE_VERSION);

            $cmsBlockCollection->add($cmsBlock);
            $blockPosition++;
        }

        return $cmsBlockCollection;
    }

    public function createCmsBlockFromBlockData(array $blockData, int $position, string $sectionId): ?CmsBlockEntity
    {
        if (!isset($blockData['type'])) {
            throw new \RuntimeException('Block type is not set');
        }

        $cmsBlock = new CmsBlockEntity();
        $fieldVisibility = new FieldVisibility([

        ]);
        $blockId = $blockData['id'] ?? Uuid::randomHex();

        $cmsBlock->setType($blockData['type']);
        $cmsBlock->setPosition($position);
        $cmsBlock->setVersionId(DEFAULTS::LIVE_VERSION);
        $cmsBlock->setSectionId($sectionId);
        $cmsBlock->setSectionPosition($blockData['sectionPosition'] ?? 'main');
        $cmsBlock->internalSetEntityData('cms_block', $fieldVisibility);
        $cmsBlock->setId($blockId);
        if (array_key_exists('hiddenEditorRendering', $blockData)) {
            $cmsBlock->setCustomFields(['hiddenEditorRendering' => $blockData['hiddenEditorRendering']]);
        }
        $cmsBlock->setCssClass($blockData['cssClass'] ?? '');

        $slots = $this->slotCollectionFromArray($blockData['slots'] ?? [], $blockId);
        $cmsBlock->setSlots($slots);

        return $cmsBlock;
    }

    private function slotCollectionFromArray(array $slots, string $blockId): CmsSlotCollection
    {
        $cmsSlotCollection = new CmsSlotCollection();
        foreach ($slots as $slot) {
            if (!isset($slot['type'])) {
                throw new \RuntimeException('Slot type is not set');
            }

            $fieldVisibility = new FieldVisibility([]);
            $cmsSlot = $this->deserializeEntityFromJson($slot['data'], CmsSlotEntity::class);
            $cmsSlot->setType($slot['type']);
            $cmsSlot->setVersionId(Defaults::LIVE_VERSION);
            $cmsSlot->setId($slot['id'] ?? Uuid::randomHex());
            $cmsSlot->setSlot($slot['slot'] ?? 'content');
            $cmsSlot->setConfig($slot['config'] ?? []);
            $cmsSlot->setTranslated($this->getTranslatedConfigFromConfig($slot['config'] ?? []));
            $cmsSlot->setBlockId($blockId);
            $cmsSlot->internalSetEntityData('cms_slot', $fieldVisibility);

            if (count($slot['fieldConfig']) > 0) {
                $cmsSlot->setFieldConfig($this->fieldConfigCollectionFromArray($slot['fieldConfig'] ?? []));
            }

            $cmsSlotCollection->add($cmsSlot);
        }

        return $cmsSlotCollection;
    }

    private function fieldConfigCollectionFromArray(array $data): FieldConfigCollection
    {
        $fieldConfigCollection = new FieldConfigCollection();
        foreach ($data as $fieldConfigData) {
            $fieldConfig = new FieldConfig(
                $fieldConfigData['name'],
                $fieldConfigData['source'],
                $fieldConfigData['value']
            );
            $fieldConfigCollection->add($fieldConfig);
        }

        return $fieldConfigCollection;
    }

    /**
     * @throws NeosContentFetchException
     */
    private function fetchNeosContentByNodeIdentifierAndDimension(
        string $nodeIdentifier,
        string $languageId,
        string $salesChannelId
    ): string {
        try {
            $response = $this->neosClient->get(sprintf("/neos/shopware-api/content/%s/", $nodeIdentifier), [
                'headers' => [
                    'x-sw-language-id' => $languageId,
                    'x-sw-sales-channel-id' => $salesChannelId,
                ]
            ]);
        } catch (RequestException $e) {
            throw new NeosContentFetchException (
                sprintf(
                    'Failed to fetch content from Neos for node identifier "%s" for sales channel "%s" and language "%s": %s',
                    $nodeIdentifier,
                    $salesChannelId,
                    $languageId,
                    $e->getMessage()
                ),
                1752652016,
                $e
            );
        }

        return $response->getBody()->getContents();
    }

    /**
     * @param class-string<T> $classString
     *
     * @template T of Struct
     * @return Struct|T
     */
    private function deserializeEntityFromJson(array $data, string $classString): Struct
    {
        $data['_class'] = $classString;
        return $this->serializer->denormalize($data, $classString, 'json');
    }

    public function loadSlotData(CmsBlockCollection $blocks, ResolverContext $resolverContext): void
    {
        $slots = $this->cmsSlotsDataResolver->resolve($blocks->getSlots(), $resolverContext);

        $blocks->setSlots($slots);
    }

    private function getTranslatedConfigFromConfig(array $config): array
    {
        return [
            "config" => $config,
        ];
    }

    private function resolveDimensionString(Struct $dimensions): string
    {
        return implode('__', $dimensions->getVars());
    }
}
