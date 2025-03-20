<script setup lang="ts">
import { useProductStore } from '~/stores/products';
import { computed } from 'vue';

const productsStore = useProductStore();
const isLoading = computed(() => productsStore.loading);
const value = ref(null);

// Fetch products on mount
onMounted(() => {
    productsStore.fetchProducts();
});

// Filter products: exclude parents and bundles, calculate total stock value
const inventoryProducts = computed(() => {
    // It's assumed that productsStore.flattenedProducts includes both parent and variation products.
    const products = productsStore.flattenedProducts
        .filter((product) => {
            // Flag to decide if the product should be excluded
            let exclude = false;

            // Check if the product itself has the custom field set
            if (product.meta_data && product.meta_data._exclude_from_stock === 'yes') {
                exclude = true;
            }

            // For variations, also check if the parent has the exclusion set
            if (!exclude && product.parentId) {
                const parent = productsStore.flattenedProducts.find((p) => p.id === product.parentId);
                console.log(parent);
                if (parent && parent.meta_data && parent.meta_data._exclude_from_stock === 'yes') {
                    exclude = true;
                }
            }

            if (exclude) {
                return false;
            }

            // Determine product type label
            const typeLabel = product.parentId
                ? 'Variation'
                : product.type === 'Woosb'
                  ? 'Bundle'
                  : product.type === 'Variable'
                    ? 'Parent'
                    : 'Simple';

            // Exclude parents and bundles from being counted (if that's desired)
            return typeLabel !== 'Parent' && typeLabel !== 'Bundle';
        })
        .map((product) => {
            const regularPrice = parseFloat(String(product.regular_price)) || 0;

            const cogs =
                product.meta_data && product.meta_data._cogs_price
                    ? parseFloat(String(product.meta_data._cogs_price)) || 0
                    : parseFloat((regularPrice * 0.6).toFixed(2));

            const stockQuantity = parseFloat(String(product.stock_quantity)) || 0;
            const totalStockValue = parseFloat((stockQuantity * cogs).toFixed(2));

            return {
                id: product.id,
                name: product.name,
                sku: product.sku,
                stock_quantity: stockQuantity,
                cogs,
                total_stock_value: totalStockValue,
            };
        });

    return products;
});

// Compute the total stock value sum
const totalStockValueSum = computed(() => {
    return inventoryProducts.value.reduce((sum, product) => sum + product.total_stock_value, 0).toFixed(2);
});

// Function to download data as CSV
function stripHtml(html: string): string {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    return doc.body.textContent || '';
}

function escapeCsvField(field: string): string {
    if (field.includes(',') || field.includes('"') || field.includes('\n')) {
        // Escape double quotes and wrap the field in double quotes
        return `"${field.replace(/"/g, '""')}"`;
    }
    return field;
}

const downloadCSV = () => {
    const csvRows = [
        ['ID', 'Name', 'SKU', 'Stock Quantity', 'COGS', 'Total Stock Value'], // CSV Header
        ...inventoryProducts.value.map((product) => [
            product.id,
            escapeCsvField(stripHtml(product.name)), // Strip HTML and escape special characters
            escapeCsvField(stripHtml(product.sku || '')), // Sanitize and escape SKU
            product.stock_quantity,
            product.cogs,
            product.total_stock_value,
        ]),
    ];

    // Convert rows to CSV string
    const csvContent = csvRows.map((row) => row.join(',')).join('\n');

    // Create a blob and download the file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'inventory_data.csv';
    link.click();
};
const rowClass = 'px-2 py-1 text-left text-sm text-gray-700 dark:text-gray-300';
const thClass = 'pl-2 p-1';
const tdClass = 'px-2 py-2';
</script>

<template>
    <div class="w-full p-4">
        <!-- Total Stock Value Summary -->
        <div class="flex justify-between items-center">
            <div>
                <div class="text-lg font-bold">
                    Total Stock Value: <span class="text-primary">€{{ totalStockValueSum }}</span>
                </div>
                <div class="text-sm text-gray-500">
                    This is counted with COGS at 60% of the price if no COGS are set.
                </div>
            </div>
            <UButton @click="downloadCSV" color="primary" variant="outline" size="lg"> Download CSV </UButton>
        </div>
        <!-- Inventory Table -->
        <div v-if="isLoading" class="my-4">
            <UProgress v-model="value" />
        </div>
        <table v-else class="w-full my-4 table-auto border-collapse border-b border-gray-200 dark:border-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-900">
                <tr :class="rowClass" class="border-0">
                    <th :class="thClass" class="pl-4">ID</th>
                    <th :class="thClass">Name</th>
                    <th :class="thClass">SKU</th>
                    <th :class="thClass" class="text-right pr-2">Stock</th>
                    <th :class="thClass" class="text-right pr-2">COGS</th>
                    <th :class="thClass" class="text-right pr-4">Stock Value</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="product in inventoryProducts"
                    :key="product.id"
                    class="odd:bg-gray-50 even:bg-gray-100 dark:odd:bg-gray-900 dark:even:bg-gray-800 text-sm text-gray-800 dark:text-gray-300"
                >
                    <td :class="tdClass" class="pl-4">{{ product.id }}</td>
                    <td :class="tdClass" v-html="product.name"></td>
                    <td :class="tdClass">{{ product.sku }}</td>
                    <td :class="tdClass" class="text-right">
                        {{ product.stock_quantity.toFixed(2) }}
                    </td>
                    <td :class="tdClass" class="text-right">
                        {{ product.cogs.toFixed(2) }}
                    </td>
                    <td :class="tdClass" class="text-right pr-4">
                        {{ product.total_stock_value.toFixed(2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style lang="css" scoped></style>
