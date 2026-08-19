import { useMemo } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { AppShell } from "@/components/patterns/AppShell";
import { EventCalendar } from "@/components/patterns/event-calendar";
import type { EventDisplayRecord } from "@/features/events";
import { useAppTheme } from "@/theme/ThemeProvider";

export default function CalendarScreen() {
  const { t } = useTranslation("app");
  const { theme } = useAppTheme();
  const { session } = useAuth();
  const events = useMemo(() => [] as EventDisplayRecord[], []);

  return (
    <AppShell title={t("calendarTitle")} subtitle={t("calendarSubtitle")}>
      <View style={{ gap: theme.spacing[4] }}>
        <EventCalendar
          accessibilityLabel={t("calendarAccessibilityLabel")}
          events={events}
          timeZone={session?.currentWorkspace?.timezone ?? session?.user.timezone ?? "UTC"}
        />
      </View>
    </AppShell>
  );
}
