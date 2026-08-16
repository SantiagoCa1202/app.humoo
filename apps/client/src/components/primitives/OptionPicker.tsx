import { View } from "react-native";

import { ChoiceChip } from "@/components/primitives/ChoiceChip";
import { AppText } from "@/components/primitives/AppText";
import { useAppTheme } from "@/theme/ThemeProvider";

type OptionPickerProps<T extends string> = {
  label: string;
  hint?: string;
  error?: string;
  options: Array<{
    value: T;
    label: string;
  }>;
  selected: T | null;
  onChange: (value: T) => void;
};

export function OptionPicker<T extends string>({
  label,
  hint,
  error,
  options,
  selected,
  onChange,
}: OptionPickerProps<T>) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: 8 }}>
      <AppText variant="subtitle">{label}</AppText>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 10 }}>
        {options.map((option) => (
          <ChoiceChip
            key={option.value}
            active={option.value === selected}
            label={option.label}
            onPress={() => onChange(option.value)}
          />
        ))}
      </View>
      {error ? (
        <AppText style={{ color: theme.components.input.errorText }}>
          {error}
        </AppText>
      ) : null}
      {!error && hint ? <AppText muted>{hint}</AppText> : null}
    </View>
  );
}
