import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Avatar } from "@/components/primitives/avatar";
import { AvatarGroup } from "@/components/primitives/avatar-group";
import { Button } from "@/components/primitives/button";
import { StatusBadge } from "@/components/primitives/status-badge";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";
import {
  getEventStaffRole,
  type EventStaffMemberValue,
} from "@/features/events";

export type EventStaffCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  maxVisible?: number;
  members?: EventStaffMemberValue[];
  onManage?: () => void | Promise<void>;
  onMemberPress?: (member: EventStaffMemberValue) => void | Promise<void>;
  title?: React.ReactNode;
};

export function EventStaffCard({
  accessibilityLabel,
  compact = false,
  maxVisible = 4,
  members = [],
  onManage,
  onMemberPress,
  title,
}: EventStaffCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const visibleMembers = members.slice(0, maxVisible);

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel ?? t("events.related.staff.accessibilityLabel")}
      padding={compact ? "md" : "lg"}
      variant={compact ? "muted" : "default"}
    >
      <CardHeader
        subtitle={
          members.length > 0 ? (
            <AvatarGroup
              max={maxVisible}
              size={compact ? "sm" : "md"}
              users={members.map((member) => ({
                name: member.name,
                status: member.presence,
                source: member.source,
                variant: member.variant ?? "neutral",
              }))}
            />
          ) : undefined
        }
        title={title ?? t("events.related.staff.title")}
        trailing={
          onManage ? (
            <Button
              label={t("events.related.staff.manage")}
              onPress={onManage}
              size="sm"
              variant="ghost"
            />
          ) : undefined
        }
      />
      <CardContent topDivider>
        {members.length === 0 ? (
          <Text tone="muted" variant="bodySmall">
            {t("events.related.staff.empty")}
          </Text>
        ) : (
          <View style={{ gap: theme.spacing[3] }}>
            {visibleMembers.map((member, index) => {
              const role = getEventStaffRole(member);

              return (
                <BaseCard
                  accessibilityLabel={`${member.name ?? t("events.related.staff.memberFallback")} ${role ?? ""}`.trim()}
                  key={member.workspaceMembershipId ?? member.id ?? `staff-${index}`}
                  onPress={onMemberPress ? () => onMemberPress(member) : undefined}
                  padding="md"
                  radius="md"
                  variant="muted"
                >
                  <View
                    style={{
                      alignItems: "center",
                      flexDirection: "row",
                      gap: theme.spacing[3],
                      justifyContent: "space-between",
                    }}
                  >
                    <View style={{ alignItems: "center", flex: 1, flexDirection: "row", gap: theme.spacing[3] }}>
                      <Avatar
                        name={member.name}
                        status={member.presence}
                        size={compact ? "sm" : "md"}
                        source={member.source}
                        variant={member.variant ?? "neutral"}
                      />
                      <View style={{ flex: 1, gap: theme.spacing[1] }}>
                        <Text selectable variant="bodyMedium">
                          {member.name ?? t("events.related.staff.memberFallback")}
                        </Text>
                        {role ? (
                          <Text tone="secondary" variant="bodySmall">
                            {member.roleTranslationKey ? t(member.roleTranslationKey) : role}
                          </Text>
                        ) : null}
                      </View>
                    </View>
                    {member.membershipStatus ? (
                      <StatusBadge
                        namespace="workspaceMembers"
                        showDot
                        size="sm"
                        status={member.membershipStatus}
                      />
                    ) : null}
                  </View>
                </BaseCard>
              );
            })}
          </View>
        )}
      </CardContent>
    </BaseCard>
  );
}
