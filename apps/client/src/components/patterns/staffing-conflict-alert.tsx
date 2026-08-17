import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { AlertCard } from "@/components/patterns/alert-card";
import { ComparisonCard } from "@/components/patterns/comparison-card";
import { MemberCard } from "@/components/patterns/member-card";
import { ShiftCard } from "@/components/patterns/shift-card";
import { StationCard } from "@/components/patterns/station-card";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  buildStaffingConflictComparison,
  type StaffingConflictRecord,
} from "@/features/team-staff";
import { useAppTheme } from "@/theme/ThemeProvider";

function getConflictTitleKey(type: StaffingConflictRecord["type"]) {
  if (type === "unavailable") {
    return "teamStaff.conflicts.types.unavailable";
  }

  if (type === "overtime") {
    return "teamStaff.conflicts.types.overtime";
  }

  if (type === "station_capacity") {
    return "teamStaff.conflicts.types.station_capacity";
  }

  if (type === "event_overlap") {
    return "teamStaff.conflicts.types.event_overlap";
  }

  return "teamStaff.conflicts.types.overlap";
}

function getConflictTone(severity?: StaffingConflictRecord["severity"] | null) {
  if (severity === "critical") {
    return "error" as const;
  }

  if (severity === "warning") {
    return "warning" as const;
  }

  return "info" as const;
}

export type StaffingConflictAlertProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  conflict?: StaffingConflictRecord | null;
  conflicts?: StaffingConflictRecord[];
  onDismiss?: () => void | Promise<void>;
  onEditShift?: () => void | Promise<void>;
  onReassign?: () => void | Promise<void>;
  onReview?: () => void | Promise<void>;
};

export function StaffingConflictAlert({
  accessibilityLabel,
  compact = false,
  conflict,
  conflicts,
  onDismiss,
  onEditShift,
  onReassign,
  onReview,
}: StaffingConflictAlertProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const resolvedConflict = conflict ?? conflicts?.[0] ?? null;

  if (!resolvedConflict) {
    return null;
  }

  const changes = buildStaffingConflictComparison(resolvedConflict, i18n.language);
  const title = t(getConflictTitleKey(resolvedConflict.type));

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("teamStaff.conflicts.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <AlertCard
        actionLabel={onReview ? t("teamStaff.actions.reviewSchedule") : undefined}
        description={
          <View style={{ gap: theme.spacing[3] }}>
            <Text selectable variant="bodySmall">
              {resolvedConflict.message?.trim() ??
                t("teamStaff.conflicts.defaultMessage")}
            </Text>
            {typeof resolvedConflict.requiredStaff === "number" &&
            typeof resolvedConflict.assignedStaff === "number" ? (
              <Text selectable tone="secondary" variant="caption">
                {t("teamStaff.conflicts.staffingCounts", {
                  assigned: resolvedConflict.assignedStaff,
                  required: resolvedConflict.requiredStaff,
                })}
              </Text>
            ) : null}
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
              {onReview ? (
                <Button
                  label={t("teamStaff.actions.review")}
                  onPress={onReview}
                  size="sm"
                  variant="primary"
                />
              ) : null}
              {onEditShift ? (
                <Button
                  label={t("teamStaff.actions.editShift")}
                  onPress={onEditShift}
                  size="sm"
                  variant="secondary"
                />
              ) : null}
              {onReassign ? (
                <Button
                  label={t("teamStaff.actions.reassign")}
                  onPress={onReassign}
                  size="sm"
                  variant="ghost"
                />
              ) : null}
            </View>
          </View>
        }
        dismissible={Boolean(onDismiss)}
        onDismiss={onDismiss ? () => void onDismiss() : undefined}
        title={title}
        tone={getConflictTone(resolvedConflict.severity)}
        variant="muted"
      />
      {changes.length ? (
        <ComparisonCard
          changes={changes.map((change) => ({
            ...change,
            label: t(change.label as string),
          }))}
          subtitle={t("teamStaff.conflicts.reviewSubtitle")}
          title={t("teamStaff.conflicts.reviewTitle")}
          variant="outlined"
        />
      ) : null}
      {resolvedConflict.member ? (
        <MemberCard compact={compact} member={resolvedConflict.member} />
      ) : null}
      {resolvedConflict.shift ? (
        <ShiftCard compact={compact} shift={resolvedConflict.shift} />
      ) : null}
      {resolvedConflict.relatedShift ? (
        <ShiftCard compact={compact} shift={resolvedConflict.relatedShift} />
      ) : null}
      {resolvedConflict.station ? (
        <StationCard compact={compact} station={resolvedConflict.station} />
      ) : null}
    </View>
  );
}
