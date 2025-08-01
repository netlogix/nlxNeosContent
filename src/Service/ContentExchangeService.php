<?php

declare(strict_types=1);

namespace netlogixNeosContent\Service;

use DateTime;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use netlogixNeosContent\Error\NeosContentFetchException;
use Psr\Http\Client\ClientInterface;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

class ContentExchangeService
{
    //FIXME use try catch for all type checks
    public function __construct(
        #[Autowire(service: 'serializer')]
        private readonly SerializerInterface $serializer,
        private readonly CmsSlotsDataResolver $cmsSlotsDataResolver,
        private readonly ClientInterface $neosClient,
    ) {
    }

    /**
     * @throws GuzzleException
     */
    public function getAlternativeCmsBlocksFromNeos(
        string $cmsSectionId,
        string $nodeIdentifier,
        string $dimensions,
    ): CmsBlockCollection {
        $elements = $this->fetchNeosContentByNodeIdentifierAndDimension(
            $nodeIdentifier,
            $dimensions
        );

        $blocks = $this->serializer->decode($elements, 'json');

        $cmsBlockCollection = new CmsBlockCollection();
        $blockPosition = 0;
        foreach ($blocks as $blockData) {
            $cmsBlock = $this->createCmsBlockFromNeosElement($blockData, $blockPosition, $cmsSectionId);
            if ($cmsBlock === null) {
                continue;
            }

            $cmsBlock->setCreatedAt(new DateTime());
            $cmsBlock->setSectionPosition('main');
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

    public function createCmsBlockFromNeosElement(array $blockData, int $position, string $sectionId): ?CmsBlockEntity
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
        $cmsBlock->internalSetEntityData('cms_block', $fieldVisibility);
        $cmsBlock->setId($blockId);

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

            //FIXME fieldConfig "content" is not set which leads to some values not beeing resolved

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
     * @throws GuzzleException
     */
    private function fetchNeosContentByNodeIdentifierAndDimension(string $nodeIdentifier, string $dimension): string
    {
        try {
            $response = $this->neosClient->get(sprintf("/shopware-api/content/%s__%s/", $nodeIdentifier, $dimension));
        } catch (RequestException $e) {
            throw new NeosContentFetchException (
                sprintf('Failed to fetch content from Neos for node identifier "%s" and dimension "%s": %s', $nodeIdentifier, $dimension, $e->getMessage()),
                1752652016,
                $e
            );
        }

        return $response->getBody()->getContents();
    }

    /**
     * @param string $jsonContent
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
}
