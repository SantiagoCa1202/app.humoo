import { useState } from "react";
import { Pressable, View } from "react-native";
import { useTranslation } from "react-i18next";

import { SelectBase } from "@/components/primitives/select-base";
import { StatusBadge } from "@/components/primitives/status-badge";
import { Text } from "@/components/primitives/text";
import {
  getStatusMetadata,
  type AppOperationalStatus,
  type StatusConfigNamespace,
} from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

export type StatusSelectOption = {
  disabled?: boolean;
  namespace?: StatusConfigNamespace;
  value: AppOperationalStatus;
};

export type StatusSelectProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  error?: string;
  helperText?: string;
  label?: string;
  namespace?: StatusConfigNamespace;
  onChange: (value: AppOperationalStatus) => void;
  options: StatusSelectOption[];
  value?: AppOperationalStatus;
};

export function StatusSelect({
  accessibilityLabel,
  disabled = false,
  error,
  helperText,
  label,
  namespace,
  onChange,
  options,
  value,
}: StatusSelectProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const [open, setOpen] = useState(false);
  const selectedOption = options.find((option) => option.value === value);

  return (
    <SelectBase
      accessibilityLabel={accessibilityLabel ?? label}
      disabled={disabled}
      error={error}
      helperText={helperText}
      label={label}
      onDismiss={() => setOpen(false)}
      onOpen={() => setOpen(true)}
      open={open}
      placeholder={t("forms.statusSelect.placeholder")}
      renderOptions={() => (
        <View style={{ gap: theme.spacing[1] }}>
          {options.map((option) => {
            const optionNamespace = option.namespace ?? namespace;
            const metadata = getStatusMetadata(option.value, optionNamespace);
            const isSelected = option.value === value;

            return (
              <Pressable
                key={`${optionNamespace ?? "status"}-${option.value}`}
                disabled={option.disabled}
                onPress={() => {
                  onChange(option.value);
                  setOpen(false);
                }}
                style={({ hovered, pressed }) => ({
                  backgroundColor: isSelected
                    ? theme.colors.brand.soft
                    : pressed || hovered
                    ? theme.colors.background.subtle
                    : "transparent",
                  borderColor: isSelected
                    ? theme.colors.brand.primary
                    : "transparent",
                  borderCurve: "continuous",
                  borderRadius: theme.radius.md,
                  borderWidth: 1,
                  opacity: option.disabled ? 0.6 : 1,
                  paddingHorizontal: theme.spacing[3],
                  paddingVertical: theme.spacing[3],
                })}
              >
                <StatusBadge
                  namespace={optionNamespace}
                  showDot
                  size="md"
                  status={option.value}
                />
                {!isSelected ? null : (
                  <Text tone="muted" variant="caption">
                    {t(metadata.translationKey)}
                  </Text>
                )}
              </Pressable>
            );
          })}
        </View>
      )}
      triggerContent={
        selectedOption ? (
          <StatusBadge
            namespace={selectedOption.namespace ?? namespace}
            showDot
            size="md"
            status={selectedOption.value}
          />
        ) : (
          <Text variant="body">{t("forms.statusSelect.placeholder")}</Text>
        )
      }
      triggerValueEmpty={!selectedOption}
    />
  );
}
