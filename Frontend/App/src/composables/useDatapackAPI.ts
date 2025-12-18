import { ref, computed } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";
import { useServerUuid } from "./useServerUuid";

export interface World {
  name: string;
  path: string;
}

export interface Pack {
  name: string;
  display?: string;
  version?: string;
  description?: string;
  incompatible?: string[];
  lastupdated?: number;
  video?: string;
  [key: string]: unknown;
}

export interface Category {
  category?: string;
  name?: string;
  packs: Pack[];
  [key: string]: unknown;
}

export interface PackData {
  versionName?: string;
  categories: Category[];
  [key: string]: unknown;
}

export function useDatapackAPI() {
  const { serverUuid } = useServerUuid();

  const loading = ref(false);
  const error = ref<string | null>(null);

  const apiBaseUrl = computed(() => {
    if (!serverUuid.value) return null;
    return `/api/user/servers/${serverUuid.value}/addons/datapackinstaller`;
  });

  const handleError = (err: unknown): string => {
    if (axios.isAxiosError(err)) {
      const axiosError = err as AxiosError<{
        message?: string;
        error?: string;
      }>;
      return (
        axiosError.response?.data?.message ||
        axiosError.response?.data?.error ||
        axiosError.message ||
        "An error occurred"
      );
    }
    if (err instanceof Error) {
      return err.message;
    }
    return "An unknown error occurred";
  };

  const detectVersion = async (): Promise<string | null> => {
    if (!apiBaseUrl.value) {
      throw new Error("Server UUID is required");
    }

    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`${apiBaseUrl.value}/detect-version`);
      if (response.data && response.data.success) {
        return response.data.data?.version || null;
      }
      return null;
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const getWorlds = async (): Promise<World[]> => {
    if (!apiBaseUrl.value) {
      throw new Error("Server UUID is required");
    }

    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`${apiBaseUrl.value}/worlds`);
      if (response.data && response.data.success) {
        return response.data.data?.worlds || [];
      }
      return [];
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const getPacks = async (
    mcVersion: string,
    type: string = "datapacks"
  ): Promise<PackData | null> => {
    if (!apiBaseUrl.value) {
      throw new Error("Server UUID is required");
    }

    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`${apiBaseUrl.value}/packs`, {
        params: {
          mcVersion,
          type,
        },
      });
      if (response.data && response.data.success) {
        return response.data.data || null;
      }
      return null;
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const getPackImageUrl = (
    packName: string,
    mcVersion: string,
    type: string = "datapacks"
  ): string | null => {
    if (!apiBaseUrl.value) return null;
    return `${apiBaseUrl.value}/image?pack=${encodeURIComponent(
      packName
    )}&mcVersion=${encodeURIComponent(mcVersion)}&type=${encodeURIComponent(
      type
    )}`;
  };

  const installPacks = async (
    mcVersion: string,
    packType: string,
    packs: Record<string, string[]>,
    world: string
  ): Promise<void> => {
    if (!apiBaseUrl.value) {
      throw new Error("Server UUID is required");
    }

    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post(`${apiBaseUrl.value}/install`, {
        mcVersion,
        pack_type: packType,
        packs,
        world,
      });

      if (!response.data || !response.data.success) {
        throw new Error(response.data?.message || "Installation failed");
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    error,
    detectVersion,
    getWorlds,
    getPacks,
    getPackImageUrl,
    installPacks,
  };
}
