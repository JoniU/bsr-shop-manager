import axios from "axios";
import qs from "qs";

axios.defaults.headers.post["Content-Type"] = "application/x-www-form-urlencoded";
axios.defaults.transformRequest = [(data) => qs.stringify(data)];

export interface FetchDataOptions<T> {
    apiUrl: string;
    action: string;
    security: string;
    transform?: (data: T) => T;
    onError?: (error: string) => void;
}

export const fetchData = async <T>({
    apiUrl,
    action,
    security,
    transform,
    onError,
}: FetchDataOptions<T>): Promise<T | null> => {
    try {
        const response = await axios.post(apiUrl, {
            action,
            security,
        });

        console.log("Backend response:", response); // Log backend response

        if (response.data.success) {
            let data = response.data.data;
            if (transform) {
                data = transform(data);
            }
            return data;
        } else {
            if (onError) onError("Failed to fetch data.");
            return null;
        }
    } catch (err) {
        if (onError) onError("Error fetching data.");
        console.error("Fetch error:", err);
        return null;
    }
};

