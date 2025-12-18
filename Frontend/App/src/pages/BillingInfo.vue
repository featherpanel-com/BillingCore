<script setup lang="ts">
import { ref, onMounted } from "vue";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import {
  Loader2,
  Save,
  CheckCircle2,
  AlertCircle,
  User,
  Building2,
  MapPin,
  Phone,
  FileText,
  Globe,
  Wallet,
  Info,
  Edit,
} from "lucide-vue-next";
import { useBillingAPI, type Credits } from "@/composables/useBillingAPI";
import { useToast } from "vue-toastification";

const toast = useToast();
const { loading, error, getCredits, getBillingInfo, updateBillingInfo } =
  useBillingAPI();

// Local type for form fields (using string instead of string | null)
type BillingInfoForm = {
  full_name: string;
  company_name: string;
  address_line1: string;
  address_line2: string;
  city: string;
  state: string;
  postal_code: string;
  country_code: string;
  vat_id: string;
  phone: string;
};

const credits = ref<Credits | null>(null);
const billingInfo = ref<BillingInfoForm>({
  full_name: "",
  company_name: "",
  address_line1: "",
  address_line2: "",
  city: "",
  state: "",
  postal_code: "",
  country_code: "",
  vat_id: "",
  phone: "",
});
const saving = ref(false);
const hasBillingInfo = ref(false);

const loadData = async () => {
  try {
    const [creditsData, billingData] = await Promise.all([
      getCredits(),
      getBillingInfo(),
    ]);
    credits.value = creditsData;
    // Convert null values to empty strings for v-model compatibility
    billingInfo.value = {
      full_name: billingData.full_name || "",
      company_name: billingData.company_name || "",
      address_line1: billingData.address_line1 || "",
      address_line2: billingData.address_line2 || "",
      city: billingData.city || "",
      state: billingData.state || "",
      postal_code: billingData.postal_code || "",
      country_code: billingData.country_code || "",
      vat_id: billingData.vat_id || "",
      phone: billingData.phone || "",
    };
    hasBillingInfo.value = !!(
      billingData.full_name &&
      billingData.address_line1 &&
      billingData.city &&
      billingData.postal_code &&
      billingData.country_code
    );
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load data");
  }
};

