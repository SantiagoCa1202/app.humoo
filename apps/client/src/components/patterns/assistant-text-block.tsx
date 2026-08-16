import { Text, type TextProps } from "@/components/primitives/text";

type AssistantTextBlockTone = "default" | "muted";

export type AssistantTextBlockProps = {
  accessibilityLabel?: string;
  children?: React.ReactNode;
  selectable?: boolean;
  text?: string;
  tone?: AssistantTextBlockTone;
} & Omit<TextProps, "children" | "selectable" | "tone" | "variant">;

export function AssistantTextBlock({
  accessibilityLabel,
  children,
  selectable = true,
  text,
  tone = "default",
  ...props
}: AssistantTextBlockProps) {
  return (
    <Text
      accessibilityLabel={accessibilityLabel}
      selectable={selectable}
      tone={tone === "muted" ? "muted" : "default"}
      variant="body"
      {...props}
    >
      {children ?? text}
    </Text>
  );
}
