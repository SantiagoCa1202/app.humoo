import { useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { View } from "react-native";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AppShell } from "@/components/patterns/AppShell";
import { AppButton } from "@/components/primitives/AppButton";
import { TextField } from "@/components/primitives/TextField";

const schema = z.object({
  firstName: z.string().min(2),
  lastName: z.string().min(2),
  timezone: z.string().min(3),
});

type FormValues = z.infer<typeof schema>;

export default function ProfileScreen() {
  const { t } = useTranslation("app");
  const { session, updateProfile } = useAuth();
  const [success, setSuccess] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const profileSyncPending = session?.mode === "api";
  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      firstName: session?.user.firstName ?? "",
      lastName: session?.user.lastName ?? "",
      timezone: session?.user.timezone ?? "America/New_York",
    },
  });

  const onSubmit = handleSubmit(async (values) => {
    try {
      setError(null);
      setSuccess(null);
      await updateProfile(values);
      setSuccess(t("profileSaved"));
    } catch (caught) {
      setError(
        caught instanceof Error
          ? caught.message
          : "Unable to update the profile."
      );
    }
  });

  return (
    <AppShell
      title={t("profileTitle")}
      subtitle={
        profileSyncPending ? t("profileSubtitleApi") : t("profileSubtitleLocal")
      }
    >
      <View style={{ gap: 14, maxWidth: 560 }}>
        {profileSyncPending ? (
          <AlertMessage message={t("profileSyncPending")} />
        ) : null}
        {error ? <AlertMessage tone="error" message={error} /> : null}
        {success ? <AlertMessage tone="success" message={success} /> : null}
        <Controller
          control={control}
          name="firstName"
          render={({ field }) => (
            <TextField
              label="First name"
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
              label="Last name"
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.lastName?.message}
            />
          )}
        />
        <Controller
          control={control}
          name="timezone"
          render={({ field }) => (
            <TextField
              label="Timezone"
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.timezone?.message}
            />
          )}
        />
        <AppButton
          disabled={profileSyncPending}
          label={t("save")}
          loading={isSubmitting}
          onPress={onSubmit}
        />
      </View>
    </AppShell>
  );
}
