import { View } from "react-native";

import { Text, type TextProps } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

type AssistantTextBlockTone = "default" | "muted";

export type AssistantTextBlockProps = {
  accessibilityLabel?: string;
  children?: React.ReactNode;
  selectable?: boolean;
  text?: string;
  tone?: AssistantTextBlockTone;
} & Omit<TextProps, "children" | "selectable" | "tone" | "variant">;

type ListLine = {
  label: string;
  text: string;
};

function headingLine(line: string) {
  let level = 0;

  while (line[level] === "#") {
    level += 1;
  }

  if (level === 0 || line[level] !== " ") {
    return null;
  }

  return { level: Math.min(level, 4), text: line.slice(level + 1).trim() };
}

function unorderedListLine(line: string): ListLine | null {
  if (
    (line.startsWith("- ") || line.startsWith("* ") || line.startsWith("+ ")) &&
    line.length > 2
  ) {
    return { label: "•", text: line.slice(2).trim() };
  }

  return null;
}

function orderedListLine(line: string): ListLine | null {
  let position = 0;

  while (position < line.length && line[position] >= "0" && line[position] <= "9") {
    position += 1;
  }

  if (position === 0 || line[position] !== "." || line[position + 1] !== " ") {
    return null;
  }

  return {
    label: `${line.slice(0, position)}.`,
    text: line.slice(position + 2).trim(),
  };
}

function isHorizontalRule(line: string) {
  return line === "---" || line === "***" || line === "___";
}

function isBlockStart(line: string) {
  return (
    Boolean(headingLine(line)) ||
    Boolean(unorderedListLine(line)) ||
    Boolean(orderedListLine(line)) ||
    line.startsWith("> ") ||
    isHorizontalRule(line)
  );
}

function InlineMarkdown({ text }: { text: string }) {
  const { theme } = useAppTheme();
  const parts: React.ReactNode[] = [];
  let cursor = 0;
  let key = 0;

  const pushText = (value: string) => {
    if (value) {
      parts.push(value);
    }
  };

  while (cursor < text.length) {
    const marker = text.startsWith("**", cursor)
      ? "**"
      : text.startsWith("__", cursor)
        ? "__"
        : text[cursor] === "`"
          ? "`"
          : text[cursor] === "*" || text[cursor] === "_"
            ? text[cursor]
            : null;

    if (!marker) {
      const next = [
        text.indexOf("**", cursor),
        text.indexOf("__", cursor),
        text.indexOf("*", cursor),
        text.indexOf("_", cursor),
        text.indexOf("`", cursor),
      ]
        .filter((position) => position >= 0)
        .sort((left, right) => left - right)[0];

      if (next === undefined) {
        pushText(text.slice(cursor));
        break;
      }

      pushText(text.slice(cursor, next));
      cursor = next;
      continue;
    }

    const end = text.indexOf(marker, cursor + marker.length);

    if (end === -1) {
      pushText(marker);
      cursor += marker.length;
      continue;
    }

    const value = text.slice(cursor + marker.length, end);
    const isStrong = marker === "**" || marker === "__";
    const isCode = marker === "`";

    parts.push(
      <Text
        key={`inline-${key}`}
        style={
          isStrong
            ? {
                fontFamily: theme.typography.family.interfaceSemiBold,
                fontWeight: "600",
              }
            : isCode
              ? {
                  backgroundColor: theme.colors.background.muted,
                  fontFamily: "monospace",
                }
              : { fontStyle: "italic" }
        }
      >
        {value}
      </Text>,
    );
    key += 1;
    cursor = end + marker.length;
  }

  return <>{parts}</>;
}

