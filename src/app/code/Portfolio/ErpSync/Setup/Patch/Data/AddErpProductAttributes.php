<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddErpProductAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(Product::ENTITY, 'erp_brand', [
            'type' => 'varchar',
            'label' => 'ERP Brand',
            'input' => 'text',
            'required' => false,
            'sort_order' => 80,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'user_defined' => false,
            'searchable' => true,
            'filterable' => true,
            'comparable' => true,
            'visible_on_front' => true,
            'used_in_product_listing' => true,
            'group' => 'General',
        ]);

        $eavSetup->addAttribute(Product::ENTITY, 'erp_source_updated_at', [
            'type' => 'varchar',
            'label' => 'ERP Source Updated At',
            'input' => 'text',
            'required' => false,
            'sort_order' => 90,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'user_defined' => false,
            'searchable' => false,
            'filterable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'group' => 'General',
        ]);

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @return array<class-string>
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
