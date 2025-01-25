<script setup lang="ts">
import { computed } from 'vue';
import { useProductStore } from '~/stores/products';
import type { ProductId } from '~/types/product';

const productStore = useProductStore();

const props = defineProps<{ id: ProductId; parentId?: ProductId }>(); // Accept product and parent IDs
const product = computed(() => productStore.getProductById(props.id, props.parentId));

// Determine the label for the product type
const getTypeLabel = computed(() => {
    if (!product.value) return 'Unknown'; // Fallback if product is not found
    if (props.parentId) return 'Variation';
    if (product.value.type === 'Woosb') return 'Bundle';
    return product.value.type === 'Variable' ? 'Parent' : 'Simple';
});

// Determine the color for the product type
const getTypeColor = computed(() => {
    if (!product.value) return 'neutral'; // Fallback if product is not found
    if (props.parentId) return 'secondary'; // Variations
    if (product.value.type === 'Woosb') return 'success'; // Bundle
    if (product.value.type === 'Variable') return 'secondary'; // Parent
    return 'warning'; // Simple
});

// Determine the size for the button based on the product type
const getTypeSize = computed(() => {
    if (!product.value) return 'md'; // Fallback if product is not found
    if (props.parentId) return 'md'; // Variations
    if (product.value.type === 'Woosb') return 'xl'; // Bundle
    if (product.value.type === 'Variable') return 'xl'; // Parent
    return 'lg'; // Simple
});
</script>

<template>
    <div v-if="product" :class="props.parentId ? 'gap-0 pl-6 pr-0 flex items-center' : 'flex items-center gap-2'">
        <div class="flex flex-col items-center">
            <!-- Product ID -->
            <p class="text-md text-gray-500 font-medium">{{ product.id }}</p>

            <!-- Tooltip and Button -->
            <UTooltip :text="getTypeLabel">
                <UButton :icon="product.type === 'Woosb'
                    ? 'i-lucide-boxes'
                    : product.type === 'Variable'
                        ? 'i-lucide-package-plus'
                        : 'i-lucide-package'" :color="getTypeColor" :size="getTypeSize" variant="ghost" />
            </UTooltip>
        </div>
    </div>
    <div v-else class="text-gray-500">Product not found</div>
</template>
