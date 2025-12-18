import { ref } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";

export interface BillingInfo {
  user_id?: number;
  full_name: string | null;
  company_name: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country_code: string | null;
  vat_id: string | null;
  phone: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface Credits {
  credits: number;
  credits_formatted: string;
  currency: {
    code: string;
    name: string;
    symbol: string;
  };
}

export interface Invoice {
  id: number;
  user_id: number;
  invoice_number: string;
  status: "draft" | "pending" | "paid" | "overdue" | "cancelled";
  due_date: string | null;
  paid_at: string | null;
  subtotal: number;
  tax_rate: number;
  tax_amount: number;
  total: number;
  currency_code: string;
  notes: string | null;
  created_at: string;
  updated_at: string;
  subtotal_formatted?: string;
  tax_amount_formatted?: string;
  total_formatted?: string;
  items?: InvoiceItem[];
  username?: string;
  email?: string;
  customer?: {
    billing_info?: BillingInfo;
    username?: string;
    email?: string;
  };
  admin?: {
    billing_info?: BillingInfo;
  };
}

export interface InvoiceItem {
  id: number;
  invoice_id: number;
  description: string;
  quantity: number;
  unit_price: number;
  total: number;
  sort_order: number;
  unit_price_formatted?: string;
  total_formatted?: string;
}

export interface InvoiceListResponse {
  data: Invoice[];
  meta: {
    pagination: {
      current_page: number;
      per_page: number;
      total: number;
      total_pages: number;
    };
    currency: {
      code: string;
      name: string;
      symbol: string;
    };
  };
}

export interface Statistics {
  users: {
    total_with_billing: number;
    with_credits: number;
  };
  credits: {
    total: number;
    total_formatted: string;
    average_per_user: number;
    average_per_user_formatted: string;
  };
}

export interface User {
  id: number;
  username: string;
  email: string;
  uuid?: string;
  credits?: number;
  credits_formatted?: string;
}

export interface UserListResponse {
  data: User[];
  meta: {
    pagination: {
      current_page: number;
      per_page: number;
      total: number;
      total_pages: number;
    };
  };
}

export interface UserWithCredits extends User {
  credits: number;
  credits_formatted: string;
  currency: {
    code: string;
    name: string;
    symbol: string;
  };
}

export interface Currency {
  code: string;
  name: string;
  symbol: string;
}

export interface CurrencySettings {
  default_currency: Currency;
  available_currencies: Currency[];
}

export interface BillingCoreSettings {
  credits_mode: "currency" | "token";
  tokens_per_currency?: string;
}

export function useBillingAPI() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  const handleError = (err: unknown): string => {
    if (axios.isAxiosError(err)) {
      const axiosError = err as AxiosError<{
        message?: string;
        error_message?: string;
        error?: string;
      }>;
      return (
        axiosError.response?.data?.message ||
        axiosError.response?.data?.error_message ||
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

  // Credits
  const getCredits = async (): Promise<Credits> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/user/billingcore/credits");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch credits");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Billing Info
  const getBillingInfo = async (): Promise<BillingInfo> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/user/billingcore/billing-info");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch billing info");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const updateBillingInfo = async (
    data: Partial<BillingInfo>
  ): Promise<BillingInfo> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(
        "/api/user/billingcore/billing-info",
        data
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(
        response.data?.message || "Failed to update billing info"
      );
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Invoices
  const getInvoices = async (
    page: number = 1,
    limit: number = 20,
    status?: string
  ): Promise<InvoiceListResponse> => {
    loading.value = true;
    error.value = null;

    try {
      const params: Record<string, string> = {
        page: page.toString(),
        limit: limit.toString(),
      };
      if (status) params.status = status;

      const response = await axios.get("/api/user/billingcore/invoices", {
        params,
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch invoices");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const getInvoice = async (
    invoiceId: number
  ): Promise<
    Invoice & {
      customer?: {
        billing_info?: BillingInfo;
        username?: string;
        email?: string;
      };
      admin?: {
        billing_info?: BillingInfo;
      };
    }
  > => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(
        `/api/user/billingcore/invoices/${invoiceId}`
      );
      if (response.data && response.data.success) {
        // The backend now returns customer and admin info directly in the invoice object
        return response.data.data;
      }
      throw new Error("Failed to fetch invoice");
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
    getCredits,
    getBillingInfo,
    updateBillingInfo,
    getInvoices,
    getInvoice,
  };
}

export function useAdminBillingAPI() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  const handleError = (err: unknown): string => {
    if (axios.isAxiosError(err)) {
      const axiosError = err as AxiosError<{
        message?: string;
        error_message?: string;
        error?: string;
      }>;
      return (
        axiosError.response?.data?.message ||
        axiosError.response?.data?.error_message ||
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

  // Admin Invoices
  const getInvoices = async (
    page: number = 1,
    limit: number = 20,
    userId?: number,
    status?: string,
    search?: string
  ): Promise<InvoiceListResponse> => {
    loading.value = true;
    error.value = null;

    try {
      const params: Record<string, string> = {
        page: page.toString(),
        limit: limit.toString(),
      };
      if (userId) params.user_id = userId.toString();
      if (status) params.status = status;
      if (search) params.search = search;

      const response = await axios.get("/api/admin/billingcore/invoices", {
        params,
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch invoices");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const getInvoice = async (invoiceId: number): Promise<Invoice> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(
        `/api/admin/billingcore/invoices/${invoiceId}`
      );
      if (response.data && response.data.success) {
        const data = response.data.data;
        const invoice = data.invoice || data;
        // Merge user info into invoice if available
        if (data.user) {
          invoice.username = data.user.username;
          invoice.email = data.user.email;
        }
        // Merge customer and admin info if available
        if (invoice.customer || invoice.admin) {
          // Already merged in backend
        } else if (data.customer || data.admin) {
          invoice.customer = data.customer;
          invoice.admin = data.admin;
        }
        return invoice;
      }
      throw new Error("Failed to fetch invoice");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const createInvoice = async (data: {
    user_id: number;
    invoice_number?: string;
    status?: string;
    due_date?: string;
    tax_rate?: number;
    currency_code?: string;
    notes?: string;
    items?: Array<{
      description: string;
      quantity: number;
      unit_price: number;
      sort_order?: number;
    }>;
  }): Promise<Invoice> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post(
        "/api/admin/billingcore/invoices",
        data
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to create invoice");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const updateInvoice = async (
    invoiceId: number,
    data: Partial<Invoice>
  ): Promise<Invoice> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(
        `/api/admin/billingcore/invoices/${invoiceId}`,
        data
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to update invoice");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const deleteInvoice = async (invoiceId: number): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.delete(
        `/api/admin/billingcore/invoices/${invoiceId}`
      );
      if (!response.data || !response.data.success) {
        throw new Error(response.data?.message || "Failed to delete invoice");
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const addInvoiceItem = async (
    invoiceId: number,
    item: {
      description: string;
      quantity: number;
      unit_price: number;
      sort_order?: number;
    }
  ): Promise<InvoiceItem> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post(
        `/api/admin/billingcore/invoices/${invoiceId}/items`,
        item
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to add item");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const updateInvoiceItem = async (
    invoiceId: number,
    itemId: number,
    item: Partial<InvoiceItem>
  ): Promise<InvoiceItem> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(
        `/api/admin/billingcore/invoices/${invoiceId}/items/${itemId}`,
        item
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to update item");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const deleteInvoiceItem = async (
    invoiceId: number,
    itemId: number
  ): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.delete(
        `/api/admin/billingcore/invoices/${invoiceId}/items/${itemId}`
      );
      if (!response.data || !response.data.success) {
        throw new Error(response.data?.message || "Failed to delete item");
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Statistics
  const getStatistics = async (): Promise<Statistics> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingcore/statistics");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch statistics");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Users
  const getUsers = async (
    page: number = 1,
    limit: number = 20,
    search?: string
  ): Promise<UserListResponse> => {
    loading.value = true;
    error.value = null;

    try {
      const params: Record<string, string> = {
        page: page.toString(),
        limit: limit.toString(),
      };
      if (search) params.search = search;

      const response = await axios.get("/api/admin/billingcore/users", {
        params,
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch users");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const getUserCredits = async (userId: number): Promise<UserWithCredits> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(
        `/api/admin/billingcore/users/${userId}/credits`
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch user credits");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const getUserBillingInfo = async (userId: number): Promise<BillingInfo> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(
        `/api/admin/billingcore/users/${userId}/billing-info`
      );
      if (response.data && response.data.success) {
        return response.data.data.billing_info || response.data.data;
      }
      throw new Error("Failed to fetch user billing info");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const addUserCredits = async (
    userId: number,
    amount: number
  ): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post(
        `/api/admin/billingcore/users/${userId}/credits/add`,
        { amount }
      );
      if (!response.data || !response.data.success) {
        throw new Error(
          response.data?.data?.message ||
            response.data?.message ||
            "Failed to add credits"
        );
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const removeUserCredits = async (
    userId: number,
    amount: number
  ): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post(
        `/api/admin/billingcore/users/${userId}/credits/remove`,
        { amount }
      );
      if (!response.data || !response.data.success) {
        throw new Error(
          response.data?.data?.message ||
            response.data?.message ||
            "Failed to remove credits"
        );
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const setUserCredits = async (
    userId: number,
    amount: number
  ): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post(
        `/api/admin/billingcore/users/${userId}/credits/set`,
        { amount }
      );
      if (!response.data || !response.data.success) {
        throw new Error(
          response.data?.data?.message ||
            response.data?.message ||
            "Failed to set credits"
        );
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const updateUserBillingInfo = async (
    userId: number,
    data: Partial<BillingInfo>
  ): Promise<BillingInfo> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(
        `/api/admin/billingcore/users/${userId}/billing-info`,
        data
      );
      if (response.data && response.data.success) {
        return response.data.data.billing_info || response.data.data;
      }
      throw new Error(
        response.data?.message || "Failed to update user billing info"
      );
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Settings
  const getCurrencySettings = async (): Promise<CurrencySettings> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(
        "/api/admin/billingcore/currency/settings"
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch currency settings");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const updateCurrencySettings = async (
    defaultCurrency: string
  ): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(
        "/api/admin/billingcore/currency/settings",
        { default_currency: defaultCurrency }
      );
      if (!response.data || !response.data.success) {
        throw new Error(
          response.data?.data?.message ||
            response.data?.message ||
            "Failed to update currency settings"
        );
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const getAdminBillingInfo = async (): Promise<BillingInfo> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingcore/billing-info");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch admin billing info");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const updateAdminBillingInfo = async (
    data: Partial<BillingInfo>
  ): Promise<BillingInfo> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(
        "/api/admin/billingcore/billing-info",
        data
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(
        response.data?.message || "Failed to update admin billing info"
      );
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // General Settings
  const getSettings = async (): Promise<BillingCoreSettings> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingcore/settings");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch settings");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const updateSettings = async (
    data: Partial<BillingCoreSettings>
  ): Promise<BillingCoreSettings> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(
        "/api/admin/billingcore/settings",
        data
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to update settings");
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
    getInvoices,
    getInvoice,
    createInvoice,
    updateInvoice,
    deleteInvoice,
    addInvoiceItem,
    updateInvoiceItem,
    deleteInvoiceItem,
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
  };
}
