import { View } from "react-native";

import { Skeleton } from "@/components/primitives/skeleton";
import { useAppTheme } from "@/theme/ThemeProvider";

export type SkeletonTextProps = {
  lines?: number;
  lineHeight?: number;
  gap?: number;
  lastLineWidth?: number | `${number}%`;
};

export function SkeletonText({
  lines = 3,
  lineHeight,
  gap,
  lastLineWidth = "80%",
}: SkeletonTextProps) {
  const { theme } = useAppTheme();
  const resolvedLineHeight = lineHeight ?? theme.typography.styles.body.lineHeight;
  const resolvedGap = gap ?? theme.spacing[2];

  return (
    <View style={{ gap: resolvedGap, width: "100%" }}>
      {Array.from({ length: lines }).map((_, index) => (
        <Skeleton
          height={resolvedLineHeight}
          key={`skeleton-line-${index}`}
          radius="sm"
          variant="text"
          width={index === lines - 1 && lines > 1 ? lastLineWidth : "100%"}
        />
      ))}
    </View>
  );
}
