<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
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
  Plus,
  Eye,
  Trash2,
  ChevronLeft,
  ChevronRight,
  Edit,
  X,
  FileText,
  Calendar,
  DollarSign,
} from "lucide-vue-next";
import {
  useAdminBillingAPI,
  type Invoice,
  type User,
} from "@/composables/useBillingAPI";
import { useToast } from "vue-toastification";

const toast = useToast();
const {
  loading,
  getInvoices,
  getInvoice,
  deleteInvoice,
  createInvoice,
  updateInvoice,
  addInvoiceItem,
  updateInvoiceItem,
  deleteInvoiceItem,
  getUsers,
} = useAdminBillingAPI();

const invoices = ref<Invoice[]>([]);
const currentPage = ref(1);
const totalPages = ref(1);
const searchQuery = ref("");
const statusFilter = ref<string>("all");
const selectedInvoice = ref<Invoice | null>(null);
const invoiceDialogOpen = ref(false);
const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const loadingInvoice = ref(false);
const savingInvoice = ref(false);

// User search
const userSearchQuery = ref("");
const userSearchResults = ref<User[]>([]);
const selectedUser = ref<User | null>(null);
const showUserDropdown = ref(false);
const searchingUsers = ref(false);

// Invoice form
const invoiceForm = ref<{
  user_id: string;
  invoice_number: string;
  status: string;
  due_date: string;
  tax_rate: string;
  notes: string;
  items: Array<{
    id?: string;
    description: string;
    quantity: string;
    unit_price: string;
    total: string;
  }>;
}>({
  user_id: "",
  invoice_number: "",
  status: "draft",
  due_date: "",
  tax_rate: "0",
  notes: "",
  items: [],
});

const currentInvoiceId = ref<number | null>(null);
let itemCounter = 0;

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
    const userId =
      searchQuery.value && /^\d+$/.test(searchQuery.value)
        ? parseInt(searchQuery.value)
        : undefined;
    const search: string | undefined =
      searchQuery.value && !/^\d+$/.test(searchQuery.value)
        ? searchQuery.value
        : undefined;
    const response = await getInvoices(
      page,
      20,
      userId,
      statusFilter.value === "all" ? undefined : statusFilter.value,
      search || undefined
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

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const searchUsers = async () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }

  if (!userSearchQuery.value.trim() || selectedUser.value) {
    userSearchResults.value = [];
    showUserDropdown.value = false;
    return;
  }

  searchTimeout = setTimeout(async () => {
    searchingUsers.value = true;
    try {
      const response = await getUsers(1, 10, userSearchQuery.value.trim());
      userSearchResults.value = response.data;
      showUserDropdown.value = response.data.length > 0;
    } catch (err) {
      toast.error(
        err instanceof Error ? err.message : "Failed to search users"
      );
      showUserDropdown.value = false;
    } finally {
      searchingUsers.value = false;
    }
  }, 300);
};

const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement;
  if (!target.closest(".user-search-container")) {
    showUserDropdown.value = false;
  }
};

const selectUser = (user: User) => {
  selectedUser.value = user;
  invoiceForm.value.user_id = user.id.toString();
  userSearchQuery.value = `${user.username} (${user.email})`;
  showUserDropdown.value = false;
  userSearchResults.value = [];
};

const clearUserSelection = () => {
  selectedUser.value = null;
  invoiceForm.value.user_id = "";
  userSearchQuery.value = "";
  showUserDropdown.value = false;
};

const openCreateDialog = () => {
  currentInvoiceId.value = null;
  selectedUser.value = null;
  userSearchQuery.value = "";
  invoiceForm.value = {
    user_id: "",
    invoice_number: "",
    status: "draft",
    due_date: "",
    tax_rate: "0",
    notes: "",
    items: [
      {
        id: `new-${itemCounter++}`,
        description: "",
        quantity: "1",
        unit_price: "0",
        total: "0",
      },
    ],
  };
  createDialogOpen.value = true;
};

