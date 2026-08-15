import { useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { View } from "react-native";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslation } from "react-i18next";

import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AuthLayout } from "@/components/patterns/AuthLayout";
import { AppButton } from "@/components/primitives/AppButton";
import { TextField } from "@/components/primitives/TextField";

const schema = z.object({
  token: z.string().min(4),
  password: z.string().min(8),
});

type FormValues = z.infer<typeof schema>;

export default function ResetPasswordScreen() {
  const { t } = useTranslation(["auth", "app"]);
  const [success, setSuccess] = useState<string | null>(null);
  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      token: "",
      password: "",
    },
  });

  const onSubmit = handleSubmit(async () => {
    setSuccess(t("app:resetPasswordSaved"));
  });

  return (
    <AuthLayout
      description={t("resetPasswordBody")}
      title={t("submitResetPassword")}
    >
      <View style={{ gap: 14 }}>
        {success ? <AlertMessage tone="success" message={success} /> : null}
        <Controller
          control={control}
          name="token"
          render={({ field }) => (
            <TextField
              autoCapitalize="none"
              label="Token"
              onBlur={field.onBlur}
              onChangeText={field.onChange}
              value={field.value}
              error={errors.token?.message}
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
      </View>
    </AuthLayout>
  );
}
