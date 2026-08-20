export const commandCenterKeys = {
  workspace(workspaceId: string) {
    return ["workspace", workspaceId, "command-center"] as const;
  },
};
