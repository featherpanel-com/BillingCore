import { computed } from "vue";

/**
 * Composable to get the server UUID from URL query parameters
 * @returns The server UUID or null if not present
 */
export function useServerUuid() {
  const serverUuid = computed(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get("serverUuid") || null;
  });

  return {
    serverUuid,
  };
}

