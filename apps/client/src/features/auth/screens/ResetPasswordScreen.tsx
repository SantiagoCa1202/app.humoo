import { useState } from "react";
import { router, useLocalSearchParams } from "expo-router";
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
import {
  applyApiFieldErrors,
  resolveErrorMessage,
} from "@/features/auth/form-errors";
import { spacing } from "@/theme";

function buildSchema(t: ReturnType<typeof useTranslation>["t"]) {
  return z
    .object({
      email: z.string().trim().email(t("auth:validationEmail")),
      token: z.string().trim().min(20, t("auth:validationToken")),
      password: z.string().min(8, t("auth:validationPassword")),
      confirmPassword: z.string().min(8, t("auth:validationConfirmPassword")),
    })
    .refine((values) => values.password === values.confirmPassword, {
      message: t("auth:passwordMismatch"),
      path: ["confirmPassword"],
    });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export default function ResetPasswordScreen() {
  const { t } = useTranslation(["auth", "app"]);
  const { resetPassword } = useAuth();
  const schema = buildSchema(t);
  const params = useLocalSearchParams<{
    token?: string;
    email?: string;
  }>();
  const tokenParam = typeof params.token === "string" ? params.token : "";
  const emailParam = typeof params.email === "string" ? params.email : "";
  const [success, setSuccess] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const {
    control,
    clearErrors,
    handleSubmit,
    setError: setFormError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      email: emailParam,
      token: tokenParam,
      password: "",
      confirmPassword: "",
    },
  });

  const onSubmit = handleSubmit(async (values) => {
    try {
      clearErrors();
      setError(null);
      await resetPassword({
        email: values.email,
        token: values.token,
        password: values.password,
        passwordConfirmation: values.confirmPassword,
      });
      setSuccess(t("app:resetPasswordSaved"));
    } catch (caught) {
      applyApiFieldErrors(
        caught,
        {
          email: "email",
          password: "password",
          password_confirmation: "confirmPassword",
          token: "token",
        },
        setFormError
      );
      setError(resolveErrorMessage(caught, t("auth:resetPasswordError")));
    }
  });

  return (
    <AuthLayout
      description={t("resetPasswordBody")}
      title={t("submitResetPassword")}
    >
      <View style={{ gap: spacing[4] }}>
        {error ? <AlertMessage tone="error" message={error} /> : null}
        {success ? <AlertMessage tone="success" message={success} /> : null}
        <Controller
          control={control}
          name="email"
          render={({ field }) => (
            <TextField
              autoCapitalize="none"
              keyboardType="email-address"
              label={t("resetPasswordEmail")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.email?.message}
            />
          )}
        />
        <Controller
          control={control}
          name="token"
          render={({ field }) => (
            <TextField
              autoCapitalize="none"
              label={t("auth:resetToken")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.token?.message}
            />
          )}
        />
        <Controller
          control={control}
          name="confirmPassword"
          render={({ field }) => (
            <TextField
              autoCapitalize="none"
              label={t("confirmPassword")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              secure
              value={field.value}
              error={errors.confirmPassword?.message}
            />
          )}
        />
        <Controller
          control={control}
          name="password"
          render={({ field }) => (
            <TextField
              autoCapitalize="none"
              label={t("password")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              secure
              value={field.value}
              error={errors.password?.message}
            />
          )}
        />
        <AppButton
          label={t("submitResetPassword")}
          loading={isSubmitting}
          onPress={onSubmit}
        />
        {success ? (
          <AppButton
            label={t("backToLogin")}
            onPress={() => router.replace("/(public)/login")}
            variant="secondary"
          />
        ) : null}
      </View>
    </AuthLayout>
  );
}
