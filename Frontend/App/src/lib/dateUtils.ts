/**
 * Date helpers aligned with FeatherPanel frontendv2 `dateUtils.ts`.
 * API datetimes are UTC (MySQL session +00:00); naive strings get a `Z` suffix before parse.
 */

const NAIVE_MYSQL_DATETIME = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})(\.\d+)?$/;

export function parseApiDate(value: string | number | Date | null | undefined): Date | null {
  if (value === null || value === undefined) return null;
  if (value instanceof Date) {
    return Number.isFinite(value.getTime()) ? value : null;
  }
  if (typeof value === "number") {
    if (!Number.isFinite(value)) return null;
    const ms = value < 1e12 ? value * 1000 : value;
    const d = new Date(ms);
    return Number.isFinite(d.getTime()) ? d : null;
  }
  const trimmed = value.trim();
  if (trimmed === "" || trimmed === "0000-00-00 00:00:00") return null;

  let candidate = trimmed;
  const m = trimmed.match(NAIVE_MYSQL_DATETIME);
  if (m) {
    candidate = `${m[1]}T${m[2]}${m[3] ?? ""}Z`;
  }

  const d = new Date(candidate);
  return Number.isFinite(d.getTime()) ? d : null;
}

export function getEffectiveTimezone(preference?: string | null): string {
  if (preference && preference.trim() !== "") return preference.trim();
  try {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (tz) return tz;
  } catch {
    /* fall through */
  }
  return "UTC";
}

export interface FormatDateOptions {
  timeZone?: string;
  locale?: string;
  now?: Date;
}

function formatRelativeIntlNarrow(date: Date, now: Date, localeCode: string): string {
  const diffSecTotal = Math.round((date.getTime() - now.getTime()) / 1000);
  const absSec = Math.abs(diffSecTotal);

  let value: number;
  let unit: Intl.RelativeTimeFormatUnit;

  if (absSec < 60) {
    value = diffSecTotal;
    unit = "second";
  } else if (absSec < 3600) {
    value = Math.round(diffSecTotal / 60);
    unit = "minute";
  } else if (absSec < 86_400) {
    value = Math.round(diffSecTotal / 3600);
    unit = "hour";
  } else {
    value = Math.round(diffSecTotal / 86_400);
    unit = "day";
  }

  const tag = (localeCode || "en").trim().replace("_", "-") || "en";
  try {
    const rtf = new Intl.RelativeTimeFormat(tag, { numeric: "always", style: "narrow" });
    return rtf.format(value, unit);
  } catch {
    const mins = Math.round(Math.abs(diffSecTotal) / 60);
    return diffSecTotal < 0 ? `${mins} min ago` : `in ${mins} min`;
  }
}

export function formatRelativeTime(
  value: string | number | Date | null | undefined,
  options: FormatDateOptions = {}
): string {
  const date = parseApiDate(value);
  if (!date) return "-";

  const now = options.now ?? new Date();
  const diffMs = now.getTime() - date.getTime();
  const absDays = Math.abs(diffMs) / 86_400_000;

  if (absDays > 30) {
    return formatDateTimeInTz(date, options);
  }

  const locale = (options.locale || navigator.language || "en").replace("_", "-");
  return formatRelativeIntlNarrow(date, now, locale);
}

export function formatDateTimeInTz(
  value: string | number | Date | null | undefined,
  options: FormatDateOptions = {}
): string {
  const date = value instanceof Date ? value : parseApiDate(value);
  if (!date) return "-";

  const tz = getEffectiveTimezone(options.timeZone);
  const locale = (options.locale || navigator.language || "en").replace("_", "-");

  try {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "medium",
      timeStyle: "short",
      timeZone: tz,
    }).format(date);
  } catch {
    return date.toLocaleString(locale);
  }
}

export function formatDateInTz(
  value: string | number | Date | null | undefined,
  options: FormatDateOptions = {}
): string {
  const date = parseApiDate(value);
  if (!date) return "-";

  const tz = getEffectiveTimezone(options.timeZone);
  const locale = (options.locale || navigator.language || "en").replace("_", "-");

  try {
    return new Intl.DateTimeFormat(locale, { dateStyle: "medium", timeZone: tz }).format(date);
  } catch {
    return date.toLocaleDateString(locale);
  }
}
