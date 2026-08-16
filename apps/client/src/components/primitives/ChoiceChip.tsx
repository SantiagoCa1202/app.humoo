import type { PressableProps } from "react-native";

import { Chip } from "@/components/primitives/chip";

type ChoiceChipProps = Omit<PressableProps, "children" | "style"> & {
  active?: boolean;
  label: string;
};

export function ChoiceChip({
  active = false,
  disabled,
  label,
  ...props
}: ChoiceChipProps) {
  return (
    <Chip
      disabled={Boolean(disabled)}
      label={label}
      selected={active}
      size="md"
      variant="neutral"
      {...props}
    />
  );
}
