import {
  Text,
  type TextProps as HumooTextProps,
} from "@/components/primitives/text";

type AppTextProps = HumooTextProps & {
  muted?: boolean;
};

export function AppText({
  style,
  variant = "body",
  muted,
  ...props
}: AppTextProps) {
  return (
    <Text
      {...props}
      style={style}
      tone={muted ? "muted" : props.tone}
      variant={variant}
    />
  );
}
