import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard, type BaseCardProps } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type FormCardProps = Omit<BaseCardProps, "children"> & {
  cancelLabel?: string;
  children?: React.ReactNode;
  error?: React.ReactNode;
  eyebrow?: React.ReactNode;
  footer?: React.ReactNode;
  onCancel?: () => void;
  onSubmit?: () => void;
  submitLabel?: string;
  submitting?: boolean;
  subtitle?: React.ReactNode;
  title?: React.ReactNode;
};

export function FormCard({
  cancelLabel,
  children,
  error,
  eyebrow,
  footer,
  onCancel,
  onSubmit,
  submitLabel,
  submitting = false,
  subtitle,
  title,
  variant = "default",
  ...props
}: FormCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <BaseCard padding="lg" variant={variant} {...props}>
      {title || subtitle || eyebrow ? (
        <CardHeader eyebrow={eyebrow} subtitle={subtitle} title={title} />
      ) : null}
      <CardContent topDivider={Boolean(title || subtitle || eyebrow)}>
        <View style={{ gap: theme.spacing[3] }}>
          {children}
          {error ? (
            typeof error === "string" || typeof error === "number" ? (
              <Text selectable tone="danger" variant="bodySmall">
                {error}
              </Text>
            ) : (
              error
            )
          ) : null}
        </View>
      </CardContent>
      {footer ?? onSubmit ?? onCancel ? (
        <CardFooter align="right" divider>
          {footer ?? (
            <>
              {onCancel ? (
                <Button
                  disabled={props.disabled}
                  label={cancelLabel ?? t("cards.form.cancel")}
                  onPress={onCancel}
                  variant="ghost"
                />
              ) : null}
              {onSubmit ? (
                <Button
                  disabled={props.disabled}
                  label={submitLabel ?? t("cards.form.submit")}
                  loading={submitting}
                  onPress={onSubmit}
                  variant="primary"
                />
              ) : null}
            </>
          )}
        </CardFooter>
      ) : null}
    </BaseCard>
  );
}
