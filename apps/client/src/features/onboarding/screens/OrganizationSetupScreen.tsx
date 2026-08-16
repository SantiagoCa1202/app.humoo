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

const schema = z.object({
  name: z.string().min(2),
  businessType: z.string().min(2),
  countryCode: z.string().min(2).max(2),
  currencyCode: z.string().min(3).max(3),
  timezone: z.string().min(3),
});

type FormValues = z.infer<typeof schema>;

export default function OrganizationSetupScreen() {
  const { t } = useTranslation("app");
  const {
    acceptInvitation,
    createOrganization,
    refreshSession,
    session,
    signOut,
  } = useAuth();
  const [error, setError] = useState<string | null>(null);
  const [invitationToken, setInvitationToken] = useState("");
  const [isAcceptingInvitation, setIsAcceptingInvitation] = useState(false);
  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: "Humoo Test Kitchen",
      businessType: "Catering",
      countryCode: "US",
      currencyCode: "USD",
      timezone: "America/New_York",
    },
  });

  const workspaceAccessPending = session?.mode === "api";

  const onSubmit = handleSubmit(async (values) => {
    try {
      setError(null);
      await createOrganization(values);
      router.replace("/(app)/chat");
    } catch (caught) {
      setError(
        caught instanceof Error
          ? caught.message
          : "Unable to create the workspace."
      );
    }
  });

  return (
    <AuthLayout
      description={t("organizationSetupBody")}
      title={t("organizationSetupTitle")}
    >
      {workspaceAccessPending ? (
        <View style={{ gap: 14 }}>
          <AlertMessage message={t("workspaceAccessPendingBody")} />
          {error ? <AlertMessage tone="error" message={error} /> : null}
          <TextField
            autoCapitalize="none"
            label={t("workspaceInvitationToken")}
            onChangeText={setInvitationToken}
            value={invitationToken}
          />
          <AppButton
            disabled={!invitationToken.trim()}
            label={t("acceptWorkspaceInvitation")}
            loading={isAcceptingInvitation}
            onPress={async () => {
              try {
                setError(null);
                setIsAcceptingInvitation(true);
                await acceptInvitation(invitationToken);
                router.replace("/");
              } catch (caught) {
                setError(
                  caught instanceof Error
                    ? caught.message
                    : "Unable to accept the invitation."
                );
              } finally {
                setIsAcceptingInvitation(false);
              }
            }}
          />
          <AppButton
            label={t("refreshWorkspaceAccess")}
            onPress={async () => {
              try {
                setError(null);
                await refreshSession();
                router.replace("/");
              } catch (caught) {
                setError(
                  caught instanceof Error
                    ? caught.message
                    : "Unable to refresh workspace access."
                );
              }
            }}
          />
          <AppButton
            label={t("signOut")}
            onPress={() => signOut().then(() => router.replace("/(public)/welcome"))}
            variant="secondary"
          />
        </View>
      ) : (
        <View style={{ gap: 14 }}>
          {error ? <AlertMessage tone="error" message={error} /> : null}
          <Controller
            control={control}
            name="name"
            render={({ field }) => (
              <TextField
                label={t("organizationName")}
                onBlur={field.onBlur}
                onChangeText={field.onChange}
                value={field.value}
                error={errors.name?.message}
              />
            )}
          />
          <Controller
            control={control}
            name="businessType"
            render={({ field }) => (
              <TextField
                label={t("businessType")}
                onBlur={field.onBlur}
                onChangeText={field.onChange}
                value={field.value}
                error={errors.businessType?.message}
              />
            )}
          />
          <Controller
            control={control}
            name="countryCode"
            render={({ field }) => (
              <TextField
                autoCapitalize="characters"
                label={t("countryCode")}
                onBlur={field.onBlur}
                onChangeText={field.onChange}
                value={field.value}
                error={errors.countryCode?.message}
              />
            )}
          />
          <Controller
            control={control}
            name="currencyCode"
            render={({ field }) => (
              <TextField
                autoCapitalize="characters"
                label={t("currencyCode")}
                onBlur={field.onBlur}
                onChangeText={field.onChange}
                value={field.value}
                error={errors.currencyCode?.message}
              />
            )}
          />
          <Controller
            control={control}
            name="timezone"
            render={({ field }) => (
              <TextField
                autoCapitalize="none"
                label={t("timezone")}
                onBlur={field.onBlur}
                onChangeText={field.onChange}
                value={field.value}
                error={errors.timezone?.message}
              />
            )}
          />
          <AppButton
            label={t("createOrganization")}
            loading={isSubmitting}
            onPress={onSubmit}
          />
        </View>
      )}
    </AuthLayout>
  );
}
