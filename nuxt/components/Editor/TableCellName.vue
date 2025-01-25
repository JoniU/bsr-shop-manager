<script setup lang="ts">
import { computed } from 'vue';
import { useProductStore } from '~/stores/products';
import type { ProductId } from '~/types/product'; // Import the ProductId type

const productStore = useProductStore();

const props = defineProps<{ id: ProductId; parentId?: ProductId }>(); // Accept product and parent IDs
const product = computed(() => productStore.getProductById(props.id, props.parentId));

// Strip HTML tags from the product name reactively
const productName = computed(() => (product.value ? product.value.name : ''));

// Add logic to replace " - " with a line-break-friendly string
const productNameWithBreaks = computed(() => {
    if (!productName.value) return ''; // Fallback for empty names

    const cleanName = productName.value.replace(/<wbr>/g, ''); // Remove any pre-existing <wbr>

    // Add <wbr> ONLY before the first " - "
    const firstDashIndex = cleanName.indexOf(' - ');
    if (firstDashIndex === -1) {
        return cleanName; // No " - " found, return the clean name
    }

    return (
        cleanName.slice(0, firstDashIndex) + // Part before the first " - "
        '<wbr>' + // Insert <wbr>
        cleanName.slice(firstDashIndex) // Part from the first " - " onwards
    );
});
</script>

<template>
    <div v-if="product" :class="['flex flex-col gap-1', props.parentId ? 'pl-4' : '']">
        <p v-if="props.parentId" class="text-sm text-gray-500">
            Parent ID: {{ props.parentId }}
        </p>
        <p class="font-small mr-6 mb-2 text-[var(--ui-text-highlighted)] break-words">
            <span v-html="productNameWithBreaks"></span>
        </p>
        <div class="flex items-center gap-2 w-full">
            <!-- Tooltip wrapping the SKU input -->
            <UTooltip text="SKU" placement="top">
                <UInput v-model="product.sku" color="primary" variant="outline" size="md" class="w-full mr-6"
                    placeholder="Enter SKU..." />
            </UTooltip>
        </div>
    </div>
    <div v-else class="text-gray-500">Product not found</div>
</template>
