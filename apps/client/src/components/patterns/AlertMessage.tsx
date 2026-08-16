import type { AlertTone } from "@/theme/status-config";

import { AlertCard } from "@/components/patterns/alert-card";

type AlertMessageProps = {
  tone?: AlertTone;
  message: string;
};

export function AlertMessage({
  tone = "info",
  message,
}: AlertMessageProps) {
  return (
    <AlertCard
      title={message}
      tone={tone}
      variant="muted"
    />
  );
}