const openEditDialog = async (invoiceId: number) => {
  currentInvoiceId.value = invoiceId;
  loadingInvoice.value = true;
  editDialogOpen.value = true;
  try {
    const invoice = await getInvoice(invoiceId);
    // Always set the user if we have user_id, even if username/email aren't available
    if (invoice.user_id) {
      selectedUser.value = {
        id: invoice.user_id,
        username: invoice.username || `User #${invoice.user_id}`,
        email: invoice.email || "",
      };
      if (invoice.username || invoice.email) {
        userSearchQuery.value = `${invoice.username || ""} (${
          invoice.email || ""
        })`;
      } else {
        // If we don't have username/email, try to fetch user info
        try {
          const userResponse = await getUsers(1, 1, invoice.user_id.toString());
          if (userResponse.data.length > 0) {
            const user = userResponse.data[0];
            if (user) {
              selectedUser.value = {
                id: user.id,
                username: user.username,
                email: user.email,
              };
              userSearchQuery.value = `${user.username} (${user.email})`;
            } else {
              userSearchQuery.value = `User #${invoice.user_id}`;
            }
          } else {
            userSearchQuery.value = `User #${invoice.user_id}`;
          }
        } catch {
          userSearchQuery.value = `User #${invoice.user_id}`;
        }
      }
    } else {
      selectedUser.value = null;
      userSearchQuery.value = "";
    }
    invoiceForm.value = {
      user_id: invoice.user_id.toString(),
      invoice_number: invoice.invoice_number,
      status: invoice.status,
      due_date: invoice.due_date ? invoice.due_date.split("T")[0] || "" : "",
      tax_rate: invoice.tax_rate.toString(),
      notes: invoice.notes || "",
      items:
        invoice.items?.map((item) => ({
          id: item.id.toString(),
          description: item.description,
          quantity: item.quantity.toString(),
          unit_price: item.unit_price.toString(),
          total: item.total.toString(),
        })) || [],
    };
    if (invoiceForm.value.items.length === 0) {
      addItemRow();
    }
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load invoice");
    editDialogOpen.value = false;
  } finally {
    loadingInvoice.value = false;
  }
};

const addItemRow = () => {
  invoiceForm.value.items.push({
    id: `new-${itemCounter++}`,
    description: "",
    quantity: "1",
    unit_price: "0",
    total: "0",
  });
};

const removeItemRow = (index: number) => {
  invoiceForm.value.items.splice(index, 1);
};

const updateItemTotal = (index: number) => {
  const item = invoiceForm.value.items[index];
  if (!item) return;
  const quantity = parseFloat(item.quantity) || 0;
  const unitPrice = parseFloat(item.unit_price) || 0;
  item.total = (quantity * unitPrice).toFixed(2);
};

const saveInvoice = async () => {
  savingInvoice.value = true;
  try {
    const userId = parseInt(invoiceForm.value.user_id);
    if (!userId || userId <= 0) {
      toast.error("Please enter a valid user ID");
      return;
    }

    const items = invoiceForm.value.items
      .filter((item) => item.description.trim())
      .map((item, index) => ({
        description: item.description.trim(),
        quantity: parseFloat(item.quantity) || 1,
        unit_price: parseFloat(item.unit_price) || 0,
        sort_order: index,
      }));

    if (items.length === 0) {
      toast.error("Please add at least one invoice item");
      return;
    }

    const invoiceData = {
      user_id: userId,
      invoice_number: invoiceForm.value.invoice_number.trim() || undefined,
      status: invoiceForm.value.status as
        | "draft"
        | "pending"
        | "paid"
        | "overdue"
        | "cancelled",
      due_date: invoiceForm.value.due_date || undefined,
      tax_rate: parseFloat(invoiceForm.value.tax_rate) || 0,
      notes: invoiceForm.value.notes.trim() || undefined,
      items,
    };

    if (currentInvoiceId.value) {
      await updateInvoice(
        currentInvoiceId.value,
        invoiceData as Partial<Invoice>
      );
      toast.success("Invoice updated successfully!");
      editDialogOpen.value = false;
    } else {
      await createInvoice(invoiceData as Parameters<typeof createInvoice>[0]);
      toast.success("Invoice created successfully!");
      createDialogOpen.value = false;
    }
    loadInvoices(currentPage.value);
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to save invoice");
  } finally {
    savingInvoice.value = false;
  }
};

