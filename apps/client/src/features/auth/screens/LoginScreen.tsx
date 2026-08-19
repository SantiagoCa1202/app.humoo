import { useState } from "react";
import { Link } from "expo-router";
import { Controller, useForm } from "react-hook-form";
import { View } from "react-native";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AuthLayout } from "@/components/patterns/AuthLayout";
import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import { TextField } from "@/components/primitives/TextField";
import {
  applyApiFieldErrors,
  resolveErrorMessage,
} from "@/features/auth/form-errors";
import { spacing } from "@/theme";

type FormValues = {
  email: string;
  password: string;
};

export default function LoginScreen() {
  const { t } = useTranslation("auth");
  const { signIn } = useAuth();
  const schema = z.object({
    email: z.string().trim().email(t("validationEmail")),
    password: z.string().min(8, t("validationPassword")),
  });
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
      email: "",
      password: "",
    },
  });

  const onSubmit = handleSubmit(async (values) => {
    try {
      setError(null);
      clearErrors();
      await signIn(values);
    } catch (caught) {
      applyApiFieldErrors(
        caught,
        {
          email: "email",
          password: "password",
        },
        setFormError
      );
      setError(resolveErrorMessage(caught, t("loginError")));
    }
  });

  return (
    <AuthLayout
      description={t("welcomeBody")}
      title={t("login")}
    >
      <View style={{ gap: spacing[4] }}>
        {error ? <AlertMessage tone="error" message={error} /> : null}
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
        <AppButton
          label={t("submitLogin")}
          loading={isSubmitting}
          onPress={onSubmit}
        />
        <Link href="/(public)/forgot-password">
          <AppText muted>{t("forgotPassword")}</AppText>
        </Link>
        <Link href="/(public)/register">
          <AppText muted>{t("registerLink")}</AppText>
        </Link>
      </View>
    </AuthLayout>
  );
}
