import { Modal, ScrollView, View } from "react-native";
import { useTranslation } from "react-i18next";

import type { EntityPickerOption } from "@/components/primitives/entity-picker";
import { ClientForm, ContactForm, VenueForm } from "@/features/directory/forms";
import {
  clientToPickerOption,
  contactToPickerOption,
  venueToPickerOption,
} from "@/features/directory/adapters";
import {
  useCreateClient,
  useCreateContact,
  useCreateVenue,
} from "@/features/directory/hooks";
import type {
  ClientRecord,
  ContactRecord,
  VenueRecord,
} from "@/features/directory/types";
import { useAppTheme } from "@/theme/ThemeProvider";

export type DirectoryInlineCreateKind = "client" | "contact" | "venue";

export type DirectoryInlineCreateResult =
  | {
      clientId: string;
      kind: "client";
      option: EntityPickerOption<string>;
      record: ClientRecord;
    }
  | {
      clientId: string | null;
      kind: "contact";
      option: EntityPickerOption<string>;
      record: ContactRecord;
    }
  | {
      clientId: string | null;
      kind: "venue";
      option: EntityPickerOption<string>;
      record: VenueRecord;
    };

type DirectoryInlineCreateDialogProps = {
  clientOptions: EntityPickerOption<string>[];
  initialClientId?: string | null;
  kind: DirectoryInlineCreateKind | null;
  onClose: () => void;
  onCreated: (result: DirectoryInlineCreateResult) => void;
};

export function DirectoryInlineCreateDialog({
  clientOptions,
  initialClientId,
  kind,
  onClose,
  onCreated,
}: DirectoryInlineCreateDialogProps) {
  const { t } = useTranslation("app");
  const { theme } = useAppTheme();
  const createClientMutation = useCreateClient();
  const createContactMutation = useCreateContact();
  const createVenueMutation = useCreateVenue();
  const activeMutation =
    kind === "client"
      ? createClientMutation
      : kind === "contact"
        ? createContactMutation
        : createVenueMutation;
  const submitError = activeMutation.error instanceof Error ? activeMutation.error.message : null;

  return (
    <Modal
      animationType="slide"
      onRequestClose={onClose}
      transparent
      visible={Boolean(kind)}
    >
      <View
        style={{
          backgroundColor: theme.colors.overlay,
          flex: 1,
          justifyContent: "center",
          padding: theme.spacing[4],
        }}
      >
        <View
          style={{
            alignSelf: "center",
            backgroundColor: theme.colors.background.app,
            maxHeight: "92%",
            maxWidth: 760,
            width: "100%",
          }}
        >
          <ScrollView
            contentContainerStyle={{ padding: theme.spacing[3] }}
            keyboardShouldPersistTaps="handled"
          >
            {kind === "client" ? (
              <ClientForm
                errorMessage={submitError}
                loading={createClientMutation.isPending}
                onCancel={onClose}
                onSubmit={async (values) => {
                  const record = await createClientMutation.mutateAsync(values);
                  onCreated({
                    clientId: record.id,
                    kind,
                    option: clientToPickerOption(record),
                    record,
                  });
                }}
                submitLabel={t("directory.clients.actions.create")}
                subtitle={t("directory.clients.form.description")}
                title={t("directory.clients.create.cardTitle")}
              />
            ) : null}
            {kind === "contact" ? (
              <ContactForm
                clientOptions={clientOptions}
                errorMessage={submitError}
                initialValues={{ clientId: initialClientId ?? "" }}
                loading={createContactMutation.isPending}
                onCancel={onClose}
                onSubmit={async (values) => {
                  const record = await createContactMutation.mutateAsync(values);
                  onCreated({
                    clientId: record.clientId,
                    kind,
                    option: contactToPickerOption(record),
                    record,
                  });
                }}
                submitLabel={t("directory.contacts.actions.create")}
                subtitle={t("directory.contacts.form.description")}
                title={t("directory.contacts.create.cardTitle")}
              />
            ) : null}
            {kind === "venue" ? (
              <VenueForm
                errorMessage={submitError}
                loading={createVenueMutation.isPending}
                onCancel={onClose}
                onSubmit={async (values) => {
                  const record = await createVenueMutation.mutateAsync(values);
                  onCreated({
                    clientId: null,
                    kind,
                    option: venueToPickerOption(record),
                    record,
                  });
                }}
                submitLabel={t("directory.venues.actions.create")}
                subtitle={t("directory.venues.form.description")}
                title={t("directory.venues.create.cardTitle")}
              />
            ) : null}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}