const handleDeleteInvoice = async (invoiceId: number) => {
  if (!confirm("Are you sure you want to delete this invoice?")) return;
  try {
    await deleteInvoice(invoiceId);
    toast.success("Invoice deleted successfully");
    loadInvoices(currentPage.value);
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to delete invoice"
    );
  }
};

const handleAddItem = async (invoiceId: number) => {
  const description = prompt("Item description:");
  if (!description) return;
  const quantity = parseFloat(prompt("Quantity:", "1") || "1") || 1;
  const unitPrice = parseFloat(prompt("Unit price:", "0") || "0") || 0;

  try {
    await addInvoiceItem(invoiceId, {
      description,
      quantity,
      unit_price: unitPrice,
    });
    toast.success("Item added successfully!");
    await viewInvoice(invoiceId);
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to add item");
  }
};

const handleEditItem = async (invoiceId: number, itemId: number) => {
  const invoice = await getInvoice(invoiceId);
  const item = invoice.items?.find((i) => i.id === itemId);
  if (!item) return;

  const description = prompt("Item description:", item.description);
  if (!description) return;
  const quantity =
    parseFloat(prompt("Quantity:", item.quantity.toString()) || "1") || 1;
  const unitPrice =
    parseFloat(prompt("Unit price:", item.unit_price.toString()) || "0") || 0;

  try {
    await updateInvoiceItem(invoiceId, itemId, {
      description,
      quantity,
      unit_price: unitPrice,
    });
    toast.success("Item updated successfully!");
    await viewInvoice(invoiceId);
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to update item");
  }
};

const handleDeleteItem = async (invoiceId: number, itemId: number) => {
  if (!confirm("Are you sure you want to delete this item?")) return;
  try {
    await deleteInvoiceItem(invoiceId, itemId);
    toast.success("Item deleted successfully!");
    await viewInvoice(invoiceId);
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to delete item");
  }
};

const formatDate = (dateString: string | null) => {
  if (!dateString) return "N/A";
  return new Date(dateString).toLocaleDateString();
};

