<script setup lang="ts">
import { computed } from 'vue';
import { useProductStore } from '~/stores/products';
import type { ProductId } from '~/types/product';

const productStore = useProductStore();

const props = defineProps<{ id: ProductId; parentId?: ProductId }>(); // Accept product and parent IDs

const product = computed(() => productStore.getProductById(props.id, props.parentId));

const getTypeLabel = computed(() => {
    if (!product.value) return 'Unknown'; // Fallback if product is not found
    if (props.parentId) return 'Variation';
    if (product.value.type === 'Woosb') return 'Bundle';
    return product.value.type === 'Variable' ? 'Parent' : 'Simple';
});

const productSalePrice = computed({
    get() {
        return product.value?.sale_price ?? undefined; // Safely access product.value
    },
    set(value: string | number | undefined) {
        if (product.value) {
            product.value.sale_price = value !== undefined ? Number(value) : null; // Safely set sale_price
        }
    },
});

</script>

<template>
    <div v-if="product" class="grid grid-cols-4 gap-4">
        <!-- Regular Price -->
        <div>
            <div v-if="getTypeLabel !== 'Parent'">
                <label class="text-xs text-gray-500 mb-0 block">Regular Price</label>
                <UInput v-model="product.regular_price" icon="i-lucide-euro" size="md" variant="outline"
                    placeholder="Regular Price..." />
            </div>
        </div>

        <!-- Stock -->
        <div>
            <div v-if="product.manage_stock && getTypeLabel !== 'Bundle'">
                <label class="text-xs text-gray-500 mb-0 block">Stock</label>
                <UInput v-model="product.stock_quantity" icon="i-lucide-layers" size="md" variant="outline"
                    placeholder="Stock..." />
            </div>
        </div>

        <!-- Packing Cost -->
        <div>
            <label class="text-xs text-gray-500 mb-0 block">Packing Cost</label>
            <UInput v-model="product.meta_data._packing_cost" icon="i-lucide-package" size="md" variant="outline"
                placeholder="Packing Cost..." />
        </div>

        <!-- Work Time (min) -->
        <div>
            <label class="text-xs text-gray-500 mb-0 block">Work Time (min)</label>
            <UInput v-model="product.meta_data._work_time_minutes" icon="i-lucide-clock" size="md" variant="outline"
                placeholder="Work Time..." />
        </div>

        <!-- Sale Price -->
        <div>
            <div v-if="getTypeLabel !== 'Parent'">
                <label class="text-xs text-gray-500 mb-0 block">Sale Price</label>
                <UInput v-model="productSalePrice" icon="i-lucide-euro" size="md" variant="outline"
                    placeholder="Sale Price..." />
            </div>
        </div>

        <!-- COGS -->
        <div>
            <div v-if="getTypeLabel !== 'Bundle'">
                <label class="text-xs text-gray-500 mb-0 block">COGS</label>
                <UInput v-model="product.meta_data._cogs_price" icon="i-lucide-euro" size="md" variant="outline"
                    placeholder="COGS..." />
            </div>
        </div>

        <!-- Development Cost -->
        <div>
            <label class="text-xs text-gray-500 mb-0 block">Dev Cost</label>
            <UInput v-model="product.meta_data._development_cost" icon="i-lucide-euro" size="md" variant="outline"
                placeholder="Dev Cost..." />
        </div>

        <!-- Development Months -->
        <div>
            <label class="text-xs text-gray-500 mb-0 block">Dev Months</label>
            <UInput v-model="product.meta_data._development_months" icon="i-lucide-calendar" size="md" variant="outline"
                placeholder="Dev Months..." />
        </div>
    </div>
    <div v-else class="text-gray-500">Product not found</div>
</template>
