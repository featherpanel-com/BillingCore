<script setup lang="ts">
import { ref, onMounted } from "vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Loader2,
  Eye,
  ChevronLeft,
  ChevronRight,
  FileText,
  Filter,
  RefreshCw,
  Calendar,
  DollarSign,
  Download,
  MoreVertical,
} from "lucide-vue-next";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useBillingAPI, type Invoice } from "@/composables/useBillingAPI";
import { useToast } from "vue-toastification";

const toast = useToast();
const { loading, getInvoices, getInvoice } = useBillingAPI();

const invoices = ref<Invoice[]>([]);
const currentPage = ref(1);
const totalPages = ref(1);
const statusFilter = ref<string>("all");
const selectedInvoice = ref<Invoice | null>(null);
const invoiceDialogOpen = ref(false);
const loadingInvoice = ref(false);

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

const loadInvoices = async (page: number = 1) => {
  currentPage.value = page;
  try {
    const response = await getInvoices(
      page,
      20,
      statusFilter.value === "all" ? undefined : statusFilter.value
    );
    invoices.value = response.data;
    totalPages.value = response.meta.pagination.total_pages;
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load invoices");
  }
};

const viewInvoice = async (invoiceId: number) => {
  loadingInvoice.value = true;
  invoiceDialogOpen.value = true;
  try {
    selectedInvoice.value = await getInvoice(invoiceId);
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load invoice");
    invoiceDialogOpen.value = false;
  } finally {
    loadingInvoice.value = false;
  }
};

const formatDate = (dateString: string | null) => {
  if (!dateString) return "N/A";
  return new Date(dateString).toLocaleDateString();
};

