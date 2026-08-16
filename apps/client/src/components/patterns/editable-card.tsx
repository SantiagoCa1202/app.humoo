import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard, type BaseCardProps } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type EditableCardProps = Omit<BaseCardProps, "children"> & {
  children?: React.ReactNode;
  editing?: boolean;
  error?: React.ReactNode;
  eyebrow?: React.ReactNode;
  loading?: boolean;
  onCancel?: () => void;
  onEdit?: () => void;
  onSave?: () => void;
  renderContent?: () => React.ReactNode;
  renderEditor?: () => React.ReactNode;
  subtitle?: React.ReactNode;
  title: React.ReactNode;
};

export function EditableCard({
  children,
  editing = false,
  error,
  eyebrow,
  loading = false,
  onCancel,
  onEdit,
  onSave,
  renderContent,
  renderEditor,
  subtitle,
  title,
  variant = "default",
  ...props
}: EditableCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <BaseCard padding="lg" variant={variant} {...props}>
      <CardHeader eyebrow={eyebrow} subtitle={subtitle} title={title} />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          {editing
            ? renderEditor?.() ?? children
            : renderContent?.() ?? children}
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
      <CardFooter align="right" divider>
        {editing ? (
          <>
            {onCancel ? (
              <Button
                disabled={props.disabled}
                label={t("cards.editable.cancel")}
                onPress={onCancel}
                variant="ghost"
              />
            ) : null}
            {onSave ? (
              <Button
                disabled={props.disabled}
                label={t("cards.editable.save")}
                loading={loading}
                onPress={onSave}
                variant="primary"
              />
            ) : null}
          </>
        ) : onEdit ? (
          <Button
            disabled={props.disabled}
            label={t("cards.editable.edit")}
            onPress={onEdit}
            variant="secondary"
          />
        ) : null}
      </CardFooter>
    </BaseCard>
  );
}
