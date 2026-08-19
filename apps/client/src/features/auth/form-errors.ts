import type { FieldValues, Path, UseFormSetError } from "react-hook-form";

import { isApiError } from "@/api/types";

export function applyApiFieldErrors<TFieldValues extends FieldValues>(
  error: unknown,
  fieldMap: Partial<Record<string, Path<TFieldValues>>>,
  setError: UseFormSetError<TFieldValues>
) {
  if (!isApiError(error) || !error.fieldErrors) {
    return;
  }

  for (const [apiField, messages] of Object.entries(error.fieldErrors)) {
    const targetField = fieldMap[apiField];
    const firstMessage = messages[0];

    if (!targetField || !firstMessage) {
      continue;
    }

    setError(targetField, {
      message: firstMessage,
      type: "server",
    });
  }
}

export function resolveErrorMessage(error: unknown, fallbackMessage: string) {
  return error instanceof Error ? error.message : fallbackMessage;
}