const formatDateFull = (dateString: string | null) => {
  if (!dateString) return "N/A";
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

const downloadInvoice = async (invoice: Invoice) => {
  try {
    // Fetch full invoice details if not already loaded
    let invoiceData = invoice;
    if (!invoice.items || invoice.items.length === 0) {
      invoiceData = await getInvoice(invoice.id);
    }

    // Create a printable HTML invoice
    const htmlContent = generateInvoiceHTML(invoiceData);

    // Create blob and download
    const blob = new Blob([htmlContent], { type: "text/html" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `invoice-${
      invoiceData.invoice_number || invoiceData.id
    }.html`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    toast.success("Invoice downloaded successfully!");
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to download invoice"
    );
  }
};

const formatBillingAddress = (billingInfo: any): string => {
  if (!billingInfo) return "";
  const parts: string[] = [];
  if (billingInfo.full_name) parts.push(billingInfo.full_name);
  if (billingInfo.company_name) parts.push(billingInfo.company_name);
  if (billingInfo.address_line1) parts.push(billingInfo.address_line1);
  if (billingInfo.address_line2) parts.push(billingInfo.address_line2);
  const cityState = [
    billingInfo.city,
    billingInfo.state,
    billingInfo.postal_code,
  ]
    .filter(Boolean)
    .join(", ");
  if (cityState) parts.push(cityState);
  if (billingInfo.country_code) parts.push(billingInfo.country_code);
  if (billingInfo.vat_id) parts.push(`VAT ID: ${billingInfo.vat_id}`);
  if (billingInfo.phone) parts.push(billingInfo.phone);
  return parts.join("<br>");
};

const generateInvoiceHTML = (invoice: any): string => {
  const itemsHTML =
    invoice.items
      ?.map(
        (item: any) => `
      <tr>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">${
          item.description
        }</td>
        <td style="padding: 8px; text-align: right; border-bottom: 1px solid #e5e7eb;">${
          item.quantity
        }</td>
        <td style="padding: 8px; text-align: right; border-bottom: 1px solid #e5e7eb;">${
          item.unit_price_formatted || item.unit_price
        }</td>
        <td style="padding: 8px; text-align: right; border-bottom: 1px solid #e5e7eb; font-weight: 600;">${
          item.total_formatted || item.total
        }</td>
      </tr>
    `
      )
      .join("") || "";

  const customerInfo = invoice.customer
    ? `
      <div class="detail-section">
        <h3>Bill To</h3>
        ${
          invoice.customer.username
            ? `<p><strong>${invoice.customer.username}</strong></p>`
            : ""
        }
        ${invoice.customer.email ? `<p>${invoice.customer.email}</p>` : ""}
        ${
          invoice.customer.billing_info
            ? `<div style="margin-top: 8px;">${formatBillingAddress(
                invoice.customer.billing_info
              )}</div>`
            : ""
        }
      </div>
    `
    : "";

  const adminInfo = invoice.admin?.billing_info
    ? `
      <div class="detail-section">
        <h3>From</h3>
        ${formatBillingAddress(invoice.admin.billing_info)}
      </div>
    `
    : "";

  return `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice ${invoice.invoice_number || invoice.id}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      line-height: 1.6;
      color: #1f2937;
      background: #fff;
      padding: 40px;
    }
    .invoice-container {
      max-width: 800px;
      margin: 0 auto;
      background: #fff;
    }
    .header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 40px;
      padding-bottom: 20px;
      border-bottom: 2px solid #e5e7eb;
    }
    .invoice-title {
      font-size: 32px;
      font-weight: 700;
      color: #111827;
    }
    .invoice-number {
      font-size: 18px;
      color: #6b7280;
      margin-top: 8px;
    }
    .invoice-info {
      text-align: right;
    }
    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      text-transform: uppercase;
      margin-top: 8px;
    }
    .status-paid { background: #d1fae5; color: #065f46; }
    .status-pending { background: #dbeafe; color: #1e40af; }
    .status-overdue { background: #fee2e2; color: #991b1b; }
    .status-draft { background: #f3f4f6; color: #374151; }
    .status-cancelled { background: #f3f4f6; color: #6b7280; }
    .details {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
      margin-bottom: 40px;
    }
    .detail-section h3 {
      font-size: 14px;
      font-weight: 600;
      color: #6b7280;
      margin-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .detail-section p {
      font-size: 16px;
      color: #111827;
      margin-bottom: 4px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
    }
    thead {
      background: #f9fafb;
    }
    th {
      padding: 12px;
      text-align: left;
      font-size: 12px;
      font-weight: 600;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid #e5e7eb;
    }
    th.text-right {
      text-align: right;
    }
    .totals {
      margin-top: 20px;
      margin-left: auto;
      width: 300px;
    }
    .total-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      font-size: 14px;
    }
    .total-row.final {
      border-top: 2px solid #111827;
      margin-top: 8px;
      padding-top: 16px;
      font-size: 20px;
      font-weight: 700;
    }
    .notes {
      margin-top: 40px;
      padding: 20px;
      background: #f9fafb;
      border-radius: 8px;
    }
    .notes h4 {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 8px;
      color: #111827;
    }
    .notes p {
      font-size: 14px;
      color: #6b7280;
      line-height: 1.6;
    }
    @media print {
      body { padding: 20px; }
      .no-print { display: none; }
    }
  </style>
</head>
<body>
  <div class="invoice-container">
    <div class="header">
      <div>
        <div class="invoice-title">Invoice</div>
        <div class="invoice-number">#${
          invoice.invoice_number || invoice.id
        }</div>
      </div>
      <div class="invoice-info">
        <div style="font-size: 14px; color: #6b7280;">Status</div>
        <div class="status-badge status-${invoice.status}">${
    invoice.status
  }</div>
      </div>
    </div>

    <div class="details">
      ${customerInfo}
      ${adminInfo}
    </div>

    <div class="details" style="margin-top: 20px;">
      <div class="detail-section">
        <h3>Invoice Details</h3>
        <p><strong>Invoice Number:</strong> ${
          invoice.invoice_number || invoice.id
        }</p>
        <p><strong>Date:</strong> ${formatDateFull(invoice.created_at)}</p>
        ${
          invoice.due_date
            ? `<p><strong>Due Date:</strong> ${formatDateFull(
                invoice.due_date
              )}</p>`
            : ""
        }
        ${
          invoice.paid_at
            ? `<p><strong>Paid At:</strong> ${formatDateFull(
                invoice.paid_at
              )}</p>`
            : ""
        }
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Description</th>
          <th class="text-right">Quantity</th>
          <th class="text-right">Unit Price</th>
          <th class="text-right">Total</th>
        </tr>
      </thead>
      <tbody>
        ${itemsHTML}
      </tbody>
    </table>

    <div class="totals">
      <div class="total-row">
        <span>Subtotal:</span>
        <span>${invoice.subtotal_formatted || invoice.subtotal}</span>
      </div>
      ${
        invoice.tax_rate > 0
          ? `
      <div class="total-row">
        <span>Tax (${invoice.tax_rate}%):</span>
        <span>${invoice.tax_amount_formatted || invoice.tax_amount}</span>
      </div>
      `
          : ""
      }
      <div class="total-row final">
        <span>Total:</span>
        <span>${invoice.total_formatted || invoice.total}</span>
      </div>
    </div>

    ${
      invoice.notes
        ? `
    <div class="notes">
      <h4>Notes</h4>
      <p>${invoice.notes}</p>
    </div>
    `
        : ""
    }
  </div>
</body>
</html>
  `;
};

onMounted(() => {
  loadInvoices(1);
});
</script>

<template>
  <div class="min-h-screen p-4 md:p-8">
    <div class="max-w-5xl mx-auto space-y-8">
      <!-- Header Section -->
      <div class="text-center space-y-4">
        <div class="flex items-center justify-center gap-3">
          <div class="relative">
            <div
              class="absolute inset-0 bg-primary/20 blur-2xl rounded-full"
            ></div>
            <FileText class="relative h-12 w-12 text-primary" />
          </div>
        </div>
        <div>
          <h1
            class="text-5xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent"
          >
            My Invoices
          </h1>
          <p class="text-lg text-muted-foreground mt-2">
            View and manage your invoices
          </p>
        </div>
      </div>

      <!-- Filters -->
      <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
        <div class="p-4 flex gap-3 items-center">
          <Filter class="h-4 w-4 text-muted-foreground" />
          <Select v-model="statusFilter" @update:model-value="loadInvoices(1)">
            <SelectTrigger class="w-[180px]">
              <SelectValue placeholder="All Statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Statuses</SelectItem>
              <SelectItem value="draft">Draft</SelectItem>
              <SelectItem value="pending">Pending</SelectItem>
              <SelectItem value="paid">Paid</SelectItem>
              <SelectItem value="overdue">Overdue</SelectItem>
              <SelectItem value="cancelled">Cancelled</SelectItem>
            </SelectContent>
          </Select>
          <Button
            @click="loadInvoices(currentPage)"
            variant="outline"
            class="gap-2"
          >
            <RefreshCw class="h-4 w-4" />
            Refresh
          </Button>
        </div>
      </Card>

      <!-- Invoices List -->
      <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
        <div class="p-6">
          <div
            v-if="loading && invoices.length === 0"
            class="flex items-center justify-center py-12"
          >
            <Loader2 class="h-8 w-8 animate-spin" />
          </div>
          <div
            v-else-if="invoices.length === 0"
            class="text-center py-12 text-muted-foreground"
          >
            No invoices found
          </div>
          <div
            v-else
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
          >
            <Card
              v-for="invoice in invoices"
              :key="invoice.id"
              class="hover:shadow-lg transition-shadow cursor-pointer"
              @click="viewInvoice(invoice.id)"
            >
              <CardHeader class="pb-3">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <CardTitle class="text-lg font-semibold mb-1">
                      {{ invoice.invoice_number }}
                    </CardTitle>
                    <p class="text-xs text-muted-foreground">
                      {{ formatDate(invoice.created_at) }}
                    </p>
                  </div>
                  <Badge
                    :variant="getStatusBadgeVariant(invoice.status)"
                    class="shrink-0"
                  >
                    {{ invoice.status }}
                  </Badge>
                </div>
              </CardHeader>
              <CardContent class="pt-0">
                <div class="space-y-3">
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground flex items-center gap-1">
                      <Calendar class="h-3 w-3" />
                      Due Date
                    </span>
                    <span class="font-medium">
                      {{ formatDate(invoice.due_date) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-muted-foreground flex items-center gap-1">
                      <DollarSign class="h-4 w-4" />
                      Total
                    </span>
                    <span class="text-xl font-bold">
                      {{ invoice.total_formatted || invoice.total }}
                    </span>
                  </div>
                  <div class="flex gap-2 pt-2 border-t">
                    <Button
                      @click.stop="viewInvoice(invoice.id)"
                      variant="outline"
                      size="sm"
                      class="flex-1"
                    >
                      <Eye class="h-3 w-3 mr-1" />
                      View
                    </Button>
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button
                          @click.stop
                          variant="outline"
                          size="sm"
                          class="px-2"
                        >
                          <MoreVertical class="h-3 w-3" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem
                          @click.stop="downloadInvoice(invoice)"
                        >
                          <Download class="h-4 w-4 mr-2" />
                          Download Invoice
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          <!-- Pagination -->
          <div
            v-if="totalPages > 1"
            class="flex items-center justify-center gap-2 mt-6"
          >
            <Button
              @click="loadInvoices(currentPage - 1)"
              :disabled="currentPage === 1"
              variant="outline"
              size="sm"
            >
              <ChevronLeft class="h-4 w-4" />
            </Button>
            <span class="text-sm text-muted-foreground">
              Page {{ currentPage }} of {{ totalPages }}
            </span>
            <Button
              @click="loadInvoices(currentPage + 1)"
              :disabled="currentPage === totalPages"
              variant="outline"
              size="sm"
            >
              <ChevronRight class="h-4 w-4" />
            </Button>
          </div>
        </div>
      </Card>

      <!-- Invoice Details Dialog -->
      <Dialog v-model:open="invoiceDialogOpen">
        <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <div class="flex items-center justify-between">
              <div>
                <DialogTitle>
                  Invoice: {{ selectedInvoice?.invoice_number }}
                </DialogTitle>
                <DialogDescription>
                  Invoice details and items
                </DialogDescription>
              </div>
              <Button
                v-if="selectedInvoice"
                @click="downloadInvoice(selectedInvoice)"
                variant="outline"
                size="sm"
                class="gap-2"
              >
                <Download class="h-4 w-4" />
                Download
              </Button>
            </div>
          </DialogHeader>

          <div
            v-if="loadingInvoice"
            class="flex items-center justify-center py-12"
          >
            <Loader2 class="h-8 w-8 animate-spin" />
          </div>
          <div v-else-if="selectedInvoice" class="space-y-6">
            <!-- Invoice Info -->
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-muted-foreground">Status:</span>
                <div>
                  <Badge
                    :variant="getStatusBadgeVariant(selectedInvoice.status)"
                  >
                    {{ selectedInvoice.status }}
                  </Badge>
                </div>
              </div>
              <div>
                <span class="text-muted-foreground">Due Date:</span>
                <div class="font-medium">
                  {{ formatDate(selectedInvoice.due_date) }}
                </div>
              </div>
              <div v-if="selectedInvoice.paid_at">
                <span class="text-muted-foreground">Paid At:</span>
                <div class="font-medium">
                  {{ formatDate(selectedInvoice.paid_at) }}
                </div>
              </div>
              <div>
                <span class="text-muted-foreground">Created:</span>
                <div class="font-medium">
                  {{ formatDate(selectedInvoice.created_at) }}
                </div>
              </div>
            </div>

            <!-- Items -->
            <div
              v-if="selectedInvoice.items && selectedInvoice.items.length > 0"
            >
              <h3 class="font-semibold mb-3">Items</h3>
              <table class="w-full">
                <thead>
                  <tr class="border-b">
                    <th class="text-left p-2 text-sm font-medium">
                      Description
                    </th>
                    <th class="text-right p-2 text-sm font-medium">Quantity</th>
                    <th class="text-right p-2 text-sm font-medium">
                      Unit Price
                    </th>
                    <th class="text-right p-2 text-sm font-medium">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="item in selectedInvoice.items"
                    :key="item.id"
                    class="border-b"
                  >
                    <td class="p-2">{{ item.description }}</td>
                    <td class="p-2 text-right">{{ item.quantity }}</td>
                    <td class="p-2 text-right">
                      {{ item.unit_price_formatted || item.unit_price }}
                    </td>
                    <td class="p-2 text-right font-medium">
                      {{ item.total_formatted || item.total }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Totals -->
            <div class="border-t pt-4 space-y-2">
              <div class="flex justify-between">
                <span class="text-muted-foreground">Subtotal:</span>
                <span class="font-medium">{{
                  selectedInvoice.subtotal_formatted || selectedInvoice.subtotal
                }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground"
                  >Tax ({{ selectedInvoice.tax_rate }}%):</span
                >
                <span class="font-medium">{{
                  selectedInvoice.tax_amount_formatted ||
                  selectedInvoice.tax_amount
                }}</span>
              </div>
              <div class="flex justify-between text-lg font-bold border-t pt-2">
                <span>Total:</span>
                <span>{{
                  selectedInvoice.total_formatted || selectedInvoice.total
                }}</span>
              </div>
            </div>

            <!-- Notes -->
            <div v-if="selectedInvoice.notes" class="p-4 bg-muted rounded-lg">
              <h4 class="font-semibold mb-2">Notes</h4>
              <p class="text-sm text-muted-foreground">
                {{ selectedInvoice.notes }}
              </p>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  </div>
</template>
