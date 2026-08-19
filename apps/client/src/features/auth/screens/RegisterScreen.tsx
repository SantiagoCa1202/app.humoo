import { useEffect, useState } from "react";
import { useLocalSearchParams } from "expo-router";
import { Controller, useForm } from "react-hook-form";
import { View } from "react-native";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslation } from "react-i18next";
import { useQuery } from "@tanstack/react-query";

import { previewInvitation } from "@/auth/api";
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
      firstName: z.string().trim().min(2, t("validationFirstName")),
      lastName: z.string().trim().min(2, t("validationLastName")),
      email: z.string().trim().email(t("validationEmail")),
      password: z.string().min(8, t("validationPassword")),
      confirmPassword: z.string().min(8, t("validationConfirmPassword")),
      invitationToken: z
        .string()
        .trim()
        .refine(
          (value) => value.length === 0 || value.length >= 20,
          t("validationInvitationToken")
        )
        .optional(),
    })
    .refine((values) => values.password === values.confirmPassword, {
      message: t("passwordMismatch"),
      path: ["confirmPassword"],
    });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export default function RegisterScreen() {
  const { t } = useTranslation("auth");
  const { signUp } = useAuth();
  const schema = buildSchema(t);
  const params = useLocalSearchParams<{ invitationToken?: string }>();
  const invitationTokenParam =
    typeof params.invitationToken === "string" ? params.invitationToken : "";
  const [error, setError] = useState<string | null>(null);
  const {
    control,
    clearErrors,
    handleSubmit,
    setError: setFormError,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      firstName: "",
      lastName: "",
      email: "",
      password: "",
      confirmPassword: "",
      invitationToken: invitationTokenParam,
    },
  });
  const invitationPreviewQuery = useQuery({
    queryKey: ["invitation-preview", invitationTokenParam],
    queryFn: () => previewInvitation(invitationTokenParam),
    enabled: invitationTokenParam.trim().length > 0,
    retry: 0,
  });

  useEffect(() => {
    if (!invitationTokenParam.trim()) {
      return;
    }

    setValue("invitationToken", invitationTokenParam);
  }, [invitationTokenParam, setValue]);

  useEffect(() => {
    if (!invitationPreviewQuery.data) {
      return;
    }

    setValue("email", invitationPreviewQuery.data.email);
  }, [invitationPreviewQuery.data, setValue]);

  const onSubmit = handleSubmit(async (values) => {
    try {
      setError(null);
      clearErrors();
      await signUp({
        firstName: values.firstName,
        lastName: values.lastName,
        email: values.email,
        password: values.password,
        invitationToken: values.invitationToken?.trim() || null,
      });
    } catch (caught) {
      applyApiFieldErrors(
        caught,
        {
          email: "email",
          first_name: "firstName",
          invitation_token: "invitationToken",
          last_name: "lastName",
          password: "password",
          password_confirmation: "confirmPassword",
        },
        setFormError
      );
      setError(resolveErrorMessage(caught, t("registerError")));
    }
  });

  return (
    <AuthLayout
      description={t("welcomeBody")}
      title={t("register")}
    >
      <View style={{ gap: spacing[4] }}>
        {invitationPreviewQuery.data ? (
          <AlertMessage
            message={t("invitationPreview", {
              email: invitationPreviewQuery.data.email,
              workspace: invitationPreviewQuery.data.workspace.name,
              role:
                invitationPreviewQuery.data.role?.name ?? t("guestRoleFallback"),
            })}
          />
        ) : null}
        {invitationPreviewQuery.error ? (
          <AlertMessage
            tone="error"
            message={
              invitationPreviewQuery.error instanceof Error
                ? invitationPreviewQuery.error.message
                : t("invitationPreviewInvalid")
            }
          />
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
          name="invitationToken"
          render={({ field }) => (
            <TextField
              autoCapitalize="none"
              label={t("invitationToken")}
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value ?? ""}
              error={errors.invitationToken?.message}
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
          label={t("submitRegister")}
          loading={isSubmitting}
          onPress={onSubmit}
        />
      </View>
    </AuthLayout>
  );
}
