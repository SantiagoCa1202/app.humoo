import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { Chip } from "@/components/primitives/chip";
import { RadioGroup } from "@/components/primitives/radio-group";
import { Select } from "@/components/primitives/select";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ClarificationOption = {
  description?: string;
  id: string;
  label: string;
  value?: string;
};

export type ClarificationCardProps = {
  accessibilityLabel?: string;
  cancelLabel?: string;
  children?: React.ReactNode;
  description?: React.ReactNode;
  disabled?: boolean;
  loading?: boolean;
  onCancel?: () => void | Promise<void>;
  onSelect: (option: ClarificationOption) => void | Promise<void>;
  onSubmit?: (option: ClarificationOption | null) => void | Promise<void>;
  options: ClarificationOption[];
  customOnly?: boolean;
  optionsPresentation?: "radio" | "select";
  submitDisabled?: boolean;
  selected?: string;
  selectionMode?: "single" | "immediate";
  submitLabel?: string;
  title?: React.ReactNode;
};

export function ClarificationCard({
  accessibilityLabel,
  cancelLabel,
  children,
  description,
  disabled = false,
  loading = false,
  onCancel,
  onSelect,
  onSubmit,
  options,
  customOnly = false,
  optionsPresentation = "radio",
  submitDisabled = false,
  selected,
  selectionMode = "single",
  submitLabel,
  title,
}: ClarificationCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const selectedOption =
    options.find((option) => option.id === selected || option.value === selected) ??
    (customOnly && selected === "custom"
      ? { id: "custom", label: "", value: "custom" }
      : null);

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ?? t("chat.blocks.clarification.accessibilityLabel")
      }
      disabled={disabled}
      padding="md"
      radius="lg"
      variant="elevated"
    >
      <CardHeader
        padding="none"
        subtitle={description}
        title={title ?? t("chat.blocks.clarification.title")}
      />
      <CardContent padding="none">
        {selectionMode === "single" && !customOnly ? optionsPresentation === "select" ? (
          <Select
            accessibilityLabel={t("chat.blocks.clarification.optionsAccessibilityLabel")}
            disabled={disabled || loading}
            onChange={(value) => {
              const option = options.find(
                (item) => item.id === value || item.value === value
              );

              if (option) {
                void onSelect(option);
              }
            }}
            options={options.map((option) => ({
              disabled,
              label: option.label,
              value: option.value ?? option.id,
            }))}
            placeholder={t("chat.blocks.clarification.selectOption")}
            value={selectedOption?.value ?? selectedOption?.id}
          />
        ) : (
          <RadioGroup
            accessibilityLabel={t("chat.blocks.clarification.optionsAccessibilityLabel")}
            disabled={disabled || loading}
            onChange={(value) => {
              const option = options.find(
                (item) => item.id === value || item.value === value
              );

              if (option) {
                void onSelect(option);
              }
            }}
            options={options.map((option) => ({
              description: option.description,
              disabled,
              label: option.label,
              value: option.value ?? option.id,
            }))}
            value={selectedOption ? selectedOption.value ?? selectedOption.id : undefined}
          />
        ) : (
          <View
            accessibilityLabel={t("chat.blocks.clarification.optionsAccessibilityLabel")}
            style={{
              flexDirection: "row",
              flexWrap: "wrap",
              gap: theme.spacing[2],
            }}
          >
            {options.map((option) => {
              const optionValue = option.value ?? option.id;

              return (
                <Chip
                  accessibilityLabel={option.label}
                  disabled={disabled || loading}
                  key={option.id}
                  label={option.label}
                  onPress={() => void onSelect(option)}
                  selected={selected === option.id || selected === optionValue}
                  variant="neutral"
                />
              );
            })}
          </View>
        )}
        {children}
      </CardContent>
      {selectionMode === "single" || onCancel ? (
        <CardFooter align="right" divider padding="none">
          {onCancel ? (
            <Button
              accessibilityLabel={cancelLabel ?? t("chat.blocks.clarification.cancel")}
              disabled={disabled || loading}
              label={cancelLabel ?? t("chat.blocks.clarification.cancel")}
              onPress={onCancel}
              size="sm"
              variant="secondary"
            />
          ) : null}
          {selectionMode === "single" ? (
            <Button
              accessibilityLabel={submitLabel ?? t("chat.blocks.clarification.submit")}
              disabled={disabled || loading || submitDisabled || !selectedOption}
              label={submitLabel ?? t("chat.blocks.clarification.submit")}
              loading={loading}
              onPress={onSubmit ? () => void onSubmit(selectedOption) : undefined}
              size="sm"
              variant="primary"
            />
          ) : null}
        </CardFooter>
      ) : null}
    </BaseCard>
  );
}
