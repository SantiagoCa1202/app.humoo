import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard, type BaseCardProps } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ComparisonChange = {
  after: React.ReactNode;
  before: React.ReactNode;
  id?: string;
  label: React.ReactNode;
};

export type ComparisonCardProps = Omit<BaseCardProps, "children"> & {
  changes: ComparisonChange[];
  loading?: boolean;
  onAccept?: () => void;
  onReject?: () => void;
  subtitle?: React.ReactNode;
  title: React.ReactNode;
};

export function ComparisonCard({
  changes,
  loading = false,
  onAccept,
  onReject,
  subtitle,
  title,
  variant = "default",
  ...props
}: ComparisonCardProps) {
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");

  return (
    <BaseCard padding="lg" variant={variant} {...props}>
      <CardHeader subtitle={subtitle} title={title} />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          {changes.map((change, index) => (
            <View key={change.id ?? `comparison-${index}`} style={{ gap: theme.spacing[3] }}>
              <Text selectable tone="muted" variant="caption">
                {change.label}
              </Text>
              <View
                style={{
                  flexDirection: "row",
                  flexWrap: "wrap",
                  gap: theme.spacing[3],
                }}
              >
                <View
                  style={{
                    backgroundColor: theme.colors.status.dangerSoft,
                    borderColor: theme.colors.status.danger,
                    borderCurve: "continuous",
                    borderRadius: theme.radius.md,
                    borderWidth: 1,
                    flex: 1,
                    gap: theme.spacing[2],
                    minWidth: 180,
                    padding: theme.spacing[3],
                  }}
                >
                  <Text tone="danger" variant="overline">
                    {t("cards.comparison.before")}
                  </Text>
                  {typeof change.before === "string" || typeof change.before === "number" ? (
                    <Text selectable variant="bodySmall">
                      {change.before}
                    </Text>
                  ) : (
                    change.before
                  )}
                </View>
                <View
                  style={{
                    backgroundColor: theme.colors.status.successSoft,
                    borderColor: theme.colors.status.success,
                    borderCurve: "continuous",
                    borderRadius: theme.radius.md,
                    borderWidth: 1,
                    flex: 1,
                    gap: theme.spacing[2],
                    minWidth: 180,
                    padding: theme.spacing[3],
                  }}
                >
                  <Text tone="success" variant="overline">
                    {t("cards.comparison.after")}
                  </Text>
                  {typeof change.after === "string" || typeof change.after === "number" ? (
                    <Text selectable variant="bodySmall">
                      {change.after}
                    </Text>
                  ) : (
                    change.after
                  )}
                </View>
              </View>
              {index < changes.length - 1 ? <Divider spacing="none" /> : null}
            </View>
          ))}
        </View>
      </CardContent>
      {onAccept || onReject ? (
        <CardFooter align="right" divider>
          {onReject ? (
            <Button
              label={t("cards.comparison.keepCurrent")}
              loading={loading}
              onPress={onReject}
              variant="ghost"
            />
          ) : null}
          {onAccept ? (
            <Button
              label={t("cards.comparison.acceptChanges")}
              loading={loading}
              onPress={onAccept}
              variant="primary"
            />
          ) : null}
        </CardFooter>
      ) : null}
    </BaseCard>
  );
}
