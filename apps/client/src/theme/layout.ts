import { humooContentWidths } from "@/theme/breakpoints";
import { spacing } from "@/theme/spacing";

export const humooLayout = {
  content: humooContentWidths,
  sidebarWidth: 272,
  screenPadding: spacing[5],
  cardPadding: spacing[5],
  controlHeight: 40,
  iconButtonMinSize: 36,
} as const;

export type LayoutScale = typeof humooLayout;
