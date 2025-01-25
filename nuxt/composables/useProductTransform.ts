import type { Product, ProductSave, MetaData, MetaDataSave } from '~/types/product';

export function useProductTransform() {
    function transformProductToSave(product: Partial<Product>): Partial<ProductSave> {
        return {
            ...product,
            regular_price: product.regular_price?.toString(),
            sale_price: product.sale_price?.toString(),
            stock_quantity: product.stock_quantity?.toString(),
            meta_data: product.meta_data
                ? Object.entries(product.meta_data).map(([key, value]) => ({
                    key,
                    value: value as number,
                }))
                : []
        };
    }

    function transformSaveToProduct(productSave: Partial<ProductSave>): Partial<Product> {
        return {
            ...productSave,
            regular_price: productSave.regular_price ? parseFloat(productSave.regular_price) : undefined,
            sale_price: productSave.sale_price ? parseFloat(productSave.sale_price) : undefined,
            stock_quantity: productSave.stock_quantity ? parseFloat(productSave.stock_quantity) : undefined,
            meta_data: productSave.meta_data?.reduce(
                (acc, { key, value }) => ({
                    ...acc,
                    [key]: value,
                }),
                {} as MetaData
            )
        };
    }

    return {
        transformProductToSave,
        transformSaveToProduct,
    };
}
