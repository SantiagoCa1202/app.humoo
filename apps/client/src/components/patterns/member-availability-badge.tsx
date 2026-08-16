import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import {
  getMemberAvailabilityMetadata,
  type MemberAvailabilityRecord,
} from "@/features/team-staff";

export type MemberAvailabilityBadgeProps = {
  accessibilityLabel?: string;
  availability?: MemberAvailabilityRecord | null;
  showDot?: boolean;
  size?: "sm" | "md" | "lg";
};

export function MemberAvailabilityBadge({
  accessibilityLabel,
  availability,
  showDot = true,
  size = "md",
}: MemberAvailabilityBadgeProps) {
  const { t } = useTranslation("common");
  const metadata = getMemberAvailabilityMetadata(availability);

  if (!metadata) {
    return null;
  }

  const label = t(metadata.translationKey);

  return (
    <View accessibilityLabel={accessibilityLabel ?? label} accessible>
      <Badge dot={showDot} label={label} size={size} variant={metadata.tone} />
    </View>
  );
}
