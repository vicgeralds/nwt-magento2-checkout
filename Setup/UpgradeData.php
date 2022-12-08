<?php

namespace Svea\Checkout\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Eav\Setup\EavSetupFactory;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var AttributeRepositoryInterface
     */
    private AttributeRepositoryInterface $attributeRepo;

    /**
     * @var EavSetupFactory
     */
    private EavSetupFactory $eavSetupFactory;

    public function __construct(
        AttributeRepositoryInterface $attributeRepo,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->attributeRepo = $attributeRepo;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '1.1.2', '<')) {
            $this->addDimensionAttributes();
        }
    }

    /**
     * Creates dimension attributes if they don't exist
     *
     * @return void
     */
    private function addDimensionAttributes()
    {
        $attrs = [
            ['code' => 'height_cm', 'label' => 'Packaging height in cm'],
            ['code' => 'length_cm', 'label' => 'Packaging length in cm'],
            ['code' => 'width_cm', 'label' => 'Packaging width in cm']
        ];

        $eavSetup = $this->eavSetupFactory->create();

        // Create the attributes if they don't exist yet
        foreach ($attrs as $attr) {
            try {
                $this->attributeRepo->get(Product::ENTITY, $attr['code']);
            } catch (NoSuchEntityException $e) {
                $eavSetup->addAttribute(
                    Product::ENTITY,
                    $attr['code'],
                    [
                        'group' => 'General',
                        'label' => $attr['label'],
                        'type' => 'decimal',
                        'input' => 'text',
                        'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                        'visible' => true,
                        'required' => false,
                        'user_defined' => false,
                        'apply_to' => \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE,
                        'visible_on_front' => false,
                        'used_in_product_listing' => false,
                        'is_used_in_grid' => false,
                        'is_visible_in_grid' => false,
                        'is_filterable_in_grid' => false,
                    ]
                );
            }
        }
    }
}
