export const spacing = {
  0: 0,
  1: 4,
  2: 8,
  3: 10,
  4: 12,
  5: 16,
  6: 20,
  8: 24,
  10: 32,
  12: 40,
  16: 48,
} as const;

export type SpacingScale = typeof spacing;
