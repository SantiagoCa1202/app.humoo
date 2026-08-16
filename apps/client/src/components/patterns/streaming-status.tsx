import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Spinner } from "@/components/primitives/spinner";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

type StreamingStepStatus = "pending" | "active" | "done" | "error";

export type StreamingStep = {
  id: string;
  label: string;
  status: StreamingStepStatus;
};

export type StreamingStatusProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  description?: React.ReactNode;
  steps?: StreamingStep[];
  title?: React.ReactNode;
};

function StreamingStepIcon({ status }: { status: StreamingStepStatus }) {
  const { theme } = useAppTheme();

  if (status === "active") {
    return <Spinner size="sm" variant="primary" />;
  }

  return (
    <Text
      accessibilityLabel={undefined}
      tone={
        status === "done"
          ? "success"
          : status === "error"
          ? "danger"
          : "secondary"
      }
      variant="caption"
      style={{
        minWidth: theme.spacing[4],
        textAlign: "center",
      }}
    >
      {status === "done" ? "OK" : status === "error" ? "!" : "..."}
    </Text>
  );
}

export function StreamingStatus({
  accessibilityLabel,
  compact = false,
  description,
  steps = [],
  title,
}: StreamingStatusProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const titleNode = title ?? t("chat.blocks.streaming.title");
  const descriptionNode = description ?? t("chat.blocks.streaming.description");

  return (
    <View
      accessibilityLabel={
        accessibilityLabel ?? t("chat.blocks.streaming.accessibilityLabel")
      }
      style={{
        gap: compact ? theme.spacing[2] : theme.spacing[3],
        width: "100%",
      }}
    >
      {typeof titleNode === "string" || typeof titleNode === "number" ? (
        <Text variant={compact ? "label" : "title"}>{titleNode}</Text>
      ) : (
        titleNode
      )}
      {descriptionNode ? (
        typeof descriptionNode === "string" || typeof descriptionNode === "number" ? (
          <Text tone="secondary" variant={compact ? "caption" : "bodySmall"}>
            {descriptionNode}
          </Text>
        ) : (
          descriptionNode
        )
      ) : null}
      {steps.length ? (
        <View style={{ gap: theme.spacing[2] }}>
          {steps.map((step) => (
            <View
              accessibilityLabel={t("chat.blocks.streaming.stepAccessibilityLabel", {
                label: step.label,
                status: t(`chat.blocks.streaming.steps.status.${step.status}`),
              })}
              key={step.id}
              style={{
                alignItems: "center",
                flexDirection: "row",
                gap: theme.spacing[2],
              }}
            >
              <StreamingStepIcon status={step.status} />
              <Text
                tone={
                  step.status === "done"
                    ? "success"
                    : step.status === "error"
                    ? "danger"
                    : step.status === "active"
                    ? "primary"
                    : "secondary"
                }
                variant={compact ? "caption" : "bodySmall"}
              >
                {step.label}
              </Text>
            </View>
          ))}
        </View>
      ) : null}
    </View>
  );
}