function MarkdownText({
  accessibilityLabel,
  selectable,
  text,
  tone,
}: Pick<AssistantTextBlockProps, "accessibilityLabel" | "selectable" | "text" | "tone">) {
  const { theme } = useAppTheme();
  const lines = (text ?? "").split("\r\n").join("\n").split("\n");
  const blocks: React.ReactNode[] = [];
  const textTone = tone === "muted" ? "muted" : "default";
  let index = 0;

  const paragraph = (value: string, key: string) => (
    <Text key={key} selectable={selectable} tone={textTone} variant="body">
      <InlineMarkdown text={value} />
    </Text>
  );

  while (index < lines.length) {
    const line = lines[index].trim();

    if (!line) {
      index += 1;
      continue;
    }

    const heading = headingLine(line);
    if (heading) {
      const variant = heading.level === 1 ? "h2" : heading.level === 2 ? "h3" : "h4";
      blocks.push(
        <Text key={`heading-${index}`} selectable={selectable} tone={textTone} variant={variant}>
          <InlineMarkdown text={heading.text} />
        </Text>,
      );
      index += 1;
      continue;
    }

    if (isHorizontalRule(line)) {
      blocks.push(
        <View
          key={`rule-${index}`}
          style={{ backgroundColor: theme.colors.border.subtle, height: 1, width: "100%" }}
        />,
      );
      index += 1;
      continue;
    }

    if (line.startsWith("> ")) {
      const quoted = [line.slice(2).trim()];
      index += 1;

      while (index < lines.length && lines[index].trim().startsWith("> ")) {
        quoted.push(lines[index].trim().slice(2).trim());
        index += 1;
      }

      blocks.push(
        <View
          key={`quote-${index}`}
          style={{
            backgroundColor: theme.colors.background.muted,
            borderColor: theme.colors.brand.primary,
            borderLeftWidth: 3,
            borderRadius: theme.radius.md,
            gap: theme.spacing[1],
            paddingHorizontal: theme.spacing[3],
            paddingVertical: theme.spacing[2],
          }}
        >
          {paragraph(quoted.join("\n"), `quote-text-${index}`)}
        </View>,
      );
      continue;
    }

    const firstListItem = unorderedListLine(line) ?? orderedListLine(line);
    if (firstListItem) {
      const ordered = Boolean(orderedListLine(line));
      const items = [firstListItem];
      index += 1;

      while (index < lines.length) {
        const next = lines[index].trim();
        const item = ordered ? orderedListLine(next) : unorderedListLine(next);

        if (!item) {
          break;
        }

        items.push(item);
        index += 1;
      }

      blocks.push(
        <View key={`list-${index}`} style={{ gap: theme.spacing[2] }}>
          {items.map((item, itemIndex) => (
            <View
              key={`${item.label}-${itemIndex}`}
              style={{ alignItems: "flex-start", flexDirection: "row", gap: theme.spacing[2] }}
            >
              <Text selectable={selectable} tone={textTone} variant="body" style={{ minWidth: theme.spacing[4] }}>
                {item.label}
              </Text>
              <View style={{ flex: 1 }}>
                {paragraph(item.text, `list-text-${itemIndex}`)}
              </View>
            </View>
          ))}
        </View>,
      );
      continue;
    }

    const paragraphLines = [line];
    index += 1;

    while (index < lines.length) {
      const next = lines[index].trim();

      if (!next || isBlockStart(next)) {
        break;
      }

      paragraphLines.push(next);
      index += 1;
    }

    blocks.push(paragraph(paragraphLines.join("\n"), `paragraph-${index}`));
  }

  return (
    <View accessibilityLabel={accessibilityLabel} style={{ gap: theme.spacing[3] }}>
      {blocks}
    </View>
  );
}

/**
 * Displays presentation Markdown returned by the assistant. This is a client-only
 * renderer: it never changes the persisted message or derives application data.
 */
export function AssistantTextBlock({
  accessibilityLabel,
  children,
  selectable = true,
  text,
  tone = "default",
  ...props
}: AssistantTextBlockProps) {
  if (children !== undefined || text === undefined) {
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

  return (
    <MarkdownText
      accessibilityLabel={accessibilityLabel}
      selectable={selectable}
      text={text}
      tone={tone}
    />
  );
}
