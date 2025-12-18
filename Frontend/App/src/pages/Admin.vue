<script setup lang="ts">
import { ref, onMounted, watch } from "vue";
import { Card } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Loader2,
  Users,
  DollarSign,
  Settings,
  Eye,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
} from "lucide-vue-next";
import {
  useAdminBillingAPI,
  type Statistics,
  type User,
  type UserWithCredits,
  type BillingInfo,
  type CurrencySettings,
  type BillingCoreSettings,
} from "@/composables/useBillingAPI";
import { useToast } from "vue-toastification";

const toast = useToast();
const {
  getStatistics,
  getUsers,
  getUserCredits,
  getUserBillingInfo,
  addUserCredits,
  removeUserCredits,
  setUserCredits,
  updateUserBillingInfo,
  getCurrencySettings,
  updateCurrencySettings,
  getAdminBillingInfo,
  updateAdminBillingInfo,
  getSettings,
  updateSettings,
} = useAdminBillingAPI();

// Statistics
const statistics = ref<Statistics | null>(null);
const loadingStats = ref(false);

// Users
const users = ref<User[]>([]);
const currentUserPage = ref(1);
const totalUserPages = ref(1);
const userSearch = ref("");
const loadingUsers = ref(false);
const selectedUser = ref<UserWithCredits | null>(null);
const selectedUserBillingInfo = ref<BillingInfo | null>(null);
const userDialogOpen = ref(false);
const creditAmount = ref("");
const loadingUserDetails = ref(false);
const showFeatherPanelIframe = ref(false);
const featherPanelUserUrl = ref<string | null>(null);

// Settings
const billingCoreSettings = ref<BillingCoreSettings | null>(null);
const currencySettings = ref<CurrencySettings | null>(null);
const adminBillingInfo = ref<BillingInfo | null>(null);
const loadingSettings = ref(false);
const savingSettings = ref(false);
const tokensPerCurrency = ref<string>("1");

// Active tab
const activeTab = ref("statistics");

// Watch for tab changes to load data
watch(activeTab, (newTab) => {
  if (newTab === "statistics" && !statistics.value) {
    loadStatistics();
  } else if (newTab === "users" && users.value.length === 0) {
    loadUsers();
  } else if (newTab === "settings") {
    if (!billingCoreSettings.value) loadSettings();
    if (!currencySettings.value) loadCurrencySettings();
    if (!adminBillingInfo.value) loadAdminBillingInfo();
  }
});

const loadStatistics = async () => {
  loadingStats.value = true;
  try {
    statistics.value = await getStatistics();
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to load statistics"
    );
  } finally {
    loadingStats.value = false;
  }
};

const loadUsers = async (page: number = 1) => {
  currentUserPage.value = page;
  loadingUsers.value = true;
  try {
    const response = await getUsers(page, 20, userSearch.value || undefined);
    users.value = response.data;
    totalUserPages.value = response.meta.pagination.total_pages;
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load users");
  } finally {
    loadingUsers.value = false;
  }
};

const viewUser = async (userId: number) => {
  loadingUserDetails.value = true;
  userDialogOpen.value = true;
  try {
    const [creditsData, billingData] = await Promise.all([
      getUserCredits(userId),
      getUserBillingInfo(userId),
    ]);
    selectedUser.value = creditsData;
    selectedUserBillingInfo.value = billingData;
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to load user details"
    );
    userDialogOpen.value = false;
  } finally {
    loadingUserDetails.value = false;
  }
};

const handleAddCredits = async () => {
  if (!selectedUser.value) return;
  const amount = parseInt(creditAmount.value);
  if (!amount || amount <= 0) {
    toast.error("Please enter a valid amount");
    return;
  }
  try {
    await addUserCredits(selectedUser.value.id, amount);
    toast.success("Credits added successfully!");
    creditAmount.value = "";
    await viewUser(selectedUser.value.id);
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to add credits");
  }
};

const handleRemoveCredits = async () => {
  if (!selectedUser.value) return;
  const amount = parseInt(creditAmount.value);
  if (!amount || amount <= 0) {
    toast.error("Please enter a valid amount");
    return;
  }
  try {
    await removeUserCredits(selectedUser.value.id, amount);
    toast.success("Credits removed successfully!");
    creditAmount.value = "";
    await viewUser(selectedUser.value.id);
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to remove credits"
    );
  }
};

