<script setup lang="ts">
import { computed } from 'vue';
import { useProductStore } from '~/stores/products';
import type { Product, ProductId } from '~/types/product'; // Import the ProductId type

const productStore = useProductStore();
const toast = useToast();

const props = defineProps<{ id: ProductId; parentId?: ProductId }>(); // Accept product and parent IDs
const product = computed(() => productStore.getProductById(props.id, props.parentId));

// Save product action
const saveProduct = () => {
    if (!product.value) {
        console.error('Product not found');
        return;
    }

    const updates: Partial<Product> = {
        sku: product.value.sku,
        regular_price: product.value.regular_price,
        sale_price: product.value.sale_price,
        stock_quantity: product.value.stock_quantity || 0,
        meta_data: {
            _cogs_price: product.value.meta_data?._cogs_price || 0,
            _packing_cost: product.value.meta_data?._packing_cost || 0,
            _work_time_minutes: product.value.meta_data?._work_time_minutes || 0,
            _development_cost: product.value.meta_data?._development_cost || 0,
            _development_months: product.value.meta_data?._development_months || 0,
        },
    };

    productStore
        .updateProduct(props.id, updates, props.parentId)
        .then(() => {
            toast.add({
                title: 'Product Saved',
                description: `Product "${product.value?.name}" has been saved.`,
                color: 'success',
                icon: 'i-lucide-save',
            });
        })
        .catch((error) => {
            toast.add({
                title: 'Error Saving Product',
                description: `Failed to save product "${product.value?.name}".`,
                color: 'error',
                icon: 'i-lucide-alert-circle',
            });
            console.error('Error saving product:', error);
        });
};

// Utility function to strip HTML tags
function stripHtml(html: string): string {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    return doc.body.textContent || '';
}

// Edit product action
const editProduct = () => {
    if (!product.value) return;

    const wpProductEditorUrl = `/wp-admin/post.php?post=${product.value.id}&action=edit`;
    window.open(wpProductEditorUrl, '_blank');

    toast.add({
        title: 'Edit Product',
        description: `Opening editor for product "${product.value.name}".`,
        color: 'info',
        icon: 'i-lucide-edit',
    });

    console.log('Opened product editor for:', product.value);
};
</script>

<template>
    <div v-if="product" class="flex flex-col items-end gap-y-5">
        <!-- Call save and edit actions -->
        <!-- Save Button with Popover -->
        <UPopover mode="hover" :open-delay="300" :close-delay="300" variant="subtle" :content="{
            align: 'center',
            side: 'left',
            sideOffset: 8
        }">
            <UButton icon="i-lucide-save" color="success" @click="saveProduct" />
            <template #content>
                <div class="p-4 w-64 bg-gray-50 dark:bg-gray-800 rounded-md shadow-md">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2" v-html="product.name" />
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                        <span class="font-medium">ID:</span> {{ product.id }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                        <span class="font-medium">SKU:</span> {{ product.sku }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                        <span class="font-medium">Regular Price:</span> {{ product.regular_price }}
                    </p>
                    <p v-if="product.sale_price" class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                        <span class="font-medium">Sale Price:</span> {{ product.sale_price }}
                    </p>
                    <p v-if="product.manage_stock" class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                        <span class="font-medium">Stock:</span> {{ product.stock_quantity }}
                    </p>
                    <div v-if="product.meta_data" class="mt-2">
                        <h4 class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Meta Data:</h4>
                        <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                            <li><span class="font-medium">COGS:</span> {{ product.meta_data._cogs_price }}</li>
                            <li><span class="font-medium">Packing Cost:</span> {{ product.meta_data._packing_cost }}
                            </li>
                            <li><span class="font-medium">Work Time:</span> {{ product.meta_data._work_time_minutes }}
                                mins</li>
                            <li><span class="font-medium">Dev Cost:</span> {{ product.meta_data._development_cost }}
                            </li>
                            <li><span class="font-medium">Dev Months:</span> {{ product.meta_data._development_months }}
                            </li>
                        </ul>
                    </div>
                </div>
            </template>

        </UPopover>
        <UTooltip text="Open product edit page">
            <UButton icon="i-lucide-edit" color="info" @click="editProduct" />
        </UTooltip>
    </div>
    <div v-else class="text-gray-500">Product not found</div>
</template>
