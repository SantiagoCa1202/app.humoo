import { Badge, type BadgeProps } from "@/components/primitives/badge";
import { useTranslation } from "react-i18next";
import {
  getStatusMetadata,
  type AppOperationalStatus,
  type StatusConfigNamespace,
} from "@/theme/status-config";

export type StatusBadgeProps = {
  namespace?: StatusConfigNamespace;
  showDot?: boolean;
  size?: BadgeProps["size"];
  status: AppOperationalStatus;
  uppercase?: boolean;
};

export function StatusBadge({
  namespace,
  showDot = true,
  size = "md",
  status,
  uppercase = false,
}: StatusBadgeProps) {
  const { t } = useTranslation("common");
  const metadata = getStatusMetadata(status, namespace);
  const resolvedLabel = t(metadata.translationKey);
  const label = uppercase ? resolvedLabel.toUpperCase() : resolvedLabel;

  return (
    <Badge
      dot={showDot}
      label={label}
      size={size}
      variant={metadata.tone}
    />
  );
}