const handleSetCredits = async () => {
  if (!selectedUser.value) return;
  const amount = parseInt(creditAmount.value);
  if (amount < 0) {
    toast.error("Please enter a valid amount (>= 0)");
    return;
  }
  try {
    await setUserCredits(selectedUser.value.id, amount);
    toast.success("Credits set successfully!");
    creditAmount.value = "";
    await viewUser(selectedUser.value.id);
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to set credits");
  }
};

const saveUserBillingInfo = async (event: Event) => {
  event.preventDefault();
  if (!selectedUser.value) return;
  try {
    const form = event.target as HTMLFormElement;
    const formData = new FormData(form);
    const data: Partial<BillingInfo> = {
      full_name: formData.get("full_name")?.toString().trim() || null,
      company_name: formData.get("company_name")?.toString().trim() || null,
      address_line1: formData.get("address_line1")?.toString().trim() || null,
      address_line2: formData.get("address_line2")?.toString().trim() || null,
      city: formData.get("city")?.toString().trim() || null,
      state: formData.get("state")?.toString().trim() || null,
      postal_code: formData.get("postal_code")?.toString().trim() || null,
      country_code:
        formData.get("country_code")?.toString().trim().toUpperCase() || null,
      vat_id: formData.get("vat_id")?.toString().trim() || null,
      phone: formData.get("phone")?.toString().trim() || null,
    };
    await updateUserBillingInfo(selectedUser.value.id, data);
    toast.success("Billing info saved successfully!");
    await viewUser(selectedUser.value.id);
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to save billing info"
    );
  }
};

const loadSettings = async () => {
  loadingSettings.value = true;
  try {
    billingCoreSettings.value = await getSettings();
    if (billingCoreSettings.value?.tokens_per_currency) {
      tokensPerCurrency.value = billingCoreSettings.value.tokens_per_currency;
    }
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load settings");
  } finally {
    loadingSettings.value = false;
  }
};

const saveCurrencySettings = async () => {
  if (!billingCoreSettings.value || !currencySettings.value) return;
  savingSettings.value = true;
  try {
    const creditsModeSelect = document.getElementById(
      "credits-mode-select"
    ) as HTMLSelectElement;
    const currencySelect = document.getElementById(
      "default-currency-select"
    ) as HTMLSelectElement;

    if (!creditsModeSelect || !currencySelect) return;

    // Prepare settings update
    const settingsUpdate: any = {
      credits_mode: creditsModeSelect.value as "currency" | "token",
    };

    // Only include tokens_per_currency if in token mode
    if (creditsModeSelect.value === "token") {
      if (tokensPerCurrency.value && tokensPerCurrency.value.trim()) {
        settingsUpdate.tokens_per_currency = tokensPerCurrency.value.trim();
      }
    }

    // Save both settings
    await Promise.all([
      updateSettings(settingsUpdate),
      updateCurrencySettings(currencySelect.value),
    ]);

    toast.success("Currency settings saved!");
    await Promise.all([loadSettings(), loadCurrencySettings()]);
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to save currency settings"
    );
  } finally {
    savingSettings.value = false;
  }
};

const loadCurrencySettings = async () => {
  loadingSettings.value = true;
  try {
    currencySettings.value = await getCurrencySettings();
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to load currency settings"
    );
  } finally {
    loadingSettings.value = false;
  }
};

const loadAdminBillingInfo = async () => {
  loadingSettings.value = true;
  try {
    adminBillingInfo.value = await getAdminBillingInfo();
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to load admin billing info"
    );
  } finally {
    loadingSettings.value = false;
  }
};

const saveAdminBillingInfo = async (event: Event) => {
  event.preventDefault();
  savingSettings.value = true;
  try {
    const form = event.target as HTMLFormElement;
    const formData = new FormData(form);
    const data: Partial<BillingInfo> = {
      full_name: formData.get("admin-full_name")?.toString().trim() || null,
      company_name:
        formData.get("admin-company_name")?.toString().trim() || null,
      address_line1:
        formData.get("admin-address_line1")?.toString().trim() || null,
      address_line2:
        formData.get("admin-address_line2")?.toString().trim() || null,
      city: formData.get("admin-city")?.toString().trim() || null,
      state: formData.get("admin-state")?.toString().trim() || null,
      postal_code: formData.get("admin-postal_code")?.toString().trim() || null,
      country_code:
        formData.get("admin-country_code")?.toString().trim().toUpperCase() ||
        null,
      vat_id: formData.get("admin-vat_id")?.toString().trim() || null,
      phone: formData.get("admin-phone")?.toString().trim() || null,
    };
    await updateAdminBillingInfo(data);
    toast.success("Admin billing info saved successfully!");
    await loadAdminBillingInfo();
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to save admin billing info"
    );
  } finally {
    savingSettings.value = false;
  }
};

