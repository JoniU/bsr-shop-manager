import { defineStore } from 'pinia';
import type { Product } from '~/types/product'; // Assuming `Product` is defined elsewhere
import axios from 'axios';
import { useProductTransform } from '~/composables/useProductTransform';

export const useProductStore = defineStore('products', {
    state: () => ({
        products: {} as Record<number, Product>, // Object to store all products keyed by ID
        loading: false, // Loading state
        error: null as string | null, // Error state
    }),
    actions: {

        // Fetch products directly from the API (no cache)
        async fetchProducts() {
            const toast = useToast();
            this.loading = true;
            this.error = null;

            const config = useRuntimeConfig();
            const apiUrl = `${config.public.baseUrl}/wp-json/custom/v1/get-products`;

            try {
                const response = await axios.get(apiUrl);

                // Check response data validity
                const validData = Array.isArray(response.data) ? response.data : [];

                // Normalize each product with fallback values
                const normalizedData = validData.map((product) => ({
                    id: product.id || null,
                    name: product.name || '',
                    sku: product.sku || '',
                    price: product.price || 0,
                    regular_price: product.regular_price || 0,
                    sale_price: product.sale_price || null,
                    stock_quantity: product.stock_quantity || 0,
                    manage_stock: product.manage_stock !== undefined ? product.manage_stock : false,
                    type: product.type || '',
                    variations: product.variations || [],
                    meta_data: {
                        _cogs_price: product.meta_data?._cogs_price || 0,
                        _packing_cost: product.meta_data?._packing_cost || 0,
                        _work_time_minutes: product.meta_data?._work_time_minutes || 0,
                        _development_cost: product.meta_data?._development_cost || 0,
                        _development_months: product.meta_data?._development_months || 0,
                    },
                }));

                // Store normalized data in the store
                this.setProducts(normalizedData);

                // Show success toast
                toast.add({
                    title: 'Products Fetched',
                    description: 'Product data has been successfully fetched from the server.',
                    color: 'success',
                    icon: 'i-lucide-check',
                });
            } catch (err) {
                console.error(err);

                // Set an empty array and error message in case of failure
                this.setProducts([]);
                this.error = 'Failed to load products.';

                // Show error toast
                toast.add({
                    title: 'Fetch Failed',
                    description: 'Failed to fetch product data from the server.',
                    color: 'error',
                    icon: 'i-lucide-alert-circle',
                });
            } finally {
                this.loading = false;
            }
        },

        // Set products (convert array to an object keyed by ID)
        setProducts(products: Product[]) {
            this.products = products.reduce((acc, product) => {
                acc[product.id] = product;
                return acc;
            }, {} as Record<number, Product>);
        },

        // Update a single product by ID
        async updateProduct(productId: number, updates: Partial<Product>, parentId?: number) {
            try {
                const { transformProductToSave } = useProductTransform();
                const payload = transformProductToSave(updates); // Transform the partial Product

                // Construct the URL dynamically
                const config = useRuntimeConfig();

                const apiEndpoint = parentId
                    ? `/wp-json/custom/v1/products/${parentId}/variations/${productId}`
                    : `/wp-json/custom/v1/products/${productId}`;

                const apiUrl = `${config.public.baseUrl}${apiEndpoint}`;
                console.log(apiUrl)
                const response = await axios.put(
                    apiUrl,
                    payload,
                    {
                        withCredentials: true,
                    }
                );
                console.log(response);
                // Update the local store after the API update
                if (parentId) {
                    const parentProduct = this.products[parentId];
                    if (parentProduct && parentProduct.variations) {
                        const variationIndex = parentProduct.variations.findIndex(
                            (variation) => variation.id === productId
                        );
                        if (variationIndex !== -1) {
                            parentProduct.variations[variationIndex] = {
                                ...parentProduct.variations[variationIndex],
                                ...updates,
                            };
                        }
                    }
                } else {
                    this.products[productId] = {
                        ...this.products[productId],
                        ...updates,
                    };
                }

                return response.data;
            } catch (error: unknown) {
                console.error('Error updating product:', error);
                const errorMessage =
                    error instanceof Error ? error.message : 'Failed to update product.';
                throw new Error(errorMessage);
            }
        },
    },
    getters: {
        // Get a product by ID, supporting both parent and variation lookup
        getProductById: (state) => (id: number, parentId?: number): Product | undefined => {
            // If only the product ID is provided, directly look for it in the first level
            if (!parentId) {
                return state.products[id];
            }

            // If both parentId and id are provided, look for the variation inside the parent's variations
            const parentProduct = state.products[parentId];
            if (!parentProduct) return undefined; // Parent not found

            // If the product is the parent itself, return it
            if (parentProduct.id === id) return parentProduct;

            // Look for the product in the parent's variations array
            if (parentProduct.variations && Array.isArray(parentProduct.variations)) {
                return parentProduct.variations.find((variation) => variation.id === id);
            }

            return undefined; // Product not found
        },
        // Flattened products for hierarchical rendering (e.g., for tables)
        flattenedProducts(state): Product[] {
            // Flatten products with variations
            return Object.values(state.products).flatMap((product) => {
                // Parent product at level 0
                const parentWithLevel = { ...product, level: 0 };

                // Handle variations for Variable products
                if (product.type === 'Variable' && Array.isArray(product.variations) && product.variations.length > 0) {
                    const variationsWithLevel = product.variations.map((variation) => ({
                        ...variation,
                        parentId: product.id, // Attach parent ID
                        name: variation.name || `Variation ${variation.id}`, // Fallback name for variations
                        level: 1, // Indicate that this is a child level
                    }));

                    // Return the parent product followed by its variations
                    return [parentWithLevel, ...variationsWithLevel];
                }

                // Return only the parent product if no variations exist
                return [parentWithLevel];
            });
        },
    },
});
