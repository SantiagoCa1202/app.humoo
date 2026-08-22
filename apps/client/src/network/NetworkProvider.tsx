import * as Network from "expo-network";
import { onlineManager } from "@tanstack/react-query";
import { AppState, View, type AppStateStatus } from "react-native";
import { createContext, useContext, useEffect, useMemo, useState } from "react";

import { AppText } from "@/components/primitives/AppText";
import { Button } from "@/components/primitives/button";
import { useAppTheme } from "@/theme/ThemeProvider";
import { setNetworkStatus, type NetworkStatus } from "@/network/network-state";
import { useTranslation } from "react-i18next";

type NetworkContextValue = {
  refresh: () => Promise<void>;
  status: NetworkStatus;
};

const NetworkContext = createContext<NetworkContextValue>({
  refresh: async () => undefined,
  status: "online",
});

export function NetworkProvider({ children }: { children: React.ReactNode }) {
  const [status, setStatus] = useState<NetworkStatus>("online");

  useEffect(() => {
    let mounted = true;
    let previousState: AppStateStatus = AppState.currentState;

    const applyState = (isConnected: boolean) => {
      const nextStatus: NetworkStatus = isConnected ? "online" : "offline";

      if (mounted) {
        setStatus(nextStatus);
      }

      setNetworkStatus(nextStatus);
      onlineManager.setOnline(isConnected);
    };

    const refresh = async (showReconnecting = false) => {
      if (showReconnecting && mounted) {
        setStatus("reconnecting");
        setNetworkStatus("reconnecting");
      }

      try {
        const state = await Network.getNetworkStateAsync();
        const isConnected =
          state.isConnected === true && state.isInternetReachable !== false;

        applyState(isConnected);
      } catch {
        applyState(false);
      }
    };

    const networkSubscription = Network.addNetworkStateListener((state) => {
      applyState(
        state.isConnected === true && state.isInternetReachable !== false
      );
    });
    const appStateSubscription = AppState.addEventListener("change", (nextState) => {
      if (nextState === "active" && previousState !== "active") {
        void refresh(true);
      }

      previousState = nextState;
    });

    void refresh();

    return () => {
      mounted = false;
      networkSubscription.remove();
      appStateSubscription.remove();
    };
  }, []);

  const value = useMemo<NetworkContextValue>(
    () => ({
      refresh: async () => {
        setStatus("reconnecting");
        setNetworkStatus("reconnecting");

        try {
          const state = await Network.getNetworkStateAsync();
          const isConnected =
            state.isConnected === true && state.isInternetReachable !== false;
          const nextStatus: NetworkStatus = isConnected ? "online" : "offline";

          setStatus(nextStatus);
          setNetworkStatus(nextStatus);
          onlineManager.setOnline(isConnected);
        } catch {
          setStatus("offline");
          setNetworkStatus("offline");
          onlineManager.setOnline(false);
        }
      },
      status,
    }),
    [status]
  );

  return (
    <NetworkContext.Provider value={value}>
      <NetworkStatusBanner />
      {children}
    </NetworkContext.Provider>
  );
}

export function useNetworkStatus(): NetworkContextValue {
  return useContext(NetworkContext);
}

function NetworkStatusBanner() {
  const { refresh, status } = useNetworkStatus();
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  if (status === "online") {
    return null;
  }

  const isReconnecting = status === "reconnecting";

  return (
    <>
      <View
        style={{
          alignItems: "center",
          backgroundColor: theme.colors.background.subtle,
          borderBottomColor: theme.colors.border.subtle,
          borderBottomWidth: 1,
          flexDirection: "row",
          gap: theme.spacing[3],
          justifyContent: "center",
          paddingHorizontal: theme.spacing[4],
          paddingVertical: theme.spacing[2],
        }}
      >
        <AppText variant="bodySmall">
          {t(
            isReconnecting
              ? "network.status.reconnecting"
              : "network.status.offline"
          )}
        </AppText>
        {!isReconnecting ? (
          <Button
            label={t("network.status.retry")}
            onPress={() => void refresh()}
            variant="ghost"
          />
        ) : null}
      </View>
    </>
  );
}
