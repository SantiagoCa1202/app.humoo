import { View } from "react-native";

import { Chip } from "@/components/primitives/chip";
import { useAppTheme } from "@/theme/ThemeProvider";

export type SuggestionChipItem = {
  id: string;
  label: string;
  value?: string;
};

export type SuggestionChipsProps = {
  accessibilityLabel?: string;
  disabled?: boolean;
  onSelect: (suggestion: SuggestionChipItem) => void;
  suggestions: SuggestionChipItem[];
};

export function SuggestionChips({
  accessibilityLabel,
  disabled = false,
  onSelect,
  suggestions,
}: SuggestionChipsProps) {
  const { theme } = useAppTheme();

  return (
    <View
      accessibilityLabel={accessibilityLabel}
      style={{
        flexDirection: "row",
        flexWrap: "wrap",
        gap: theme.spacing[2],
      }}
    >
      {suggestions.map((suggestion) => (
        <Chip
          accessibilityLabel={suggestion.label}
          disabled={disabled}
          key={suggestion.id}
          label={suggestion.label}
          onPress={() => onSelect(suggestion)}
          size="md"
          variant="neutral"
        />
      ))}
    </View>
  );
}
