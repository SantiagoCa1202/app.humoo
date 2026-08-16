import { Badge, type BadgeProps } from "@/components/primitives/badge";
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
  const metadata = getStatusMetadata(status, namespace);
  const label = uppercase ? metadata.label.toUpperCase() : metadata.label;

  return (
    <Badge
      dot={showDot}
      label={label}
      size={size}
      variant={metadata.tone}
    />
  );
}
