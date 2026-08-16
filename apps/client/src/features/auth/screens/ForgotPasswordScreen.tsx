import { useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { View } from "react-native";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AuthLayout } from "@/components/patterns/AuthLayout";
import { AppButton } from "@/components/primitives/AppButton";
import { TextField } from "@/components/primitives/TextField";
import { spacing } from "@/theme";

const schema = z.object({
  email: z.email(),
});

type FormValues = z.infer<typeof schema>;

export default function ForgotPasswordScreen() {
  const { t } = useTranslation(["auth", "app"]);
  const { requestPasswordReset } = useAuth();
  const [success, setSuccess] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [resetTokenPreview, setResetTokenPreview] = useState<string | null>(null);
  const [resetUrlPreview, setResetUrlPreview] = useState<string | null>(null);
  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      email: "",
    },
  });

  const onSubmit = handleSubmit(async (values) => {
    try {
      setError(null);
      const result = await requestPasswordReset(values.email);
      setSuccess(result.message || t("app:forgotPasswordSent"));
      setResetTokenPreview(result.resetTokenPreview ?? null);
      setResetUrlPreview(result.resetUrlPreview ?? null);
    } catch (caught) {
      setError(
        caught instanceof Error ? caught.message : "Unable to request reset."
      );
    }
  });

  return (
    <AuthLayout
      description={t("forgotPasswordBody")}
      title={t("forgotPassword")}
    >
      <View style={{ gap: spacing[4] }}>
        {error ? <AlertMessage tone="error" message={error} /> : null}
        {success ? <AlertMessage tone="success" message={success} /> : null}
        {resetTokenPreview ? (
          <AlertMessage
            message={t("auth:resetTokenPreview", {
              token: resetTokenPreview,
            })}
          />
        ) : null}
        {resetUrlPreview ? (
          <AlertMessage
            message={t("auth:resetUrlPreview", {
              url: resetUrlPreview,
            })}
          />
        ) : null}
        <Controller
          control={control}
          name="email"
          render={({ field }) => (
            <TextField
              autoCapitalize="none"
              keyboardType="email-address"
              label={t("email")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.email?.message}
            />
          )}
        />
        <AppButton
          label={t("submitForgotPassword")}
          loading={isSubmitting}
          onPress={onSubmit}
        />
      </View>
    </AuthLayout>
  );
}
