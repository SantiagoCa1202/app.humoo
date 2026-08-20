import { useState } from "react";
import { Link, router } from "expo-router";
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
import { spacing } from "@/theme";
import { useAppTheme } from "@/theme/ThemeProvider";

const schema = z.object({
  email: z.email(),
  password: z.string().min(8),
});

const SOCIAL_BUTTON_MIN_WIDTH = 160;

type FormValues = z.infer<typeof schema>;

export default function LoginScreen() {
  const { t } = useTranslation("auth");
  const { signIn } = useAuth();
  const { theme } = useAppTheme();
  const [error, setError] = useState<string | null>(null);

  const {
    control,
    handleSubmit,
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
      await signIn(values);
      router.replace("/");
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : t("loginError"));
    }
  });

  return (
    <AuthLayout description={t("loginBody")} title={t("loginTitle")}>
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
              placeholder={t("emailPlaceholder")}
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
              placeholder={t("passwordPlaceholder")}
              secure
              value={field.value}
              error={errors.password?.message}
            />
          )}
        />

        <View style={{ alignItems: "flex-end" }}>
          <Link href="/(public)/forgot-password">
            <AppText tone="primary" variant="bodyMedium">
              {t("forgotPassword")}
            </AppText>
          </Link>
        </View>

        <AppButton
          fullWidth
          label={t("submitLogin")}
          loading={isSubmitting}
          onPress={onSubmit}
        />

        <View
          style={{
            alignItems: "center",
            flexDirection: "row",
            gap: spacing[3],
          }}
        >
          <View
            style={{
              backgroundColor: theme.colors.border.subtle,
              flex: 1,
              height: 1,
            }}
          />
          <AppText muted variant="bodySmall">
            {t("continueWith")}
          </AppText>
          <View
            style={{
              backgroundColor: theme.colors.border.subtle,
              flex: 1,
              height: 1,
            }}
          />
        </View>

        <View
          style={{
            flexDirection: "row",
            flexWrap: "wrap",
            gap: spacing[3],
          }}
        >
          <AppButton
            accessibilityLabel={t("continueGoogle")}
            containerStyle={{ flex: 1, minWidth: SOCIAL_BUTTON_MIN_WIDTH }}
            disabled
            fullWidth
            label={t("google")}
            variant="outline"
          />
          <AppButton
            accessibilityLabel={t("continueApple")}
            containerStyle={{ flex: 1, minWidth: SOCIAL_BUTTON_MIN_WIDTH }}
            disabled
            fullWidth
            label={t("apple")}
            variant="outline"
          />
        </View>

        <AppText muted style={{ textAlign: "center" }} variant="bodySmall">
          {t("socialUnavailable")}
        </AppText>

        <View
          style={{
            alignItems: "center",
            flexDirection: "row",
            flexWrap: "wrap",
            gap: spacing[1],
            justifyContent: "center",
          }}
        >
          <AppText muted>{t("noAccount")}</AppText>
          <Link href="/(public)/register">
            <AppText tone="primary" variant="bodyMedium">
              {t("register")}
            </AppText>
          </Link>
        </View>

        <AppText
          muted
          style={{ textAlign: "center" }}
          variant="bodySmall"
        >
          {t("legalPrefix")}{" "}
          <AppText tone="primary" variant="bodySmall">
            {t("terms")}
          </AppText>{" "}
          {t("legalAnd")}{" "}
          <AppText tone="primary" variant="bodySmall">
            {t("privacy")}
          </AppText>
          .
        </AppText>
      </View>
    </AuthLayout>
  );
}
