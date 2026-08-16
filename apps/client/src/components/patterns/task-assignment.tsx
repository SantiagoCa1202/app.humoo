import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Avatar } from "@/components/primitives/avatar";
import { AvatarGroup } from "@/components/primitives/avatar-group";
import { Button } from "@/components/primitives/button";
import { MultiSelect } from "@/components/primitives/multi-select";
import { Text } from "@/components/primitives/text";
import { UserPicker } from "@/components/primitives/user-picker";
import {
  getTaskPrimaryAssignment,
  type TaskAssignmentOption,
  type TaskAssignmentRecord,
} from "@/features/tasks";
import { useAppTheme } from "@/theme/ThemeProvider";

const UNASSIGNED_VALUE = "__task-unassigned__";

export type TaskAssignmentProps = {
  accessibilityLabel?: string;
  assignments?: TaskAssignmentRecord[] | null;
  candidates?: TaskAssignmentOption[];
  compact?: boolean;
  disabled?: boolean;
  editable?: boolean;
  multiple?: boolean;
  onChange?: (membershipIds: string[]) => void;
  onClear?: () => void;
};

export function TaskAssignment({
  accessibilityLabel,
  assignments,
  candidates,
  compact = false,
  disabled = false,
  editable = false,
  multiple = false,
  onChange,
  onClear,
}: TaskAssignmentProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedAssignments = assignments?.filter(Boolean) ?? [];
  const primaryAssignment = getTaskPrimaryAssignment(resolvedAssignments);
  const assignedMembershipIds = resolvedAssignments
    .map((assignment) => assignment.membershipId?.trim() ?? "")
    .filter(Boolean);
  const assignedUsers = resolvedAssignments
    .map((assignment) => ({
      membershipId: assignment.membershipId?.trim() ?? "",
      name: assignment.user?.name?.trim() ?? assignment.roleLabel?.trim() ?? "",
      roleLabel: assignment.roleLabel?.trim() ?? null,
      source: assignment.user?.source,
    }))
    .filter((assignment) => assignment.name);

  if (editable && candidates?.length) {
    if (multiple) {
      return (
        <View accessibilityLabel={accessibilityLabel ?? t("tasks.assignment.accessibilityLabel")}>
          <MultiSelect
            accessibilityLabel={t("tasks.assignment.accessibilityLabel")}
            disabled={disabled}
            label={t("tasks.labels.assignedTo")}
            onChange={(membershipIds) => onChange?.(membershipIds)}
            options={candidates.map((candidate) => ({
              disabled: candidate.disabled,
              label: candidate.label ?? candidate.name ?? candidate.value,
              value: candidate.value,
            }))}
            placeholder={t("tasks.assignment.placeholder")}
            values={assignedMembershipIds}
          />
          {assignedMembershipIds.length && onClear ? (
            <View style={{ marginTop: theme.spacing[2] }}>
              <Button
                disabled={disabled}
                label={t("tasks.assignment.clear")}
                onPress={onClear}
                size="sm"
                variant="ghost"
              />
            </View>
          ) : null}
        </View>
      );
    }

    return (
      <View accessibilityLabel={accessibilityLabel ?? t("tasks.assignment.accessibilityLabel")}>
        <UserPicker
          disabled={disabled}
          label={t("tasks.labels.assignedTo")}
          onChange={(value) => {
            if (value === UNASSIGNED_VALUE) {
              onClear?.();
              return;
            }

            onChange?.([value]);
          }}
          placeholder={t("tasks.assignment.placeholder")}
          users={[
            {
              label: t("tasks.assignment.unassigned"),
              value: UNASSIGNED_VALUE,
            },
            ...candidates,
          ]}
          value={primaryAssignment?.membershipId ?? undefined}
        />
      </View>
    );
  }

  if (assignedUsers.length > 1) {
    return (
      <View
        accessibilityLabel={accessibilityLabel ?? t("tasks.assignment.accessibilityLabel")}
        style={{ gap: theme.spacing[2] }}
      >
        <AvatarGroup
          size={compact ? "sm" : "md"}
          users={assignedUsers.map((assignment) => ({
            name: assignment.name,
            source: assignment.source,
            variant: "neutral",
          }))}
        />
        <Text selectable variant={compact ? "bodySmall" : "body"}>
          {assignedUsers.map((assignment) => assignment.name).join(", ")}
        </Text>
      </View>
    );
  }

  const singleAssignment = assignedUsers[0];

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("tasks.assignment.accessibilityLabel")}
      style={{ alignItems: "center", flexDirection: "row", gap: theme.spacing[2] }}
    >
      <Avatar
        name={singleAssignment?.name ?? t("tasks.assignment.unassigned")}
        size={compact ? "sm" : "md"}
        source={singleAssignment?.source}
        variant={singleAssignment?.name ? "neutral" : "warning"}
      />
      <View style={{ flex: 1, gap: theme.spacing[1] }}>
        <Text selectable variant={compact ? "bodySmall" : "body"}>
          {singleAssignment?.name ?? t("tasks.assignment.unassigned")}
        </Text>
        {singleAssignment?.roleLabel ? (
          <Text selectable tone="muted" variant="caption">
            {singleAssignment.roleLabel}
          </Text>
        ) : null}
      </View>
      {editable && onClear && singleAssignment?.name ? (
        <Button
          disabled={disabled}
          label={t("tasks.assignment.clear")}
          onPress={onClear}
          size="sm"
          variant="ghost"
        />
      ) : null}
    </View>
  );
}
