import type { ViewProps } from "react-native";

import { FormCard } from "@/components/patterns/form-card";

type FormSectionProps = ViewProps & {
  title?: string;
  description?: string;
  footer?: React.ReactNode;
  children: React.ReactNode;
};

export function FormSection({
  title,
  description,
  footer,
  children,
  ...props
}: FormSectionProps) {
  return (
    <FormCard footer={footer} subtitle={description} title={title} {...props}>
      {children}
    </FormCard>
  );
}
