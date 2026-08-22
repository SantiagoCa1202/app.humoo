export type NetworkStatus = "online" | "offline" | "reconnecting";

let currentNetworkStatus: NetworkStatus = "online";

export function getNetworkStatus(): NetworkStatus {
  return currentNetworkStatus;
}

export function setNetworkStatus(status: NetworkStatus): void {
  currentNetworkStatus = status;
}

export function canWriteRemotely(): boolean {
  return currentNetworkStatus === "online";
}
