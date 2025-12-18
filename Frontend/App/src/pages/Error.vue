<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { AlertCircle } from "lucide-vue-next";
</script>

<template>
  <div class="min-h-screen bg-background p-4 md:p-8 flex items-center justify-center">
    <div class="mx-auto max-w-md w-full">
      <Card class="border-destructive">
        <CardHeader>
          <div class="flex items-center gap-3 mb-2">
            <AlertCircle class="h-6 w-6 text-destructive" />
            <CardTitle class="text-2xl text-destructive">Missing Server UUID</CardTitle>
          </div>
          <CardDescription>
            The application requires a server UUID to function properly.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <p class="text-muted-foreground">
              This application must be accessed with a <code class="px-2 py-1 bg-muted rounded text-sm">serverUuid</code> parameter in the URL.
            </p>
            <p class="text-sm text-muted-foreground">
              Example: <code class="px-2 py-1 bg-muted rounded text-xs">?serverUuid=your-uuid-here</code>
            </p>
            <div class="pt-4 border-t">
              <p class="text-sm text-muted-foreground">
                Please navigate to this page from the FeatherPanel server management interface.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>

