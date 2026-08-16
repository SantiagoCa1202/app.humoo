import { NumberField, type NumberFieldProps } from "@/components/primitives/number-field";

export type GuestCountEditorProps = Omit<
  NumberFieldProps,
  "onChange" | "value"
> & {
  compact?: boolean;
  onChange: (value: number) => void;
  value?: number | null;
};

export function GuestCountEditor({
  accessibilityLabel,
  compact,
  helperText,
  label,
  max,
  min = 0,
  onChange,
  step = 1,
  value,
  ...props
}: GuestCountEditorProps) {
  return (
    <NumberField
      accessibilityLabel={accessibilityLabel}
      helperText={helperText}
      label={compact ? label : label}
      max={max}
      min={Math.max(0, min)}
      onChange={(nextValue) => onChange(Math.max(0, nextValue))}
      step={step}
      value={Math.max(0, value ?? min)}
      {...props}
    />
  );
}
