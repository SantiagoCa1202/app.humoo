import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Checkbox } from "@/components/primitives/checkbox";
import { NumberField } from "@/components/primitives/number-field";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import type {
  RecipeStepRecord,
  RecipeStepValidationErrors,
} from "@/features/recipes";
import { useAppTheme } from "@/theme/ThemeProvider";

export type RecipeStepEditorProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  disabled?: boolean;
  errors?: RecipeStepValidationErrors;
  onCancel?: () => void;
  onChange: (value: RecipeStepRecord) => void;
  onSubmit?: () => void;
  position?: number;
  value: RecipeStepRecord;
};

export function RecipeStepEditor({
  accessibilityLabel,
  compact = false,
  disabled = false,
  errors,
  onCancel,
  onChange,
  onSubmit,
  position,
  value,
}: RecipeStepEditorProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("recipes.form.stepEditor.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant="muted"
    >
      <CardHeader
        subtitle={typeof position === "number" ? t("recipes.form.stepEditor.position", { value: position }) : undefined}
        title={t("recipes.form.stepEditor.title")}
      />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          <TextField
            editable={!disabled}
            error={errors?.title}
            label={t("recipes.form.fields.stepTitle.label")}
            onChangeText={(title) => onChange({ ...value, title })}
            placeholder={t("recipes.form.fields.stepTitle.placeholder")}
            value={value.title ?? ""}
          />
          <TextArea
            autoGrow
            editable={!disabled}
            error={errors?.instruction}
            label={t("recipes.form.fields.stepInstruction.label")}
            onChangeText={(instruction) => onChange({ ...value, instruction })}
            placeholder={t("recipes.form.fields.stepInstruction.placeholder")}
            required
            value={value.instruction}
          />
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
            <View style={{ flex: 1, minWidth: 180 }}>
              <NumberField
                disabled={disabled}
                error={errors?.durationMinutes}
                label={t("recipes.form.fields.durationMinutes.label")}
                min={0}
                onChange={(durationMinutes) => onChange({ ...value, durationMinutes })}
                suffix={t("recipes.duration.minutes_other", { count: 0 })}
                value={value.durationMinutes ?? 0}
              />
            </View>
            <View style={{ flex: 1, minWidth: 180 }}>
              <TextField
                editable={!disabled}
                error={errors?.type}
                label={t("recipes.form.fields.stepType.label")}
                onChangeText={(type) => onChange({ ...value, type })}
                placeholder={t("recipes.form.fields.stepType.placeholder")}
                value={value.type ?? ""}
              />
            </View>
          </View>
          <Checkbox
            checked={Boolean(value.critical)}
            disabled={disabled}
            label={t("recipes.form.fields.critical.label")}
            onChange={(critical) => onChange({ ...value, critical })}
          />
          {onCancel || onSubmit ? (
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2], justifyContent: "flex-end" }}>
              {onCancel ? (
                <Button
                  disabled={disabled}
                  label={t("recipes.actions.cancel")}
                  onPress={onCancel}
                  size="sm"
                  variant="ghost"
                />
              ) : null}
              {onSubmit ? (
                <Button
                  disabled={disabled}
                  label={t("recipes.actions.saveStep")}
                  onPress={onSubmit}
                  size="sm"
                  variant="primary"
                />
              ) : null}
            </View>
          ) : null}
        </View>
      </CardContent>
    </BaseCard>
  );
}
