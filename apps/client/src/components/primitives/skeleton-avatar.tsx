import { Skeleton } from "@/components/primitives/skeleton";
import { useAppTheme } from "@/theme/ThemeProvider";

export type SkeletonAvatarProps = {
  size?: number;
  circular?: boolean;
};

export function SkeletonAvatar({
  size,
  circular = true,
}: SkeletonAvatarProps) {
  const { theme } = useAppTheme();
  const resolvedSize = size ?? theme.spacing[10];

  return (
    <Skeleton
      height={resolvedSize}
      radius={circular ? "full" : "md"}
      variant={circular ? "circle" : "rect"}
      width={resolvedSize}
    />
  );
}