const openFeatherPanelUserEdit = (uuid: string) => {
  window.open(`/admin/users/${uuid}/edit`, "_blank");
};

const openFeatherPanelUserEditInIframe = (uuid: string) => {
  featherPanelUserUrl.value = `/admin/users/${uuid}/edit`;
  showFeatherPanelIframe.value = true;
};

onMounted(() => {
  loadStatistics();
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4">
    <div class="container mx-auto max-w-6xl">
      <div class="mb-6">
        <h1 class="text-2xl font-semibold">Billing Core - Admin</h1>
        <p class="text-sm text-muted-foreground">
          Manage billing system, credits, and user billing info
        </p>
      </div>

      <Tabs v-model="activeTab" class="w-full">
        <TabsList class="grid w-full grid-cols-3">
          <TabsTrigger value="statistics">
            <DollarSign class="h-4 w-4 mr-2" />
            Statistics
          </TabsTrigger>
          <TabsTrigger value="users">
            <Users class="h-4 w-4 mr-2" />
            Users
          </TabsTrigger>
          <TabsTrigger value="settings">
            <Settings class="h-4 w-4 mr-2" />
            Settings
          </TabsTrigger>
        </TabsList>

        <TabsContent value="statistics" class="mt-4">
          <Card>
            <div class="p-6">
              <div
                v-if="loadingStats"
                class="flex items-center justify-center py-12"
              >
                <Loader2 class="h-8 w-8 animate-spin" />
              </div>
              <div
                v-else-if="statistics"
                class="grid grid-cols-1 md:grid-cols-4 gap-4"
              >
                <div class="p-4 border rounded-lg">
                  <div class="text-sm text-muted-foreground mb-1">
                    Users with Billing
                  </div>
                  <div class="text-2xl font-bold">
                    {{ statistics.users.total_with_billing }}
                  </div>
                </div>
                <div class="p-4 border rounded-lg">
                  <div class="text-sm text-muted-foreground mb-1">
                    Users with Credits
                  </div>
                  <div class="text-2xl font-bold">
                    {{ statistics.users.with_credits }}
                  </div>
                </div>
                <div class="p-4 border rounded-lg">
                  <div class="text-sm text-muted-foreground mb-1">
                    Total Credits
                  </div>
                  <div class="text-2xl font-bold">
                    {{ statistics.credits.total_formatted }}
                  </div>
                </div>
                <div class="p-4 border rounded-lg">
                  <div class="text-sm text-muted-foreground mb-1">
                    Average per User
                  </div>
                  <div class="text-2xl font-bold">
                    {{ statistics.credits.average_per_user_formatted }}
                  </div>
                </div>
              </div>
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="users" class="mt-4">
          <Card>
            <div class="p-6">
              <div class="flex gap-3 items-center mb-4 flex-wrap">
                <Input
                  v-model="userSearch"
                  placeholder="Search by username, email, or UUID..."
                  class="flex-1 min-w-[200px]"
                  @keyup.enter="loadUsers(1)"
                />
                <Button @click="loadUsers(1)">Search</Button>
                <Button @click="loadUsers(currentUserPage)" variant="outline"
                  >Refresh</Button
                >
              </div>

              <div
                v-if="loadingUsers && users.length === 0"
                class="flex items-center justify-center py-12"
              >
                <Loader2 class="h-8 w-8 animate-spin" />
              </div>
              <div
                v-else-if="users.length === 0"
                class="text-center py-12 text-muted-foreground"
              >
                No users found
              </div>
              <div v-else class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr class="border-b">
                      <th
                        class="text-left p-4 text-sm font-medium text-muted-foreground"
                      >
                        Username
                      </th>
                      <th
                        class="text-left p-4 text-sm font-medium text-muted-foreground"
                      >
                        Email
                      </th>
                      <th
                        class="text-left p-4 text-sm font-medium text-muted-foreground"
                      >
                        Credits
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
                      v-for="user in users"
                      :key="user.id"
                      class="border-b hover:bg-muted/50"
                    >
                      <td class="p-2">{{ user.username }}</td>
                      <td class="p-2">{{ user.email }}</td>
                      <td class="p-2">
                        {{ user.credits_formatted || user.credits || 0 }}
                      </td>
                      <td class="p-2">
                        <div class="flex gap-2">
                          <Button
                            @click="viewUser(user.id)"
                            variant="outline"
                            size="sm"
                          >
                            <Eye class="h-4 w-4 mr-1" />
                            View
                          </Button>
                          <Button
                            v-if="user.uuid"
                            @click="openFeatherPanelUserEdit(user.uuid)"
                            variant="outline"
                            size="sm"
                            title="Open in FeatherPanel User Management"
                          >
                            <ExternalLink class="h-4 w-4" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <!-- Pagination -->
                <div
                  v-if="totalUserPages > 1"
                  class="flex items-center justify-center gap-2 mt-6"
                >
                  <Button
                    @click="loadUsers(currentUserPage - 1)"
                    :disabled="currentUserPage === 1"
                    variant="outline"
                    size="sm"
                  >
                    <ChevronLeft class="h-4 w-4" />
                  </Button>
                  <span class="text-sm text-muted-foreground">
                    Page {{ currentUserPage }} of {{ totalUserPages }}
                  </span>
                  <Button
                    @click="loadUsers(currentUserPage + 1)"
                    :disabled="currentUserPage === totalUserPages"
                    variant="outline"
                    size="sm"
                  >
                    <ChevronRight class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="settings" class="mt-4">
          <div class="space-y-6">
            <!-- Currency Settings -->
            <Card>
              <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Currency Settings</h3>
                <div
                  v-if="
                    loadingSettings &&
                    (!currencySettings || !billingCoreSettings)
                  "
                  class="flex items-center justify-center py-12"
                >
                  <Loader2 class="h-8 w-8 animate-spin" />
                </div>
                <div
                  v-else-if="currencySettings && billingCoreSettings"
                  class="space-y-6"
                >
                  <div>
                    <Label for="credits-mode-select">Credits Mode</Label>
                    <select
                      id="credits-mode-select"
                      class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 mt-2"
                    >
                      <option
                        value="currency"
                        :selected="
                          billingCoreSettings.credits_mode === 'currency'
                        "
                      >
                        Currency (for paid hosting)
                      </option>
                      <option
                        value="token"
                        :selected="billingCoreSettings.credits_mode === 'token'"
                      >
                        Token (for freemium hosting)
                      </option>
                    </select>
                    <p class="text-sm text-muted-foreground mt-2">
                      <strong>Currency mode:</strong> Credits are treated as a
                      currency (e.g., EUR, USD) for paid hosting services.
                      <br />
                      <strong>Token mode:</strong> Credits are treated as
                      tokens/points for freemium hosting models where users can
                      earn or purchase tokens.
                    </p>
                  </div>

                  <div v-if="billingCoreSettings.credits_mode === 'token'">
                    <Label for="tokens-per-currency-input"
                      >Tokens per Currency Unit</Label
                    >
                    <Input
                      id="tokens-per-currency-input"
                      v-model="tokensPerCurrency"
                      type="number"
                      step="0.01"
                      min="0.01"
                      placeholder="1"
                      class="mt-2"
                    />
                    <p class="text-sm text-muted-foreground mt-2">
                      How many tokens equal 1 currency unit (e.g., 1€). For
                      example, if set to "10", then 10 tokens = 1€. This
                      determines the exchange rate when users purchase tokens.
                    </p>
                  </div>

                  <div>
                    <Label for="default-currency-select"
                      >Default Currency</Label
                    >
                    <select
                      id="default-currency-select"
                      class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 mt-2"
                    >
                      <option
                        v-for="currency in currencySettings.available_currencies"
                        :key="currency.code"
                        :value="currency.code"
                        :selected="
                          currency.code ===
                          currencySettings.default_currency.code
                        "
                      >
                        {{ currency.name }} ({{ currency.code }}) -
                        {{ currency.symbol }}
                      </option>
                    </select>
                  </div>

                  <Button
                    @click="saveCurrencySettings"
                    :disabled="savingSettings"
                  >
                    <Loader2
                      v-if="savingSettings"
                      class="h-4 w-4 mr-2 animate-spin"
                    />
                    Save Currency Settings
                  </Button>
                </div>
              </div>
            </Card>

            <!-- Admin Billing Information -->
            <Card>
              <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">
                  Admin Billing Information
                </h3>
                <div
                  v-if="loadingSettings && !adminBillingInfo"
                  class="flex items-center justify-center py-12"
                >
                  <Loader2 class="h-8 w-8 animate-spin" />
                </div>
                <form v-else @submit="saveAdminBillingInfo" class="space-y-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <Label for="admin-full_name">Full Name</Label>
                      <Input
                        id="admin-full_name"
                        name="admin-full_name"
                        :default-value="adminBillingInfo?.full_name || ''"
                      />
                    </div>
                    <div>
                      <Label for="admin-company_name">Company Name</Label>
                      <Input
                        id="admin-company_name"
                        name="admin-company_name"
                        :default-value="adminBillingInfo?.company_name || ''"
                      />
                    </div>
                    <div>
                      <Label for="admin-address_line1">Address Line 1</Label>
                      <Input
                        id="admin-address_line1"
                        name="admin-address_line1"
                        :default-value="adminBillingInfo?.address_line1 || ''"
                      />
                    </div>
                    <div>
                      <Label for="admin-address_line2">Address Line 2</Label>
                      <Input
                        id="admin-address_line2"
                        name="admin-address_line2"
                        :default-value="adminBillingInfo?.address_line2 || ''"
                      />
                    </div>
                    <div>
                      <Label for="admin-city">City</Label>
                      <Input
                        id="admin-city"
                        name="admin-city"
                        :default-value="adminBillingInfo?.city || ''"
                      />
                    </div>
                    <div>
                      <Label for="admin-state">State</Label>
                      <Input
                        id="admin-state"
                        name="admin-state"
                        :default-value="adminBillingInfo?.state || ''"
                      />
                    </div>
                    <div>
                      <Label for="admin-postal_code">Postal Code</Label>
                      <Input
                        id="admin-postal_code"
                        name="admin-postal_code"
                        :default-value="adminBillingInfo?.postal_code || ''"
                      />
                    </div>
                    <div>
                      <Label for="admin-country_code">Country Code</Label>
                      <Input
                        id="admin-country_code"
                        name="admin-country_code"
                        :default-value="adminBillingInfo?.country_code || ''"
                        maxlength="2"
                      />
                    </div>
                    <div>
                      <Label for="admin-vat_id">VAT ID</Label>
                      <Input
                        id="admin-vat_id"
                        name="admin-vat_id"
                        :default-value="adminBillingInfo?.vat_id || ''"
                      />
                    </div>
                    <div>
                      <Label for="admin-phone">Phone</Label>
                      <Input
                        id="admin-phone"
                        name="admin-phone"
                        :default-value="adminBillingInfo?.phone || ''"
                      />
                    </div>
                  </div>
                  <Button type="submit" :disabled="savingSettings">
                    <Loader2
                      v-if="savingSettings"
                      class="h-4 w-4 mr-2 animate-spin"
                    />
                    Save Admin Billing Info
                  </Button>
                </form>
              </div>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>

    <!-- User Details Dialog -->
    <Dialog v-model:open="userDialogOpen">
      <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            User: {{ selectedUser?.username || "N/A" }}
          </DialogTitle>
          <DialogDescription
            >User details and billing management</DialogDescription
          >
        </DialogHeader>

        <div
          v-if="loadingUserDetails"
          class="flex items-center justify-center py-12"
        >
          <Loader2 class="h-8 w-8 animate-spin" />
        </div>
        <div v-else-if="selectedUser" class="space-y-6">
          <!-- User Info -->
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-muted-foreground">Email:</span>
              <div class="font-medium">{{ selectedUser.email }}</div>
            </div>
            <div>
              <span class="text-muted-foreground">Credits:</span>
              <div class="font-medium">
                {{ selectedUser.credits_formatted }}
              </div>
            </div>
          </div>

          <!-- Open FeatherPanel User Edit -->
          <div v-if="selectedUser.uuid" class="flex gap-2">
            <Button
              @click="openFeatherPanelUserEdit(selectedUser.uuid)"
              variant="outline"
              class="flex-1"
            >
              <ExternalLink class="h-4 w-4 mr-2" />
              Open in New Tab
            </Button>
            <Button
              @click="openFeatherPanelUserEditInIframe(selectedUser.uuid)"
              variant="outline"
              class="flex-1"
            >
              <ExternalLink class="h-4 w-4 mr-2" />
              Open in Window
            </Button>
          </div>

          <!-- Credits Management -->
          <div>
            <h4 class="font-semibold mb-3">Credits Management</h4>
            <div class="flex gap-2 items-end">
              <div class="flex-1">
                <Label for="credit-amount">Amount</Label>
                <Input
                  id="credit-amount"
                  v-model="creditAmount"
                  type="number"
                  placeholder="Amount"
                />
              </div>
              <Button @click="handleAddCredits">Add</Button>
              <Button @click="handleRemoveCredits" variant="destructive"
                >Remove</Button
              >
              <Button @click="handleSetCredits" variant="outline">Set</Button>
            </div>
          </div>

          <!-- Billing Information -->
          <div>
            <h4 class="font-semibold mb-3">Billing Information</h4>
            <form @submit="saveUserBillingInfo" class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label for="full_name">Full Name</Label>
                  <Input
                    id="full_name"
                    name="full_name"
                    :default-value="selectedUserBillingInfo?.full_name || ''"
                  />
                </div>
                <div>
                  <Label for="company_name">Company Name</Label>
                  <Input
                    id="company_name"
                    name="company_name"
                    :default-value="selectedUserBillingInfo?.company_name || ''"
                  />
                </div>
                <div>
                  <Label for="address_line1">Address Line 1</Label>
                  <Input
                    id="address_line1"
                    name="address_line1"
                    :default-value="
                      selectedUserBillingInfo?.address_line1 || ''
                    "
                  />
                </div>
                <div>
                  <Label for="address_line2">Address Line 2</Label>
                  <Input
                    id="address_line2"
                    name="address_line2"
                    :default-value="
                      selectedUserBillingInfo?.address_line2 || ''
                    "
                  />
                </div>
                <div>
                  <Label for="city">City</Label>
                  <Input
                    id="city"
                    name="city"
                    :default-value="selectedUserBillingInfo?.city || ''"
                  />
                </div>
                <div>
                  <Label for="state">State</Label>
                  <Input
                    id="state"
                    name="state"
                    :default-value="selectedUserBillingInfo?.state || ''"
                  />
                </div>
                <div>
                  <Label for="postal_code">Postal Code</Label>
                  <Input
                    id="postal_code"
                    name="postal_code"
                    :default-value="selectedUserBillingInfo?.postal_code || ''"
                  />
                </div>
                <div>
                  <Label for="country_code">Country Code</Label>
                  <Input
                    id="country_code"
                    name="country_code"
                    :default-value="selectedUserBillingInfo?.country_code || ''"
                    maxlength="2"
                  />
                </div>
                <div>
                  <Label for="vat_id">VAT ID</Label>
                  <Input
                    id="vat_id"
                    name="vat_id"
                    :default-value="selectedUserBillingInfo?.vat_id || ''"
                  />
                </div>
                <div>
                  <Label for="phone">Phone</Label>
                  <Input
                    id="phone"
                    name="phone"
                    :default-value="selectedUserBillingInfo?.phone || ''"
                  />
                </div>
              </div>
              <Button type="submit">Save Billing Info</Button>
            </form>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <!-- FeatherPanel User Edit Iframe Dialog -->
    <Dialog v-model:open="showFeatherPanelIframe">
      <DialogContent
        class="max-w-[95vw] max-h-[95vh] w-[95vw] h-[95vh] p-0 flex flex-col"
      >
        <DialogHeader class="px-6 pt-6 pb-4 border-b shrink-0">
          <DialogTitle>FeatherPanel User Management</DialogTitle>
          <DialogDescription
            >View and edit user details in FeatherPanel</DialogDescription
          >
        </DialogHeader>
        <div class="flex-1 overflow-hidden min-h-0">
          <iframe
            v-if="featherPanelUserUrl"
            :src="featherPanelUserUrl"
            class="w-full h-full border-0"
          ></iframe>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>
