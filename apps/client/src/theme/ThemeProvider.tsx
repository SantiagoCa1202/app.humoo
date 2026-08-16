import AsyncStorage from "@react-native-async-storage/async-storage";
import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import { useColorScheme } from "react-native";

import {
  resolveTheme,
  type AppTheme,
  type ThemePreference,
} from "@/theme/tokens";

const STORAGE_KEY = "humoo.theme.preference";

type ThemeContextValue = {
  theme: AppTheme;
  preference: ThemePreference;
  setPreference: (preference: ThemePreference) => Promise<void>;
};

const ThemeContext = createContext<ThemeContextValue | null>(null);

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const systemScheme = useColorScheme();
  const normalizedSystemScheme =
    systemScheme === "dark" || systemScheme === "light" ? systemScheme : null;
  const [preference, setPreferenceState] =
    useState<ThemePreference>("system");

  useEffect(() => {
    AsyncStorage.getItem(STORAGE_KEY).then((stored) => {
      if (stored === "light" || stored === "dark" || stored === "system") {
        setPreferenceState(stored);
      }
    });
  }, []);

  const theme = useMemo(() => {
    return resolveTheme(preference, normalizedSystemScheme);
  }, [normalizedSystemScheme, preference]);

  const value = useMemo<ThemeContextValue>(
    () => ({
      theme,
      preference,
      setPreference: async (nextPreference) => {
        setPreferenceState(nextPreference);
        await AsyncStorage.setItem(STORAGE_KEY, nextPreference);
      },
    }),
    [preference, theme]
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useAppTheme() {
  const context = useContext(ThemeContext);

  if (!context) {
    throw new Error("useAppTheme must be used within ThemeProvider.");
  }

  return context;
}