onMounted(() => {
  loadInvoices(1);
  document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4">
    <div class="container mx-auto max-w-6xl">
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-semibold">Invoice Management</h1>
          <p class="text-sm text-muted-foreground">
            Create, edit, and manage invoices
          </p>
        </div>
        <Button @click="openCreateDialog" class="gap-2">
          <Plus class="h-4 w-4" />
          Create Invoice
        </Button>
      </div>

      <!-- Filters -->
      <Card>
        <div class="p-4 flex gap-3 items-center flex-wrap">
          <div class="flex-1 min-w-[200px]">
            <Input
              v-model="searchQuery"
              placeholder="Search by username, email, invoice ID, or invoice number..."
              @keyup.enter="loadInvoices(1)"
            />
          </div>
          <Select v-model="statusFilter" @update:model-value="loadInvoices(1)">
            <SelectTrigger class="w-[200px]">
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
          <Button @click="loadInvoices(1)" variant="outline">Search</Button>
          <Button @click="loadInvoices(currentPage)" variant="outline"
            >Refresh</Button
          >
        </div>
      </Card>

      <!-- Invoices List -->
      <Card>
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
          <div v-else class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b">
                  <th
                    class="text-left p-4 text-sm font-medium text-muted-foreground"
                  >
                    Invoice #
                  </th>
                  <th
                    class="text-left p-4 text-sm font-medium text-muted-foreground"
                  >
                    User
                  </th>
                  <th
                    class="text-left p-4 text-sm font-medium text-muted-foreground"
                  >
                    Status
                  </th>
                  <th
                    class="text-left p-4 text-sm font-medium text-muted-foreground"
                  >
                    Due Date
                  </th>
                  <th
                    class="text-left p-4 text-sm font-medium text-muted-foreground"
                  >
                    Total
                  </th>
                  <th
                    class="text-left p-4 text-sm font-medium text-muted-foreground"
                  >
                    Created
                  </th>
                  <th
                    class="text-left p-4 text-sm font-medium text-muted-foreground"
                  >
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="invoice in invoices"
                  :key="invoice.id"
                  class="border-b hover:bg-muted/50"
                >
                  <td class="p-2 font-medium text-xs">
                    <div class="flex items-center gap-1">
                      <FileText class="h-3 w-3 text-muted-foreground" />
                      {{ invoice.invoice_number }}
                    </div>
                  </td>
                  <td class="p-2 text-xs">
                    {{ invoice.username || "N/A" }} ({{ invoice.user_id }})
                  </td>
                  <td class="p-2">
                    <Badge
                      :variant="getStatusBadgeVariant(invoice.status)"
                      class="text-xs"
                    >
                      {{ invoice.status }}
                    </Badge>
                  </td>
                  <td class="p-2 text-xs">
                    <div class="flex items-center gap-1">
                      <Calendar class="h-3 w-3 text-muted-foreground" />
                      {{ formatDate(invoice.due_date) }}
                    </div>
                  </td>
                  <td class="p-2 font-medium text-xs">
                    <div class="flex items-center gap-1">
                      <DollarSign class="h-3 w-3 text-muted-foreground" />
                      {{ invoice.total_formatted || invoice.total }}
                    </div>
                  </td>
                  <td class="p-2 text-xs">
                    <div class="flex items-center gap-1">
                      <Calendar class="h-3 w-3 text-muted-foreground" />
                      {{ formatDate(invoice.created_at) }}
                    </div>
                  </td>
                  <td class="p-2">
                    <div class="flex gap-1">
                      <Button
                        @click="viewInvoice(invoice.id)"
                        variant="outline"
                        size="sm"
                        class="h-7 text-xs"
                      >
                        <Eye class="h-3 w-3" />
                      </Button>
                      <Button
                        @click="openEditDialog(invoice.id)"
                        variant="outline"
                        size="sm"
                        class="h-7 text-xs"
                      >
                        <Edit class="h-3 w-3" />
                      </Button>
                      <Button
                        @click="handleDeleteInvoice(invoice.id)"
                        variant="destructive"
                        size="sm"
                        class="h-7 text-xs"
                      >
                        <Trash2 class="h-3 w-3" />
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

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
        </div>
      </Card>

      <!-- Invoice Details Dialog -->
      <Dialog v-model:open="invoiceDialogOpen">
        <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              Invoice: {{ selectedInvoice?.invoice_number }}
            </DialogTitle>
            <DialogDescription> Invoice details and items </DialogDescription>
          </DialogHeader>

          <div
            v-if="loadingInvoice"
            class="flex items-center justify-center py-12"
          >
            <Loader2 class="h-8 w-8 animate-spin" />
          </div>
          <div v-else-if="selectedInvoice" class="space-y-6">
            <!-- Billing Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Customer Info -->
              <div class="border rounded-lg p-4">
                <h3
                  class="font-semibold mb-3 text-sm uppercase text-muted-foreground"
                >
                  Bill To
                </h3>
                <div class="space-y-1 text-sm">
                  <p
                    v-if="selectedInvoice.customer?.username"
                    class="font-medium"
                  >
                    {{ selectedInvoice.customer.username }}
                  </p>
                  <p
                    v-if="selectedInvoice.customer?.email"
                    class="text-muted-foreground"
                  >
                    {{ selectedInvoice.customer.email }}
                  </p>
                  <p class="text-muted-foreground">
                    User ID: {{ selectedInvoice.user_id }}
                  </p>
                  <div
                    v-if="selectedInvoice.customer?.billing_info"
                    class="mt-3 space-y-1"
                  >
                    <p v-if="selectedInvoice.customer.billing_info.full_name">
                      {{ selectedInvoice.customer.billing_info.full_name }}
                    </p>
                    <p
                      v-if="selectedInvoice.customer.billing_info.company_name"
                    >
                      {{ selectedInvoice.customer.billing_info.company_name }}
                    </p>
                    <p
                      v-if="selectedInvoice.customer.billing_info.address_line1"
                    >
                      {{ selectedInvoice.customer.billing_info.address_line1 }}
                    </p>
                    <p
                      v-if="selectedInvoice.customer.billing_info.address_line2"
                    >
                      {{ selectedInvoice.customer.billing_info.address_line2 }}
                    </p>
                    <p
                      v-if="
                        selectedInvoice.customer.billing_info.city ||
                        selectedInvoice.customer.billing_info.state ||
                        selectedInvoice.customer.billing_info.postal_code
                      "
                    >
                      {{
                        [
                          selectedInvoice.customer.billing_info.city,
                          selectedInvoice.customer.billing_info.state,
                          selectedInvoice.customer.billing_info.postal_code,
                        ]
                          .filter(Boolean)
                          .join(", ")
                      }}
                    </p>
                    <p
                      v-if="selectedInvoice.customer.billing_info.country_code"
                    >
                      {{ selectedInvoice.customer.billing_info.country_code }}
                    </p>
                    <p v-if="selectedInvoice.customer.billing_info.vat_id">
                      VAT ID: {{ selectedInvoice.customer.billing_info.vat_id }}
                    </p>
                    <p v-if="selectedInvoice.customer.billing_info.phone">
                      {{ selectedInvoice.customer.billing_info.phone }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Admin/Company Info -->
              <div class="border rounded-lg p-4">
                <h3
                  class="font-semibold mb-3 text-sm uppercase text-muted-foreground"
                >
                  From
                </h3>
                <div
                  v-if="selectedInvoice.admin?.billing_info"
                  class="space-y-1 text-sm"
                >
                  <p
                    v-if="selectedInvoice.admin.billing_info.full_name"
                    class="font-medium"
                  >
                    {{ selectedInvoice.admin.billing_info.full_name }}
                  </p>
                  <p
                    v-if="selectedInvoice.admin.billing_info.company_name"
                    class="font-medium"
                  >
                    {{ selectedInvoice.admin.billing_info.company_name }}
                  </p>
                  <p v-if="selectedInvoice.admin.billing_info.address_line1">
                    {{ selectedInvoice.admin.billing_info.address_line1 }}
                  </p>
                  <p v-if="selectedInvoice.admin.billing_info.address_line2">
                    {{ selectedInvoice.admin.billing_info.address_line2 }}
                  </p>
                  <p
                    v-if="
                      selectedInvoice.admin.billing_info.city ||
                      selectedInvoice.admin.billing_info.state ||
                      selectedInvoice.admin.billing_info.postal_code
                    "
                  >
                    {{
                      [
                        selectedInvoice.admin.billing_info.city,
                        selectedInvoice.admin.billing_info.state,
                        selectedInvoice.admin.billing_info.postal_code,
                      ]
                        .filter(Boolean)
                        .join(", ")
                    }}
                  </p>
                  <p v-if="selectedInvoice.admin.billing_info.country_code">
                    {{ selectedInvoice.admin.billing_info.country_code }}
                  </p>
                  <p v-if="selectedInvoice.admin.billing_info.vat_id">
                    VAT ID: {{ selectedInvoice.admin.billing_info.vat_id }}
                  </p>
                  <p v-if="selectedInvoice.admin.billing_info.phone">
                    {{ selectedInvoice.admin.billing_info.phone }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Invoice Info -->
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-muted-foreground">User:</span>
                <div class="font-medium">
                  {{ selectedInvoice.username || "N/A" }} (ID:
                  {{ selectedInvoice.user_id }})
                </div>
              </div>
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
            <div>
              <div class="flex justify-between items-center mb-3">
                <h3 class="font-semibold">Items</h3>
                <Button
                  @click="handleAddItem(selectedInvoice.id)"
                  size="sm"
                  variant="outline"
                >
                  <Plus class="h-3 w-3 mr-1" />
                  Add Item
                </Button>
              </div>
              <div
                v-if="selectedInvoice.items && selectedInvoice.items.length > 0"
              >
                <table class="w-full">
                  <thead>
                    <tr class="border-b">
                      <th class="text-left p-2 text-sm font-medium">
                        Description
                      </th>
                      <th class="text-right p-2 text-sm font-medium">
                        Quantity
                      </th>
                      <th class="text-right p-2 text-sm font-medium">
                        Unit Price
                      </th>
                      <th class="text-right p-2 text-sm font-medium">Total</th>
                      <th class="text-right p-2 text-sm font-medium">
                        Actions
                      </th>
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
                      <td class="p-2 text-right">
                        <div class="flex gap-1 justify-end">
                          <Button
                            @click="handleEditItem(selectedInvoice.id, item.id)"
                            variant="outline"
                            size="sm"
                            class="h-6 text-xs"
                          >
                            <Edit class="h-3 w-3" />
                          </Button>
                          <Button
                            @click="
                              handleDeleteItem(selectedInvoice.id, item.id)
                            "
                            variant="destructive"
                            size="sm"
                            class="h-6 text-xs"
                          >
                            <Trash2 class="h-3 w-3" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="text-center py-4 text-muted-foreground">
                No items yet
              </div>
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

      <!-- Create/Edit Invoice Dialog -->
      <Dialog v-model:open="createDialogOpen">
        <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Create Invoice</DialogTitle>
            <DialogDescription>
              Create a new invoice for a user
            </DialogDescription>
          </DialogHeader>

          <form @submit.prevent="saveInvoice" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="relative user-search-container">
                <Label for="user_search">User *</Label>
                <div class="relative">
                  <Input
                    id="user_search"
                    v-model="userSearchQuery"
                    placeholder="Search by username or email..."
                    @input="searchUsers"
                    @focus="searchUsers"
                    @click.stop
                    required
                  />
                  <div
                    v-if="showUserDropdown && userSearchResults.length > 0"
                    class="absolute z-50 w-full mt-1 bg-popover border rounded-md shadow-lg max-h-60 overflow-auto"
                    @click.stop
                  >
                    <div
                      v-for="user in userSearchResults"
                      :key="user.id"
                      @click="selectUser(user)"
                      class="p-3 hover:bg-muted cursor-pointer border-b last:border-b-0"
                    >
                      <div class="font-medium">{{ user.username }}</div>
                      <div class="text-sm text-muted-foreground">
                        {{ user.email }}
                      </div>
                    </div>
                  </div>
                  <div
                    v-if="selectedUser"
                    class="absolute right-2 top-1/2 -translate-y-1/2"
                  >
                    <Button
                      type="button"
                      @click.stop="clearUserSelection"
                      variant="ghost"
                      size="sm"
                      class="h-6 w-6 p-0"
                    >
                      <X class="h-3 w-3" />
                    </Button>
                  </div>
                </div>
                <input v-model="invoiceForm.user_id" type="hidden" required />
              </div>
              <div>
                <Label for="invoice_number">Invoice Number</Label>
                <Input
                  id="invoice_number"
                  v-model="invoiceForm.invoice_number"
                  placeholder="Auto-generated if empty"
                />
              </div>
              <div>
                <Label for="status">Status</Label>
                <Select v-model="invoiceForm.status">
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="draft">Draft</SelectItem>
                    <SelectItem value="pending">Pending</SelectItem>
                    <SelectItem value="paid">Paid</SelectItem>
                    <SelectItem value="overdue">Overdue</SelectItem>
                    <SelectItem value="cancelled">Cancelled</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label for="due_date">Due Date</Label>
                <Input
                  id="due_date"
                  v-model="invoiceForm.due_date"
                  type="date"
                />
              </div>
              <div>
                <Label for="tax_rate">Tax Rate (%)</Label>
                <Input
                  id="tax_rate"
                  v-model="invoiceForm.tax_rate"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                />
              </div>
            </div>

            <div>
              <Label for="notes">Notes</Label>
              <Textarea
                id="notes"
                v-model="invoiceForm.notes"
                placeholder="Additional notes..."
              />
            </div>

            <div>
              <div class="flex justify-between items-center mb-3">
                <Label>Invoice Items *</Label>
                <Button
                  type="button"
                  @click="addItemRow"
                  variant="outline"
                  size="sm"
                >
                  <Plus class="h-3 w-3 mr-1" />
                  Add Item
                </Button>
              </div>
              <div class="space-y-3">
                <div
                  v-for="(item, index) in invoiceForm.items"
                  :key="item.id"
                  class="border rounded-lg p-4 space-y-3"
                >
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-3">
                      <Label :for="`item-description-${index}`"
                        >Item Description *</Label
                      >
                      <Input
                        :id="`item-description-${index}`"
                        v-model="item.description"
                        placeholder="e.g., Web Hosting - Monthly"
                        required
                      />
                    </div>
                    <div>
                      <Label :for="`item-quantity-${index}`">Quantity</Label>
                      <Input
                        :id="`item-quantity-${index}`"
                        v-model="item.quantity"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="1"
                        @input="updateItemTotal(index)"
                      />
                    </div>
                    <div>
                      <Label :for="`item-unit-price-${index}`"
                        >Unit Price</Label
                      >
                      <Input
                        :id="`item-unit-price-${index}`"
                        v-model="item.unit_price"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        @input="updateItemTotal(index)"
                      />
                    </div>
                    <div>
                      <Label :for="`item-total-${index}`">Line Total</Label>
                      <Input
                        :id="`item-total-${index}`"
                        v-model="item.total"
                        readonly
                        placeholder="0.00"
                        class="bg-muted"
                      />
                    </div>
                  </div>
                  <div class="flex justify-end">
                    <Button
                      type="button"
                      @click="removeItemRow(index)"
                      variant="destructive"
                      size="sm"
                    >
                      <Trash2 class="h-3 w-3 mr-1" />
                      Remove Item
                    </Button>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-2">
              <Button
                type="button"
                @click="createDialogOpen = false"
                variant="outline"
              >
                Cancel
              </Button>
              <Button type="submit" :disabled="savingInvoice">
                <Loader2
                  v-if="savingInvoice"
                  class="h-4 w-4 mr-2 animate-spin"
                />
                Create Invoice
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      <!-- Edit Invoice Dialog -->
      <Dialog v-model:open="editDialogOpen">
        <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Edit Invoice</DialogTitle>
            <DialogDescription>
              Update invoice details and items
            </DialogDescription>
          </DialogHeader>

          <div
            v-if="loadingInvoice"
            class="flex items-center justify-center py-12"
          >
            <Loader2 class="h-8 w-8 animate-spin" />
          </div>
          <form v-else @submit.prevent="saveInvoice" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="relative user-search-container">
                <Label for="edit-user_search">User *</Label>
                <div class="relative">
                  <Input
                    id="edit-user_search"
                    v-model="userSearchQuery"
                    placeholder="Search by username or email..."
                    @input="searchUsers"
                    @focus="searchUsers"
                    @click.stop
                    required
                  />
                  <div
                    v-if="showUserDropdown && userSearchResults.length > 0"
                    class="absolute z-50 w-full mt-1 bg-popover border rounded-md shadow-lg max-h-60 overflow-auto"
                    @click.stop
                  >
                    <div
                      v-for="user in userSearchResults"
                      :key="user.id"
                      @click="selectUser(user)"
                      class="p-3 hover:bg-muted cursor-pointer border-b last:border-b-0"
                    >
                      <div class="font-medium">{{ user.username }}</div>
                      <div class="text-sm text-muted-foreground">
                        {{ user.email }}
                      </div>
                    </div>
                  </div>
                  <div
                    v-if="selectedUser"
                    class="absolute right-2 top-1/2 -translate-y-1/2"
                  >
                    <Button
                      type="button"
                      @click.stop="clearUserSelection"
                      variant="ghost"
                      size="sm"
                      class="h-6 w-6 p-0"
                    >
                      <X class="h-3 w-3" />
                    </Button>
                  </div>
                </div>
                <input v-model="invoiceForm.user_id" type="hidden" required />
              </div>
              <div>
                <Label for="edit-invoice_number">Invoice Number</Label>
                <Input
                  id="edit-invoice_number"
                  v-model="invoiceForm.invoice_number"
                  placeholder="Auto-generated if empty"
                />
              </div>
              <div>
                <Label for="edit-status">Status</Label>
                <Select v-model="invoiceForm.status">
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="draft">Draft</SelectItem>
                    <SelectItem value="pending">Pending</SelectItem>
                    <SelectItem value="paid">Paid</SelectItem>
                    <SelectItem value="overdue">Overdue</SelectItem>
                    <SelectItem value="cancelled">Cancelled</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label for="edit-due_date">Due Date</Label>
                <Input
                  id="edit-due_date"
                  v-model="invoiceForm.due_date"
                  type="date"
                />
              </div>
              <div>
                <Label for="edit-tax_rate">Tax Rate (%)</Label>
                <Input
                  id="edit-tax_rate"
                  v-model="invoiceForm.tax_rate"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                />
              </div>
            </div>

            <div>
              <Label for="edit-notes">Notes</Label>
              <Textarea
                id="edit-notes"
                v-model="invoiceForm.notes"
                placeholder="Additional notes..."
              />
            </div>

            <div>
              <div class="flex justify-between items-center mb-3">
                <Label>Invoice Items *</Label>
                <Button
                  type="button"
                  @click="addItemRow"
                  variant="outline"
                  size="sm"
                >
                  <Plus class="h-3 w-3 mr-1" />
                  Add Item
                </Button>
              </div>
              <div class="space-y-3">
                <div
                  v-for="(item, index) in invoiceForm.items"
                  :key="item.id"
                  class="border rounded-lg p-4 space-y-3"
                >
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-3">
                      <Label :for="`item-description-${index}`"
                        >Item Description *</Label
                      >
                      <Input
                        :id="`item-description-${index}`"
                        v-model="item.description"
                        placeholder="e.g., Web Hosting - Monthly"
                        required
                      />
                    </div>
                    <div>
                      <Label :for="`item-quantity-${index}`">Quantity</Label>
                      <Input
                        :id="`item-quantity-${index}`"
                        v-model="item.quantity"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="1"
                        @input="updateItemTotal(index)"
                      />
                    </div>
                    <div>
                      <Label :for="`item-unit-price-${index}`"
                        >Unit Price</Label
                      >
                      <Input
                        :id="`item-unit-price-${index}`"
                        v-model="item.unit_price"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        @input="updateItemTotal(index)"
                      />
                    </div>
                    <div>
                      <Label :for="`item-total-${index}`">Line Total</Label>
                      <Input
                        :id="`item-total-${index}`"
                        v-model="item.total"
                        readonly
                        placeholder="0.00"
                        class="bg-muted"
                      />
                    </div>
                  </div>
                  <div class="flex justify-end">
                    <Button
                      type="button"
                      @click="removeItemRow(index)"
                      variant="destructive"
                      size="sm"
                    >
                      <Trash2 class="h-3 w-3 mr-1" />
                      Remove Item
                    </Button>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-2">
              <Button
                type="button"
                @click="editDialogOpen = false"
                variant="outline"
              >
                Cancel
              </Button>
              <Button type="submit" :disabled="savingInvoice">
                <Loader2
                  v-if="savingInvoice"
                  class="h-4 w-4 mr-2 animate-spin"
                />
                Update Invoice
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  </div>
</template>
