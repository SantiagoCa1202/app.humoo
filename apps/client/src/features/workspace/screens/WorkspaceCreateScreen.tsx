import { Controller, useForm } from "react-hook-form";
import { View } from "react-native";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslation } from "react-i18next";

import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AuthLayout } from "@/components/patterns/AuthLayout";
import { AppButton } from "@/components/primitives/AppButton";
import { OptionPicker } from "@/components/primitives/OptionPicker";
import { TextField } from "@/components/primitives/TextField";
import type { CreateWorkspaceInput } from "@/features/workspace";
import { spacing } from "@/theme";

const schema = z.object({
  name: z.string().trim().min(2),
  defaultLocale: z.enum(["en", "es"]),
  timezone: z.string().trim().min(3),
  currency: z.string().trim().length(3),
});

type FormValues = z.infer<typeof schema>;

type WorkspaceCreateScreenProps = {
  canGoBack: boolean;
  defaultCurrency: string;
  defaultLocale: "en" | "es";
  defaultTimezone: string;
  errorMessage?: string | null;
  onBack: () => void;
  onCreateWorkspace: (input: CreateWorkspaceInput) => Promise<void>;
};

export function WorkspaceCreateScreen({
  canGoBack,
  defaultCurrency,
  defaultLocale,
  defaultTimezone,
  errorMessage,
  onBack,
  onCreateWorkspace,
}: WorkspaceCreateScreenProps) {
  const { t } = useTranslation("app");
  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: "",
      defaultLocale,
      timezone: defaultTimezone,
      currency: defaultCurrency,
    },
  });

  const onSubmit = handleSubmit(async (values) => {
    try {
      await onCreateWorkspace({
        name: values.name,
        defaultLocale: values.defaultLocale,
        timezone: values.timezone,
        currency: values.currency.toUpperCase(),
      });
    } catch {
      // The provider surfaces the API error through shared state.
    }
  });

  return (
    <AuthLayout
      description={t("workspaceCreateBody")}
      title={t("workspaceCreateTitle")}
    >
      <View style={{ gap: spacing[4] }}>
        {errorMessage ? <AlertMessage tone="error" message={errorMessage} /> : null}
        <Controller
          control={control}
          name="name"
          render={({ field }) => (
            <TextField
              label={t("workspaceFieldName")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.name?.message}
            />
          )}
        />
        <Controller
          control={control}
          name="defaultLocale"
          render={({ field }) => (
            <OptionPicker
              error={errors.defaultLocale?.message}
              hint={t("workspaceDefaultLocaleHint")}
              label={t("workspaceFieldDefaultLocale")}
              onChange={field.onChange}
              options={[
                {
                  value: "en",
                  label: t("workspaceLocale.en"),
                },
                {
                  value: "es",
                  label: t("workspaceLocale.es"),
                },
              ]}
              selected={field.value}
            />
          )}
        />
        <Controller
          control={control}
          name="timezone"
          render={({ field }) => (
            <TextField
              autoCapitalize="none"
              label={t("workspaceFieldTimezone")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.timezone?.message}
            />
          )}
        />
        <Controller
          control={control}
          name="currency"
          render={({ field }) => (
            <TextField
              autoCapitalize="characters"
              label={t("workspaceFieldCurrency")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.currency?.message}
            />
          )}
        />
        <AppButton
          label={t("workspaceCreateAction")}
          loading={isSubmitting}
          onPress={onSubmit}
        />
        {canGoBack ? (
          <AppButton
            label={t("workspaceBackToSelection")}
            onPress={onBack}
            variant="secondary"
          />
        ) : null}
      </View>
    </AuthLayout>
  );
}
