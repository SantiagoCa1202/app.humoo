export const humooBreakpoints = {
  sm: 480,
  md: 768,
  lg: 1024,
  xl: 1280,
  "2xl": 1440,
} as const;

export const humooContentWidths = {
  maxWidth: 1200,
  chat: 760,
  form: 720,
  reading: 680,
} as const;

export type BreakpointScale = typeof humooBreakpoints;
export type ContentWidthScale = typeof humooContentWidths;
