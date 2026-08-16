export const humooMotion = {
  instant: 100,
  fast: 150,
  normal: 200,
  slow: 300,
  skeletonCycle: 700,
} as const;

export type MotionScale = typeof humooMotion;
