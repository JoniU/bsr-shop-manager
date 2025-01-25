import { useRuntimeConfig } from '#app';

export function getWooCommerceAuthHeader() {
    const config = useRuntimeConfig();
    const { wooConsumerKey, wooConsumerSecret } = config.public;

    const authString = `${wooConsumerKey}:${wooConsumerSecret}`;
    const encodedAuth = btoa(authString);

    return `Basic ${encodedAuth}`;
}
