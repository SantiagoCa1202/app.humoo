import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Avatar } from "@/components/primitives/avatar";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import { UserPicker } from "@/components/primitives/user-picker";
import {
  getPrepAssignmentLabel,
  type PrepAssignmentOption,
  type PrepItemAssignmentRecord,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

const UNASSIGNED_VALUE = "__prep-unassigned__";

export type PrepAssignmentProps = {
  accessibilityLabel?: string;
  assignment?: PrepItemAssignmentRecord | null;
  candidates?: PrepAssignmentOption[];
  compact?: boolean;
  disabled?: boolean;
  editable?: boolean;
  onChange?: (membershipId: string) => void;
  onClear?: () => void;
};

export function PrepAssignment({
  accessibilityLabel,
  assignment,
  candidates,
  compact = false,
  disabled = false,
  editable = false,
  onChange,
  onClear,
}: PrepAssignmentProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const assignmentLabel = getPrepAssignmentLabel(assignment);

  if (editable && candidates?.length) {
    return (
      <View accessibilityLabel={accessibilityLabel ?? t("prep.assignment.accessibilityLabel")}>
        <UserPicker
          disabled={disabled}
          label={t("prep.labels.assignedTo")}
          onChange={(value) => {
            if (value === UNASSIGNED_VALUE) {
              onClear?.();
              return;
            }

            onChange?.(value);
          }}
          placeholder={t("prep.assignment.placeholder")}
          users={[
            {
              label: t("prep.assignment.unassigned"),
              value: UNASSIGNED_VALUE,
            },
            ...candidates,
          ]}
          value={assignment?.membershipId ?? undefined}
        />
      </View>
    );
  }

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("prep.assignment.accessibilityLabel")}
      style={{ alignItems: "center", flexDirection: "row", gap: theme.spacing[2] }}
    >
      <Avatar
        name={assignmentLabel ?? t("prep.assignment.unassigned")}
        size={compact ? "sm" : "md"}
        source={assignment?.user?.source}
        variant={assignmentLabel ? "neutral" : "warning"}
      />
      <View style={{ flex: 1, gap: theme.spacing[1] }}>
        <Text selectable variant={compact ? "bodySmall" : "body"}>
          {assignmentLabel ?? t("prep.assignment.unassigned")}
        </Text>
        {assignment?.roleLabel?.trim() ? (
          <Text selectable tone="muted" variant="caption">
            {assignment.roleLabel.trim()}
          </Text>
        ) : null}
      </View>
      {editable && onClear && assignmentLabel ? (
        <Button
          disabled={disabled}
          label={t("prep.assignment.clear")}
          onPress={onClear}
          size="sm"
          variant="ghost"
        />
      ) : null}
    </View>
  );
}
