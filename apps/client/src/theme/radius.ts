export const humooRadius = {
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  "2xl": 24,
  full: 999,
} as const;

export type RadiusScale = typeof humooRadius;
