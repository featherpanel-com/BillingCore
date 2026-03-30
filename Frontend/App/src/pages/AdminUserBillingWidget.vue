<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Loader2, Wallet, FileText, MapPin, ExternalLink, User, Mail } from "lucide-vue-next";
import {
  useAdminBillingAPI,
  type UserWithCredits,
  type BillingInfo,
  type Invoice,
} from "@/composables/useBillingAPI";
import { useToast } from "vue-toastification";

function parseUserIdFromSearch(): number | null {
  const params = new URLSearchParams(window.location.search);
  const raw = params.get("userId");
  if (!raw) return null;
  const n = parseInt(raw, 10);
  return Number.isFinite(n) && n > 0 ? n : null;
}

const userId = ref<number | null>(parseUserIdFromSearch());

const toast = useToast();
const {
  getUserCredits,
  getUserBillingInfo,
  getInvoices,
  addUserCredits,
  removeUserCredits,
  setUserCredits,
} = useAdminBillingAPI();

const loading = ref(true);
const creditAmount = ref("");
const creditBusy = ref(false);
const credits = ref<UserWithCredits | null>(null);
const billingInfo = ref<BillingInfo | null>(null);
const invoices = ref<Invoice[]>([]);
const invoiceMeta = ref<{ total: number; per_page: number } | null>(null);
const loadError = ref<string | null>(null);

const formatDate = (dateString: string | null) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const getStatusBadgeVariant = (status: string) => {
  switch (status) {
    case "paid":
      return "default";
    case "pending":
      return "secondary";
    case "overdue":
      return "destructive";
    case "cancelled":
      return "outline";
    default:
      return "outline";
  }
};

function billingFieldRows(b: BillingInfo): { label: string; value: string }[] {
  const rows: { label: string; value: string }[] = [];
  const add = (label: string, v: string | null | undefined) => {
    if (v != null && String(v).trim() !== "") rows.push({ label, value: String(v) });
  };
  add("Full name", b.full_name);
  add("Company", b.company_name);
  add("Address line 1", b.address_line1);
  add("Address line 2", b.address_line2);
  add("City", b.city);
  add("State / region", b.state);
  add("Postal code", b.postal_code);
  add("Country", b.country_code);
  add("VAT ID", b.vat_id);
  add("Phone", b.phone);
  if (b.created_at) add("Profile created", formatDate(b.created_at));
  if (b.updated_at) add("Profile updated", formatDate(b.updated_at));
  return rows;
}

const billingRows = computed(() => {
  const b = billingInfo.value;
  if (!b) return [];
  return billingFieldRows(b);
});

async function loadAll() {
  const id = userId.value;
  if (id == null) {
    loading.value = false;
    loadError.value =
      "Missing user id for billing widget. Ensure Billing Core is installed and the panel passed userId in the widget context.";
    return;
  }

  loading.value = true;
  loadError.value = null;
  credits.value = null;
  billingInfo.value = null;
  invoices.value = [];
  invoiceMeta.value = null;

  try {
    const [cRes, invRes] = await Promise.all([
      getUserCredits(id),
      getInvoices(1, 15, id, undefined, undefined),
    ]);
    credits.value = cRes;
    invoices.value = invRes.data;
    invoiceMeta.value = {
      total: invRes.meta.pagination.total,
      per_page: invRes.meta.pagination.per_page,
    };
  } catch (e) {
    loadError.value = e instanceof Error ? e.message : "Failed to load billing data";
    loading.value = false;
    return;
  }

  try {
    billingInfo.value = await getUserBillingInfo(id);
  } catch {
    billingInfo.value = null;
  }

  loading.value = false;
}

async function refreshCreditsOnly() {
  const id = userId.value;
  if (id == null) return;
  try {
    credits.value = await getUserCredits(id);
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to refresh balance");
  }
}

async function handleAddCredits() {
  const id = userId.value;
  if (id == null) return;
  const amount = parseInt(creditAmount.value, 10);
  if (!amount || amount <= 0) {
    toast.error("Enter a valid positive amount");
    return;
  }
  creditBusy.value = true;
  try {
    await addUserCredits(id, amount);
    toast.success("Credits added");
    creditAmount.value = "";
    await refreshCreditsOnly();
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to add credits");
  } finally {
    creditBusy.value = false;
  }
}

