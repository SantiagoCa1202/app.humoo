import { humooContentWidths } from "@/theme/breakpoints";
import { spacing } from "@/theme/spacing";

export const humooLayout = {
  content: humooContentWidths,
  sidebarWidth: 272,
  screenPadding: spacing[6],
  cardPadding: spacing[6],
  controlHeight: 52,
  iconButtonMinSize: 44,
} as const;

export type LayoutScale = typeof humooLayout;
