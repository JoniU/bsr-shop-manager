<script setup lang="ts">

import { useProductStore } from '~/stores/products';
const productsStore = useProductStore();


onMounted(() => {
    productsStore.fetchProducts();
});

const products = computed(() => productsStore.flattenedProducts);
const isLoading = computed(() => productsStore.loading);
const value = ref(null)
</script>

<template>

    <div class="w-full">
        <!-- Table Header -->
        <div class="grid p-2 border-b font-bold text-sm text-left bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-200  border-gray-400 dark:border-gray-600"
            style="grid-template-columns: 1fr 4fr 5fr 1fr;">
            <div>Product</div>
            <div>Name</div>
            <div>Price, Stock & Cost</div>
            <div class="text-right">Actions</div>
        </div>
        <!-- Loading Indicator -->
        <div v-if="isLoading" class="p-4">
            <UProgress v-model="value" />
        </div>

        <!-- Table Body -->
        <div v-else>
            <div> <!-- row -->
                <div v-for="product in products" :key="product.id"
                    class="grid p-2 border-b border-gray-400 dark:border-gray-700 items-center" :class="product.parentId
                        ? 'bg-gray-300 dark:bg-gray-900 text-gray-900 dark:text-gray-200'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 dark:border-gray-900'"
                    style="grid-template-columns: 1fr 4fr 5fr 1fr;">
                    <!-- Product -->
                    <div>
                        <EditorTableCellTypeId :id="product.id" :parentId="product.parentId || undefined" />
                    </div>

                    <!-- Name -->
                    <div>
                        <EditorTableCellName :id="product.id" :parentId="product.parentId || undefined" />
                    </div>

                    <!-- Price, Stock & Cost -->
                    <div>
                        <EditorTableCellPriceStockCost :id="product.id" :parentId="product.parentId || undefined" />
                    </div>

                    <!-- Actions -->
                    <div>
                        <EditorTableCellActions :id="product.id" :parentId="product.parentId || undefined" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