async function handleRemoveCredits() {
  const id = userId.value;
  if (id == null) return;
  const amount = parseInt(creditAmount.value, 10);
  if (!amount || amount <= 0) {
    toast.error("Enter a valid positive amount");
    return;
  }
  creditBusy.value = true;
  try {
    await removeUserCredits(id, amount);
    toast.success("Credits removed");
    creditAmount.value = "";
    await refreshCreditsOnly();
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to remove credits");
  } finally {
    creditBusy.value = false;
  }
}

async function handleSetCredits() {
  const id = userId.value;
  if (id == null) return;
  const amount = parseInt(creditAmount.value, 10);
  if (Number.isNaN(amount) || amount < 0) {
    toast.error("Enter a valid amount (0 or greater)");
    return;
  }
  creditBusy.value = true;
  try {
    await setUserCredits(id, amount);
    toast.success("Balance set");
    creditAmount.value = "";
    await refreshCreditsOnly();
  } catch (e) {
    toast.error(e instanceof Error ? e.message : "Failed to set credits");
  } finally {
    creditBusy.value = false;
  }
}

onMounted(() => {
  loadAll();
});
</script>

<template>
  <div class="p-4 md:p-6 text-foreground text-[15px] leading-relaxed">
    <div
      v-if="userId == null"
      class="rounded-lg border border-dashed border-border/60 p-6 text-center text-sm text-muted-foreground"
    >
      {{ loadError || "No user selected." }}
    </div>

    <div v-else-if="loading" class="flex items-center justify-center gap-2 py-24 text-muted-foreground">
      <Loader2 class="h-6 w-6 animate-spin" />
      <span class="text-sm">Loading billing…</span>
    </div>

    <div
      v-else-if="loadError"
      class="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive"
    >
      {{ loadError }}
    </div>

    <div v-else class="flex flex-col gap-5 md:gap-6">
      <div class="grid gap-4 md:grid-cols-12 md:gap-5">
        <Card class="md:col-span-5 border-border/60 bg-card/40 p-5">
          <div class="flex items-center gap-2 text-muted-foreground mb-3">
            <Wallet class="h-4 w-4 shrink-0" />
            <span class="text-xs font-semibold uppercase tracking-wide">Balance</span>
          </div>
          <p class="text-3xl font-semibold tabular-nums tracking-tight">
            {{ credits?.credits_formatted ?? credits?.credits ?? "0" }}
          </p>
          <div class="mt-4 space-y-2 text-sm text-muted-foreground border-t border-border/50 pt-4">
            <div v-if="credits?.currency" class="flex justify-between gap-2">
              <span>Currency</span>
              <span class="text-foreground font-medium"
                >{{ credits.currency.symbol }} {{ credits.currency.code }} — {{ credits.currency.name }}</span
              >
            </div>
            <div v-if="credits?.credits_mode" class="flex justify-between gap-2">
              <span>Credits mode</span>
              <span class="text-foreground font-medium capitalize">{{ credits.credits_mode }}</span>
            </div>
            <div v-if="credits?.username" class="flex items-start gap-2">
              <User class="h-4 w-4 shrink-0 mt-0.5 opacity-70" />
              <span class="text-foreground">{{ credits.username }}</span>
            </div>
            <div v-if="credits?.email" class="flex items-start gap-2">
              <Mail class="h-4 w-4 shrink-0 mt-0.5 opacity-70" />
              <span class="text-foreground break-all">{{ credits.email }}</span>
            </div>
            <div v-if="credits?.uuid" class="text-xs font-mono text-foreground/80 break-all">
              UUID: {{ credits.uuid }}
            </div>
          </div>

          <div class="mt-5 border-t border-border/50 pt-5">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-3">
              Adjust balance
            </h4>
            <p class="text-xs text-muted-foreground mb-3">
              Add or remove credits, or set an exact balance (same as Billing Core admin).
            </p>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
              <div class="flex-1 min-w-0">
                <Label for="widget-credit-amount" class="text-xs">Amount</Label>
                <Input
                  id="widget-credit-amount"
                  v-model="creditAmount"
                  type="number"
                  min="0"
                  step="1"
                  placeholder="e.g. 100"
                  class="mt-1.5"
                  :disabled="creditBusy"
                  @keyup.enter="handleAddCredits"
                />
              </div>
              <div class="flex flex-col gap-2 sm:items-end">
                <div
                  v-if="creditBusy"
                  class="flex items-center justify-end gap-2 text-xs text-muted-foreground"
                >
                  <Loader2 class="h-3.5 w-3.5 animate-spin" />
                  Updating balance…
                </div>
                <div class="flex flex-wrap gap-2">
                  <Button size="sm" :disabled="creditBusy" @click="handleAddCredits"> Add </Button>
                  <Button size="sm" variant="destructive" :disabled="creditBusy" @click="handleRemoveCredits">
                    Remove
                  </Button>
                  <Button size="sm" variant="outline" :disabled="creditBusy" @click="handleSetCredits">
                    Set exact
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </Card>

        <Card class="md:col-span-7 border-border/60 bg-card/40 p-5">
          <div class="flex items-center gap-2 text-muted-foreground mb-3">
            <MapPin class="h-4 w-4 shrink-0" />
            <span class="text-xs font-semibold uppercase tracking-wide">Billing profile (full)</span>
          </div>
          <template v-if="billingRows.length">
            <div class="space-y-0 divide-y divide-border/40 text-sm">
              <div
                v-for="row in billingRows"
                :key="row.label"
                class="grid grid-cols-1 gap-1 py-2.5 sm:grid-cols-[11rem_1fr] sm:gap-4"
              >
                <span class="text-muted-foreground">{{ row.label }}</span>
                <span class="font-medium text-foreground break-words">{{ row.value }}</span>
              </div>
            </div>
          </template>
          <p v-else class="text-sm text-muted-foreground">No billing profile on file for this user.</p>
        </Card>
      </div>

      <Card class="border-border/60 bg-card/40 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
          <div class="flex items-center gap-2 text-muted-foreground">
            <FileText class="h-4 w-4 shrink-0" />
            <span class="text-xs font-semibold uppercase tracking-wide">Invoices</span>
            <span v-if="invoiceMeta" class="text-xs font-normal normal-case text-muted-foreground">
              (showing {{ invoices.length }} of {{ invoiceMeta.total }})
            </span>
          </div>
          <a
            href="/admin/billingcore/invoices"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex h-9 items-center gap-2 rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent"
          >
            Open Billing Core
            <ExternalLink class="h-4 w-4 opacity-70" />
          </a>
        </div>

        <div v-if="invoices.length === 0" class="text-sm text-muted-foreground py-8 text-center">
          No invoices for this user.
        </div>
        <div v-else class="overflow-x-auto rounded-md border border-border/50 -mx-1">
          <table class="w-full text-sm min-w-[720px]">
            <thead>
              <tr class="border-b border-border/60 text-left text-xs text-muted-foreground uppercase tracking-wide">
                <th class="p-3 font-medium">Invoice</th>
                <th class="p-3 font-medium">Status</th>
                <th class="p-3 font-medium">Created</th>
                <th class="p-3 font-medium">Due</th>
                <th class="p-3 font-medium">Paid</th>
                <th class="p-3 font-medium">Curr.</th>
                <th class="p-3 font-medium text-right">Subtotal</th>
                <th class="p-3 font-medium text-right">Tax</th>
                <th class="p-3 font-medium text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="inv in invoices"
                :key="inv.id"
                class="border-b border-border/40 last:border-0 hover:bg-muted/20"
              >
                <td class="p-3 font-mono text-xs align-top">{{ inv.invoice_number }}</td>
                <td class="p-3 align-top">
                  <Badge :variant="getStatusBadgeVariant(inv.status)" class="text-[10px] capitalize">
                    {{ inv.status }}
                  </Badge>
                </td>
                <td class="p-3 text-xs text-muted-foreground whitespace-nowrap align-top">
                  {{ formatDate(inv.created_at) }}
                </td>
                <td class="p-3 text-xs text-muted-foreground whitespace-nowrap align-top">
                  {{ formatDate(inv.due_date) }}
                </td>
                <td class="p-3 text-xs text-muted-foreground whitespace-nowrap align-top">
                  {{ formatDate(inv.paid_at) }}
                </td>
                <td class="p-3 text-xs align-top">{{ inv.currency_code }}</td>
                <td class="p-3 text-right text-xs tabular-nums align-top">
                  {{ inv.subtotal_formatted ?? inv.subtotal }}
                </td>
                <td class="p-3 text-right text-xs tabular-nums align-top">
                  {{ inv.tax_amount_formatted ?? inv.tax_amount }}
                </td>
                <td class="p-3 text-right text-xs font-medium tabular-nums align-top">
                  {{ inv.total_formatted ?? inv.total }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  </div>
</template>