const saveBillingInfo = async () => {
  saving.value = true;
  try {
    // Convert empty strings to null for API
    const dataToSend = {
      ...billingInfo.value,
      full_name: billingInfo.value.full_name || null,
      company_name: billingInfo.value.company_name || null,
      address_line1: billingInfo.value.address_line1 || null,
      address_line2: billingInfo.value.address_line2 || null,
      city: billingInfo.value.city || null,
      state: billingInfo.value.state || null,
      postal_code: billingInfo.value.postal_code || null,
      country_code: billingInfo.value.country_code || null,
      vat_id: billingInfo.value.vat_id || null,
      phone: billingInfo.value.phone || null,
    };
    const updated = await updateBillingInfo(dataToSend);
    // Convert null values back to empty strings for v-model
    billingInfo.value = {
      full_name: updated.full_name || "",
      company_name: updated.company_name || "",
      address_line1: updated.address_line1 || "",
      address_line2: updated.address_line2 || "",
      city: updated.city || "",
      state: updated.state || "",
      postal_code: updated.postal_code || "",
      country_code: updated.country_code || "",
      vat_id: updated.vat_id || "",
      phone: updated.phone || "",
    };
    hasBillingInfo.value = !!(
      updated.full_name &&
      updated.address_line1 &&
      updated.city &&
      updated.postal_code &&
      updated.country_code
    );
    toast.success("Billing information saved successfully!");
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to save billing information"
    );
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadData();
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
            Billing Information
          </h1>
          <p class="text-lg text-muted-foreground mt-2">
            Manage your billing details and view your credit balance
          </p>
        </div>
      </div>

      <!-- Credits Card -->
      <Card
        v-if="credits"
        class="p-8 md:p-10 border-2 shadow-xl bg-card/50 backdrop-blur-sm"
      >
        <div class="space-y-4">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2 rounded-lg bg-primary/10">
              <Wallet class="h-6 w-6 text-primary" />
            </div>
            <div>
              <h2 class="text-2xl font-bold">Your Credits</h2>
              <p class="text-sm text-muted-foreground">
                Current credit balance
              </p>
            </div>
          </div>
          <div
            v-if="loading && !credits"
            class="flex items-center justify-center py-12"
          >
            <Loader2 class="h-8 w-8 animate-spin text-primary" />
          </div>
          <div v-else-if="credits" class="space-y-2">
            <div class="flex items-baseline gap-2">
              <div class="text-4xl font-bold">
                {{ credits.credits_formatted }}
              </div>
              <Badge variant="secondary" class="text-sm">
                <Globe class="h-3 w-3 mr-1" />
                {{ credits.currency.code }}
              </Badge>
            </div>
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
              <Info class="h-4 w-4" />
              <span
                >Currency: {{ credits.currency.code }} ({{
                  credits.currency.name
                }})</span
              >
            </div>
          </div>
          <div
            v-else-if="error"
            class="text-destructive flex items-center gap-2 text-sm"
          >
            <AlertCircle class="h-5 w-5" />
            <span>{{ error }}</span>
          </div>
        </div>
      </Card>

      <!-- Current Billing Info Display -->
      <Card
        v-if="hasBillingInfo"
        class="p-8 md:p-10 border-2 shadow-xl bg-card/50 backdrop-blur-sm"
      >
        <div class="space-y-4">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2 rounded-lg bg-green-500/10">
              <CheckCircle2 class="h-6 w-6 text-green-500" />
            </div>
            <div>
              <h2 class="text-2xl font-bold">Current Billing Information</h2>
              <p class="text-sm text-muted-foreground">
                Your saved billing details
              </p>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              class="flex items-start gap-3 p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="p-2 rounded-md bg-background">
                <User class="h-4 w-4 text-muted-foreground flex-shrink-0" />
              </div>
              <div class="flex-1">
                <div class="text-xs text-muted-foreground mb-1 font-medium">
                  Full Name
                </div>
                <div class="text-sm font-semibold">
                  {{ billingInfo.full_name }}
                </div>
              </div>
            </div>
            <div
              v-if="billingInfo.company_name"
              class="flex items-start gap-3 p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="p-2 rounded-md bg-background">
                <Building2
                  class="h-4 w-4 text-muted-foreground flex-shrink-0"
                />
              </div>
              <div class="flex-1">
                <div class="text-xs text-muted-foreground mb-1 font-medium">
                  Company
                </div>
                <div class="text-sm font-semibold">
                  {{ billingInfo.company_name }}
                </div>
              </div>
            </div>
            <div
              class="flex items-start gap-3 p-3 rounded-lg bg-muted/30 border border-border/50 md:col-span-2"
            >
              <div class="p-2 rounded-md bg-background">
                <MapPin class="h-4 w-4 text-muted-foreground flex-shrink-0" />
              </div>
              <div class="flex-1">
                <div class="text-xs text-muted-foreground mb-1 font-medium">
                  Address
                </div>
                <div class="text-sm font-semibold">
                  {{ billingInfo.address_line1 }}
                  <span v-if="billingInfo.address_line2"
                    >, {{ billingInfo.address_line2 }}</span
                  >
                </div>
              </div>
            </div>
            <div
              class="flex items-start gap-3 p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="p-2 rounded-md bg-background">
                <MapPin class="h-4 w-4 text-muted-foreground flex-shrink-0" />
              </div>
              <div class="flex-1">
                <div class="text-xs text-muted-foreground mb-1 font-medium">
                  City
                </div>
                <div class="text-sm font-semibold">{{ billingInfo.city }}</div>
              </div>
            </div>
            <div
              v-if="billingInfo.state"
              class="flex items-start gap-3 p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="p-2 rounded-md bg-background">
                <MapPin class="h-4 w-4 text-muted-foreground flex-shrink-0" />
              </div>
              <div class="flex-1">
                <div class="text-xs text-muted-foreground mb-1 font-medium">
                  State/Province
                </div>
                <div class="text-sm font-semibold">{{ billingInfo.state }}</div>
              </div>
            </div>
            <div
              class="flex items-start gap-3 p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="p-2 rounded-md bg-background">
                <MapPin class="h-4 w-4 text-muted-foreground flex-shrink-0" />
              </div>
              <div class="flex-1">
                <div class="text-xs text-muted-foreground mb-1 font-medium">
                  Postal Code
                </div>
                <div class="text-sm font-semibold">
                  {{ billingInfo.postal_code }}
                </div>
              </div>
            </div>
            <div
              class="flex items-start gap-3 p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="p-2 rounded-md bg-background">
                <Globe class="h-4 w-4 text-muted-foreground flex-shrink-0" />
              </div>
              <div class="flex-1">
                <div class="text-xs text-muted-foreground mb-1 font-medium">
                  Country
                </div>
                <div class="text-sm font-semibold">
                  {{ billingInfo.country_code }}
                </div>
              </div>
            </div>
            <div
              v-if="billingInfo.vat_id"
              class="flex items-start gap-3 p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="p-2 rounded-md bg-background">
                <FileText class="h-4 w-4 text-muted-foreground flex-shrink-0" />
              </div>
              <div class="flex-1">
                <div class="text-xs text-muted-foreground mb-1 font-medium">
                  VAT ID
                </div>
                <div class="text-sm font-semibold">
                  {{ billingInfo.vat_id }}
                </div>
              </div>
            </div>
            <div
              v-if="billingInfo.phone"
              class="flex items-start gap-3 p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="p-2 rounded-md bg-background">
                <Phone class="h-4 w-4 text-muted-foreground flex-shrink-0" />
              </div>
              <div class="flex-1">
                <div class="text-xs text-muted-foreground mb-1 font-medium">
                  Phone
                </div>
                <div class="text-sm font-semibold">{{ billingInfo.phone }}</div>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Billing Info Form -->
      <Card class="p-8 md:p-10 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
        <div class="space-y-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2 rounded-lg bg-primary/10">
              <Edit class="h-6 w-6 text-primary" />
            </div>
            <div>
              <h2 class="text-2xl font-bold">
                {{ hasBillingInfo ? "Update" : "Add" }} Billing Information
              </h2>
              <p class="text-sm text-muted-foreground">
                {{
                  hasBillingInfo
                    ? "Modify your billing details"
                    : "Fill in your billing information"
                }}
              </p>
            </div>
          </div>

          <Alert v-if="error" class="mb-4">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription class="text-sm">{{ error }}</AlertDescription>
          </Alert>

          <form @submit.prevent="saveBillingInfo" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="full_name" class="flex items-center gap-2 text-sm">
                  <User class="h-4 w-4 text-muted-foreground" />
                  Full Name *
                </Label>
                <Input
                  id="full_name"
                  v-model="billingInfo.full_name"
                  placeholder="John Doe"
                  required
                />
              </div>
              <div class="space-y-2">
                <Label
                  for="company_name"
                  class="flex items-center gap-2 text-sm"
                >
                  <Building2 class="h-4 w-4 text-muted-foreground" />
                  Company Name
                </Label>
                <Input
                  id="company_name"
                  v-model="billingInfo.company_name"
                  placeholder="Company Inc."
                />
              </div>
              <div class="space-y-2">
                <Label
                  for="address_line1"
                  class="flex items-center gap-2 text-sm"
                >
                  <MapPin class="h-4 w-4 text-muted-foreground" />
                  Address Line 1 *
                </Label>
                <Input
                  id="address_line1"
                  v-model="billingInfo.address_line1"
                  placeholder="123 Main Street"
                  required
                />
              </div>
              <div class="space-y-2">
                <Label
                  for="address_line2"
                  class="flex items-center gap-2 text-sm"
                >
                  <MapPin class="h-4 w-4 text-muted-foreground" />
                  Address Line 2
                </Label>
                <Input
                  id="address_line2"
                  v-model="billingInfo.address_line2"
                  placeholder="Apartment 4B"
                />
              </div>
              <div class="space-y-2">
                <Label for="city" class="flex items-center gap-2 text-sm">
                  <MapPin class="h-4 w-4 text-muted-foreground" />
                  City *
                </Label>
                <Input
                  id="city"
                  v-model="billingInfo.city"
                  placeholder="Vienna"
                  required
                />
              </div>
              <div class="space-y-2">
                <Label for="state" class="flex items-center gap-2 text-sm">
                  <MapPin class="h-4 w-4 text-muted-foreground" />
                  State/Province
                </Label>
                <Input
                  id="state"
                  v-model="billingInfo.state"
                  placeholder="Vienna"
                />
              </div>
              <div class="space-y-2">
                <Label
                  for="postal_code"
                  class="flex items-center gap-2 text-sm"
                >
                  <MapPin class="h-4 w-4 text-muted-foreground" />
                  Postal Code *
                </Label>
                <Input
                  id="postal_code"
                  v-model="billingInfo.postal_code"
                  placeholder="1010"
                  required
                />
              </div>
              <div class="space-y-2">
                <Label
                  for="country_code"
                  class="flex items-center gap-2 text-sm"
                >
                  <Globe class="h-4 w-4 text-muted-foreground" />
                  Country Code *
                </Label>
                <Input
                  id="country_code"
                  v-model="billingInfo.country_code"
                  placeholder="AT"
                  maxlength="2"
                  required
                />
              </div>
              <div class="space-y-2">
                <Label for="vat_id" class="flex items-center gap-2 text-sm">
                  <FileText class="h-4 w-4 text-muted-foreground" />
                  VAT ID
                </Label>
                <Input
                  id="vat_id"
                  v-model="billingInfo.vat_id"
                  placeholder="ATU12345678"
                />
              </div>
              <div class="space-y-2">
                <Label for="phone" class="flex items-center gap-2 text-sm">
                  <Phone class="h-4 w-4 text-muted-foreground" />
                  Phone
                </Label>
                <Input
                  id="phone"
                  v-model="billingInfo.phone"
                  placeholder="+43 123 456789"
                />
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Button type="submit" :disabled="saving" class="gap-2">
                <Save class="h-4 w-4" />
                {{ saving ? "Saving..." : "Save Billing Information" }}
              </Button>
            </div>
          </form>
        </div>
      </Card>
    </div>
  </div>
</template>
