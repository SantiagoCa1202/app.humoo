import { useCallback, useEffect, useState } from "react";

import { useRealtime } from "@/realtime";

export type ChatLiveStream = {
  activity: string | null;
  hasFailed: boolean;
  messageId: string;
  text: string;
};

export function useChatLiveStream(conversationId: string | null | undefined) {
  const { subscribeConversation } = useRealtime();
  const [stream, setStream] = useState<ChatLiveStream | null>(null);

  const reset = useCallback(() => setStream(null), []);

  useEffect(() => {
    reset();

    if (!conversationId) {
      return;
    }

    return subscribeConversation(conversationId, (event) => {
      setStream((current) => {
        const next = current?.messageId === event.messageId
          ? current
          : { activity: null, hasFailed: false, messageId: event.messageId, text: "" };

        if (event.type === "activity") {
          return { ...next, activity: event.label ?? next.activity };
        }

        if (event.type === "text.delta") {
          return { ...next, text: `${next.text}${event.delta ?? ""}` };
        }

        if (event.type === "failed") {
          return { ...next, hasFailed: true };
        }

        return next;
      });
    });
  }, [conversationId, reset, subscribeConversation]);

  return { reset, stream };
}
