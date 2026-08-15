import { useState } from "react";
import { router } from "expo-router";
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
import { isApiConfigured } from "@/config/runtime";

const schema = z
  .object({
    firstName: z.string().min(2),
    lastName: z.string().min(2),
    email: z.email(),
    password: z.string().min(8),
    confirmPassword: z.string().min(8),
  })
  .refine((values) => values.password === values.confirmPassword, {
    message: "Passwords must match.",
    path: ["confirmPassword"],
  });

type FormValues = z.infer<typeof schema>;

export default function RegisterScreen() {
  const { t } = useTranslation("auth");
  const { signUp } = useAuth();
  const [error, setError] = useState<string | null>(null);
  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      firstName: "",
      lastName: "",
      email: "",
      password: "",
      confirmPassword: "",
    },
  });

  const onSubmit = handleSubmit(async (values) => {
    try {
      setError(null);
      await signUp(values);
      router.replace("/(onboarding)/organization");
    } catch (caught) {
      setError(
        caught instanceof Error ? caught.message : "Unable to create account."
      );
    }
  });

  return (
    <AuthLayout
      description={t("welcomeBody")}
      title={t("register")}
    >
      <View style={{ gap: 14 }}>
        {isApiConfigured ? (
          <AlertMessage message={t("registerUnavailable")} />
        ) : null}
        {error ? <AlertMessage tone="error" message={error} /> : null}
        <Controller
          control={control}
          name="firstName"
          render={({ field }) => (
            <TextField
              label={t("firstName")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.firstName?.message}
            />
          )}
        />
        <Controller
          control={control}
          name="lastName"
          render={({ field }) => (
            <TextField
              label={t("lastName")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.lastName?.message}
            />
          )}
        />
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
        <AppButton
          disabled={isApiConfigured}
          label={t("submitRegister")}
          loading={isSubmitting}
          onPress={onSubmit}
        />
      </View>
    </AuthLayout>
  );
}
