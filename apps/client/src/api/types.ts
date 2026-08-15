export type ApiError = {
  message: string;
  code?: string;
  fieldErrors?: Record<string, string[]>;
  status: number;
  requestId?: string;
};
