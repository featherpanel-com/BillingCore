import { computed, ref } from "vue";
import axios from "axios";
import {
  formatDateInTz,
  formatDateTimeInTz,
  formatRelativeTime,
  getEffectiveTimezone,
} from "../lib/dateUtils";

const timezone = ref(getEffectiveTimezone(null));
const ready = ref(false);
let loadPromise: Promise<void> | null = null;

async function ensurePreferencesLoaded(): Promise<void> {
  if (ready.value) return;
  if (!loadPromise) {
    loadPromise = (async () => {
      try {
        const res = await axios.get("/api/user/preferences", { withCredentials: true });
        const prefs = res.data?.data?.preferences;
        timezone.value = getEffectiveTimezone(
          prefs && typeof prefs.timezone === "string" ? prefs.timezone : null
        );
      } catch {
        timezone.value = getEffectiveTimezone(null);
      } finally {
        ready.value = true;
      }
    })();
  }
  await loadPromise;
}

export function usePanelTimezone() {
  const locale = computed(() => navigator.language || "en");

  void ensurePreferencesLoaded();

  function formatRelative(value: string | number | Date | null | undefined): string {
    return formatRelativeTime(value, { timeZone: timezone.value, locale: locale.value });
  }

  function formatDateTime(value: string | number | Date | null | undefined): string {
    return formatDateTimeInTz(value, { timeZone: timezone.value, locale: locale.value });
  }

  function formatDate(value: string | number | Date | null | undefined): string {
    return formatDateInTz(value, { timeZone: timezone.value, locale: locale.value });
  }

  return {
    timezone,
    ready,
    formatRelative,
    formatDateTime,
    formatDate,
    ensurePreferencesLoaded,
  };
}
