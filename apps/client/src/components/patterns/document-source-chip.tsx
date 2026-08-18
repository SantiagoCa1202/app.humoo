import { View } from "react-native";
import { Badge } from "@/components/primitives/badge";
import { getDocumentSourceLabel } from "@/features/documents";
import { useTranslation } from "react-i18next";

export type DocumentSourceChipProps = {
  accessibilityLabel?: string;
  icon?: React.ReactNode;
  size?: "sm" | "md" | "lg";
  source?: string | null;
  type?: string | null;
};

export function DocumentSourceChip({
  accessibilityLabel,
  icon,
  size = "sm",
  source,
  type,
}: DocumentSourceChipProps) {
  const { t } = useTranslation("common");
  const label = getDocumentSourceLabel(source, t) ?? type?.trim() ?? null;
  if (!label) return null;

  return (
    <View accessibilityLabel={accessibilityLabel ?? label}>
      <Badge icon={icon} label={label} size={size} variant="neutral" />
    </View>
  );
}
