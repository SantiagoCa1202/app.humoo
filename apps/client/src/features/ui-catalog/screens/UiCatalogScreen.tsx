import { Feather } from "@expo/vector-icons";
import { useMemo, useState } from "react";
import { ScrollView, useWindowDimensions, View } from "react-native";
import { useTranslation } from "react-i18next";

import { ActionPreviewCard } from "@/components/patterns/action-preview-card";
import { AlertCard } from "@/components/patterns/alert-card";
import { ClarificationCard } from "@/components/patterns/clarification-card";
import { ConfirmationCard } from "@/components/patterns/confirmation-card";
import { DetailCard } from "@/components/patterns/detail-card";
import { FormCard } from "@/components/patterns/form-card";
import { ListCard } from "@/components/patterns/list-card";
import { ProgressCard } from "@/components/patterns/progress-card";
import { SectionCard } from "@/components/patterns/SectionCard";
import { StateBlock } from "@/components/patterns/StateBlock";
import { StreamingStatus } from "@/components/patterns/streaming-status";
import { SuggestionChips } from "@/components/patterns/suggestion-chips";
import { SummaryCard } from "@/components/patterns/summary-card";
import { ThemeToggle } from "@/components/patterns/ThemeToggle";
import { LanguageSelector } from "@/components/patterns/LanguageSelector";
import { BaseCard } from "@/components/primitives/base-card";
import { Badge } from "@/components/primitives/badge";
import { Avatar } from "@/components/primitives/avatar";
import { AvatarGroup } from "@/components/primitives/avatar-group";
import { Button } from "@/components/primitives/button";
import { Checkbox } from "@/components/primitives/checkbox";
import { Chip } from "@/components/primitives/chip";
import { ChoiceChip } from "@/components/primitives/ChoiceChip";
import { CurrencyInput } from "@/components/primitives/currency-input";
import { DatePicker } from "@/components/primitives/date-picker";
import { DateTimeField } from "@/components/primitives/date-time-field";
import { Divider } from "@/components/primitives/divider";
import { EntityPicker } from "@/components/primitives/entity-picker";
import { FieldLabel } from "@/components/primitives/field-label";
import { FieldMessage } from "@/components/primitives/field-message";
import { FilePicker, type FilePickerFile } from "@/components/primitives/file-picker";
import { Heading } from "@/components/primitives/heading";
import { IconButton } from "@/components/primitives/icon-button";
import { MultiSelect } from "@/components/primitives/multi-select";
import { NumberField } from "@/components/primitives/number-field";
import { OptionPicker } from "@/components/primitives/OptionPicker";
import { QuantityInput } from "@/components/primitives/quantity-input";
import { RadioGroup } from "@/components/primitives/radio-group";
import { SearchInput } from "@/components/primitives/search-input";
import { Select } from "@/components/primitives/select";
import { Skeleton } from "@/components/primitives/skeleton";
import { Spinner } from "@/components/primitives/spinner";
import { StatusBadge } from "@/components/primitives/status-badge";
import { StatusSelect } from "@/components/primitives/status-select";
import { Switch } from "@/components/primitives/switch";
import { Text } from "@/components/primitives/text";
import { TextArea } from "@/components/primitives/text-area";
import { TextField } from "@/components/primitives/text-field";
import { TimePicker } from "@/components/primitives/time-picker";
import { UserPicker } from "@/components/primitives/user-picker";
import { useAppTheme } from "@/theme/ThemeProvider";
import type { AppOperationalStatus } from "@/theme/status-config";

const primitiveInventory = [
  "AppButton", "AppText", "Avatar", "AvatarGroup", "Badge", "BaseCard", "Button",
  "CardContent", "CardFooter", "CardHeader", "Checkbox", "Chip", "ChoiceChip",
  "CurrencyInput", "DatePicker", "DateTimeField", "Divider", "EntityPicker", "FieldLabel",
  "FieldMessage", "FilePicker", "Heading", "IconButton", "IconSlot", "MultiSelect",
  "NumberField", "OptionPicker", "QuantityInput", "RadioGroup", "SearchInput", "Select",
  "SelectBase", "Skeleton", "SkeletonAvatar", "SkeletonText", "Spinner", "StatusBadge",
  "StatusSelect", "Switch", "Text", "TextArea", "TextField", "TimePicker", "TimezonePicker",
  "Tooltip", "UserPicker",
];

const patternInventory = [
  "ActionPreviewCard", "ActionResultCard", "AlertCard", "AssignmentBoard", "AvailabilityEditor",
  "AvailabilitySummary", "BEOChangeAlert", "BEOChangeReview", "BEOConflictAlert", "BEOViewer",
  "ChatMessage", "ClarificationCard", "ClientCard", "ComparisonCard", "ConfirmationCard",
  "ConflictState", "ContactCard", "DetailCard", "DocumentCard", "DocumentList", "EditableCard",
  "EmptyState", "EntityCard", "ErrorRecoveryCard", "ErrorState", "EventCalendar", "EventCard",
  "EventCreateForm", "EventDetailHeader", "EventEditForm", "EventFiltersForm", "EventForm",
  "EventList", "EventTimeline", "FormCard", "FormSection", "InventoryByLocation", "InventoryList",
  "InventoryItemCard", "ListCard", "ListItemCard", "LoadingState", "MenuCard", "MenuEditorForm",
  "MenuList", "OfflineState", "PrepAssignment", "PrepGenerationOptions", "PrepGenerationPreview",
  "PrepItemEditor", "PrepItemList", "PrepList", "PrepListCard", "PrepProgress", "ProgressCard",
  "PurchaseOrderCard", "PurchaseOrderEditor", "PurchaseOrderList", "RecipeCard", "RecipeEditorForm",
  "RecipeIngredientsList", "RecipeScaler", "RecipeStepsList", "SectionCard", "ShiftCalendar",
  "ShiftCard", "ShiftEditor", "StateBlock", "StationCard", "StockMovementForm", "StockMovementList",
  "SuccessState", "SuggestionChips", "SummaryCard", "SupplierCard", "SupplierEditorForm",
  "TaskCard", "TaskEditorForm", "TaskFiltersForm", "TaskList", "TeamEditorForm", "TeamRoster",
  "ThemeToggle", "UserMessage", "VenueCard", "WorkloadSummary",
];

const options = [
  { label: "Primera opción", value: "first" },
  { label: "Segunda opción", value: "second" },
  { disabled: true, label: "Opción deshabilitada", value: "disabled" },
];

const taskStatusOptions = [
  { value: "todo" as const },
  { value: "in_progress" as const },
  { value: "blocked" as const },
  { value: "done" as const },
];

type CatalogSectionProps = { children: React.ReactNode; description?: string; title: string };

function CatalogSection({ children, description, title }: CatalogSectionProps) {
  const { theme } = useAppTheme();

  return (
    <View style={{ gap: theme.spacing[4] }}>
      <View style={{ gap: theme.spacing[1] }}>
        <Text tone="primary" variant="overline">{title}</Text>
        {description ? <Text tone="muted" variant="bodySmall">{description}</Text> : null}
      </View>
      {children}
    </View>
  );
}

function DemoLabel({ children }: { children: React.ReactNode }) {
  return <Text tone="muted" variant="caption">{children}</Text>;
}

export function UiCatalogScreen() {
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");
  const { width } = useWindowDimensions();
  const [toast, setToast] = useState("Listo para explorar");
  const [text, setText] = useState("Cocina de temporada");
  const [search, setSearch] = useState("menú");
  const [number, setNumber] = useState(12);
  const [currency, setCurrency] = useState("USD");
  const [quantity, setQuantity] = useState(2.5);
  const [unit, setUnit] = useState("kg");
  const [selectedOption, setSelectedOption] = useState<"first" | "second" | "disabled">("first");
  const [selectedValues, setSelectedValues] = useState<string[]>(["first", "second"]);
  const [selectedStatus, setSelectedStatus] = useState<AppOperationalStatus>("in_progress");
  const [selectedUser, setSelectedUser] = useState<string | undefined>("ana");
  const [checked, setChecked] = useState(true);
  const [indeterminate, setIndeterminate] = useState(true);
  const [switchValue, setSwitchValue] = useState(true);
  const [radio, setRadio] = useState("team");
  const [date, setDate] = useState<string | null>("2026-09-15");
  const [time, setTime] = useState<string | null>("18:30");
  const [dateTime, setDateTime] = useState<string | null>("2026-09-15T18:30:00-04:00");
  const [files, setFiles] = useState<FilePickerFile[]>([
    { mimeType: "application/pdf", name: "menu-septiembre.pdf", size: 248000, type: "application/pdf", uri: "demo://menu-septiembre.pdf" },
  ]);
  const [loadingForm, setLoadingForm] = useState(false);
  const [stateTone, setStateTone] = useState<"loading" | "empty" | "error" | "forbidden" | "offline" | "success" | "conflict" | "info">("loading");
  const columns = width >= 900 ? 2 : 1;
  const cardWidth = columns === 2 ? "48.5%" : "100%";
  const selectedOptionLabel = useMemo(() => options.find((option) => option.value === selectedOption)?.label, [selectedOption]);

  const notify = (message: string) => setToast(message);

  return (
    <View style={{ backgroundColor: theme.colors.background.app, flex: 1 }}>
      <ScrollView contentInsetAdjustmentBehavior="automatic" contentContainerStyle={{ gap: theme.spacing[8], padding: theme.spacing[5], paddingBottom: theme.spacing[12] }}>
        <View style={{ alignSelf: "center", gap: theme.spacing[4], maxWidth: 1180, width: "100%" }}>
          <View style={{ gap: theme.spacing[3] }}>
            <Text tone="primary" variant="overline">HUMOO / UI CATALOG</Text>
            <Heading level="display" title="Catálogo visual de componentes" subtitle="Ruta privada de exploración. Todos los ejemplos usan datos locales y no crean ni modifican registros." selectable />
            <View style={{ alignItems: "center", backgroundColor: theme.colors.brand.soft, borderColor: theme.colors.brand.primary, borderRadius: theme.radius.md, borderWidth: 1, flexDirection: "row", gap: theme.spacing[2], padding: theme.spacing[3] }}>
              <Feather color={theme.colors.brand.primary} name="link" size={theme.iconSizes.sm} />
              <Text tone="primary" variant="bodySmall" selectable>Acceso directo: /ui-catalog · {toast}</Text>
            </View>
          </View>

          <CatalogSection description="El inventario se mantiene alineado con src/components/primitives y src/components/patterns." title="Índice del sistema">
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
              <Badge label={`${primitiveInventory.length} primitivas`} variant="primary" />
              <Badge label={`${patternInventory.length} patrones catalogados`} variant="special" />
              <Badge label="Datos locales" variant="success" />
              <Badge label="Sin mutaciones" variant="neutral" />
            </View>
            <DetailCard rows={[{ label: "Primitivas", value: primitiveInventory.join(" · ") }, { label: "Patrones", value: patternInventory.join(" · ") }]} subtitle="Componentes encontrados en el cliente" title="Mapa de componentes" variant="muted" />
          </CatalogSection>

          <CatalogSection description="Jerarquía tipográfica, iconos, badges, avatares y feedback de carga." title="01 · Fundamentos">
            <View style={{ gap: theme.spacing[4] }}>
              <SectionCard description="Escala y tonos del sistema" title="Tipografía">
                <View style={{ gap: theme.spacing[3] }}>
                  <Heading eyebrow="Eyebrow" level="h1" title="Heading H1" subtitle="Subtítulo de apoyo" />
                  <Text variant="h2">Heading H2</Text><Text variant="h3">Heading H3</Text><Text variant="h4">Heading H4</Text>
                  <Text variant="body">Body: texto principal para lectura y contexto.</Text><Text tone="secondary" variant="bodySmall">Body small secundario</Text><Text tone="muted" variant="caption">Caption muted · información auxiliar</Text>
                  <Divider /><Text tone="danger" selectable variant="bodySmall">Mensaje de error importante y seleccionable.</Text>
                </View>
              </SectionCard>
              <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[3] }}>
                {(["neutral", "primary", "info", "success", "warning", "danger", "special"] as const).map((variant) => <Badge key={variant} dot label={variant} outline size="lg" variant={variant} />)}
              </View>
              <View style={{ alignItems: "center", flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}>
                {(["xs", "sm", "md", "lg", "xl"] as const).map((size) => <Avatar key={size} name="Ana López" shape={size === "lg" ? "rounded" : "circle"} showBorder size={size} status="online" />)}
                <AvatarGroup users={[{ name: "Ana López", status: "online" }, { name: "Luis Pérez", status: "away" }, { name: "Marta Ruiz", status: "busy" }, { name: "Sam Lee" }]} />
              </View>
              <View style={{ alignItems: "center", flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}>
                <Spinner label="Cargando datos" size="lg" />
                <Skeleton height={16} variant="text" width={180} /><Skeleton height={48} radius="full" variant="circle" /><Skeleton animated={false} height={48} width={220} />
              </View>
            </View>
          </CatalogSection>

          <CatalogSection description="Variantes, tamaños, acciones, doble submit y estados disabled/loading." title="02 · Botones y acciones">
            <SectionCard title="Button / IconButton">
              <View style={{ gap: theme.spacing[3] }}>
                <DemoLabel>Variantes</DemoLabel>
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
                  <Button label="Primary" onPress={() => notify("Primary presionado")} variant="primary" /><Button label="Secondary" onPress={() => notify("Secondary presionado")} variant="secondary" /><Button label="Ghost" onPress={() => notify("Ghost presionado")} variant="ghost" /><Button label="Destructive" onPress={() => notify("Destructive requiere confirmación")} variant="destructive" />
                </View>
                <DemoLabel>Tamaños y estados</DemoLabel>
                <View style={{ alignItems: "center", flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
                  <Button label="Small" size="sm" variant="primary" /><Button label="Large" size="lg" variant="primary" /><Button label="Loading" loading onPress={() => undefined} /><Button disabled label="Disabled" /><Button fullWidth label="Full width" variant="secondary" />
                </View>
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>
                  <IconButton accessibilityLabel="Editar" icon={<Feather name="edit-2" />} onPress={() => notify("Editar presionado")} shape="rounded" /><IconButton accessibilityLabel="Favorito" icon={<Feather name="heart" />} shape="circle" variant="ghost" /><IconButton accessibilityLabel="Guardando" icon={<Feather name="save" />} loading shape="circle" variant="primary" /><IconButton accessibilityLabel="Deshabilitado" disabled icon={<Feather name="lock" />} shape="circle" />
                </View>
              </View>
            </SectionCard>
          </CatalogSection>

          <CatalogSection description="Todos los campos tienen valores de ejemplo, helper, error, required, optional y disabled donde aplica." title="03 · Inputs y formularios">
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}>
              <View style={{ gap: theme.spacing[4], minWidth: 280, width: cardWidth }}>
                <SectionCard title="Texto y búsqueda">
                  <View style={{ gap: theme.spacing[3] }}>
                    <TextField label="Nombre del menú" onChangeText={setText} required value={text} />
                    <TextField helperText="Nunca compartiremos este dato." label="Email" keyboardType="email-address" value="chef@humoo.test" />
                    <TextField error="El nombre es demasiado corto." label="Con error" value="x" />
                    <TextField editable={false} label="Disabled" value="Campo bloqueado" />
                    <TextField label="Contraseña" secure value="••••••••" />
                    <SearchInput onChangeText={setSearch} placeholder="Buscar componentes" value={search} />
                    <TextArea autoGrow helperText="Máximo 240 caracteres" label="Notas" maxLength={240} onChangeText={setText} value="Preparar mise en place antes de las 16:00." />
                  </View>
                </SectionCard>
                <SectionCard title="Feedback de campo">
                  <View style={{ gap: theme.spacing[3] }}><FieldLabel label="Etiqueta requerida" required /><FieldLabel label="Etiqueta opcional" optional /><FieldMessage helperText="Texto de ayuda debajo del campo." /><FieldMessage count={240} error="Revisa este valor." maxLength={240} /></View>
                </SectionCard>
              </View>
              <View style={{ gap: theme.spacing[4], minWidth: 280, width: cardWidth }}>
                <SectionCard title="Numéricos y cantidades">
                  <View style={{ gap: theme.spacing[3] }}>
                    <NumberField label="Invitados" max={500} min={0} onChange={setNumber} suffix="personas" value={number} />
                    <CurrencyInput currencies={[{ label: "USD · Dólar", value: "USD" }, { label: "EUR · Euro", value: "EUR" }]} currency={currency} label="Costo estimado" onChange={() => undefined} onCurrencyChange={setCurrency} value={1250} />
                    <QuantityInput label="Cantidad de producto" max={100} min={0} onChange={setQuantity} onUnitChange={setUnit} step={0.5} unit={unit} units={[{ label: "kg", value: "kg" }, { label: "litros", value: "l" }, { label: "unidades", value: "unit" }]} value={quantity} />
                  </View>
                </SectionCard>
                <SectionCard title="Selección de entidades">
                  <View style={{ gap: theme.spacing[3] }}>
                    <Select label="Tipo de menú" onChange={(value) => setSelectedOption(value as typeof selectedOption)} options={options} placeholder="Selecciona una opción" required value={selectedOption} />
                    <MultiSelect label="Etiquetas" onChange={setSelectedValues} options={options} placeholder="Selecciona varias" values={selectedValues} />
                    <EntityPicker entities={[{ label: "Menú de temporada", metadata: "Septiembre 2026", type: "Menú", value: "menu" }, { label: "Menú privado", metadata: "Borrador", type: "Menú", value: "private" }]} label="Entidad" onChange={() => undefined} value="menu" />
                    <UserPicker label="Responsable" onChange={setSelectedUser} users={[{ label: "Ana López", metadata: "Chef ejecutiva", roleLabel: "Chef ejecutiva", status: "online", value: "ana" }, { label: "Luis Pérez", metadata: "Sous chef", roleLabel: "Sous chef", status: "away", value: "luis" }]} value={selectedUser} />
                    <OptionPicker error={selectedOption === "disabled" ? "Selecciona una opción válida." : undefined} hint={`Seleccionado: ${selectedOptionLabel ?? "ninguno"}`} label="Presentación" onChange={(value) => setSelectedOption(value as typeof selectedOption)} options={options} selected={selectedOption} />
                  </View>
                </SectionCard>
              </View>
            </View>
            <SectionCard title="Fecha, hora y archivos">
              <View style={{ gap: theme.spacing[3] }}>
                <DatePicker label="Fecha del evento" onChange={setDate} required value={date} />
                <TimePicker label="Hora de inicio" onChange={setTime} value={time} />
                <DateTimeField label="Inicio completo" onChange={setDateTime} timeZone="America/New_York" value={dateTime} />
                <FilePicker acceptedTypes={["application/pdf"]} files={files} helperText="PDF hasta 10 MB" label="Documento" maxSize={10 * 1024 * 1024} multiple onChange={setFiles} />
              </View>
            </SectionCard>
          </CatalogSection>

          <CatalogSection description="Chips, checks, radio, switch y select de estados con valores funcionales." title="04 · Controles">
            <SectionCard title="Selección y toggles">
              <View style={{ gap: theme.spacing[4] }}>
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}><Chip label="Neutral" /><Chip label="Seleccionado" selected variant="primary" /><Chip label="Removible" onRemove={() => notify("Chip removido")} removable variant="info" /><Chip disabled label="Disabled" /></View>
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}><ChoiceChip active label="Activo" onPress={() => undefined} /><ChoiceChip label="Inactivo" onPress={() => undefined} /><ChoiceChip disabled label="Disabled" /></View>
                <Checkbox checked={checked} description="Puedes cambiar este valor." label="Acepto la política de operación" onChange={setChecked} /><Checkbox checked={false} indeterminate={indeterminate} label="Estado indeterminado" onChange={() => setIndeterminate(false)} /><Checkbox checked={false} disabled label="Checkbox disabled" onChange={() => undefined} />
                <RadioGroup direction="horizontal" label="Visibilidad" onChange={setRadio} options={[{ description: "Solo el equipo", label: "Equipo", value: "team" }, { description: "Para toda la organización", label: "Organización", value: "org" }]} value={radio} />
                <Switch description="Los avisos se enviarán al equipo." label="Notificaciones activas" onChange={setSwitchValue} value={switchValue} /><Switch disabled label="Switch disabled" onChange={() => undefined} value={false} />
                <StatusSelect label="Estado de tarea" namespace="tasks" onChange={setSelectedStatus} options={taskStatusOptions} value={selectedStatus} />
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>{taskStatusOptions.map((option) => <StatusBadge key={option.value} namespace="tasks" status={option.value} />)}</View>
              </View>
            </SectionCard>
          </CatalogSection>

          <CatalogSection description="Estados explícitos para loading, empty, error, forbidden, offline, success, conflict e info." title="05 · Estados de pantalla">
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}>
              {(["loading", "empty", "error", "forbidden", "offline", "success", "conflict", "info"] as const).map((tone) => <View key={tone} style={{ minWidth: 280, width: cardWidth }}><BaseCard padding="md" variant="muted"><StateBlock actionLabel={tone === "loading" ? undefined : "Reintentar"} description={`Estado ${tone} con contenido de ejemplo.`} onAction={() => notify(`Acción de ${tone}`)} title={`Estado ${tone}`} tone={tone} /></BaseCard></View>)}
            </View>
            <SectionCard title="Selector de estado en vivo">
              <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[2] }}>{(["loading", "empty", "error", "forbidden", "offline", "success", "conflict", "info"] as const).map((tone) => <ChoiceChip key={tone} active={stateTone === tone} label={tone} onPress={() => setStateTone(tone)} />)}</View>
              <View style={{ marginTop: theme.spacing[4] }}><StateBlock actionLabel="Ejecutar acción" description="Este bloque cambia sin salir de la pantalla." onAction={() => notify("Estado interactivo ejecutado")} title="Preview interactivo" tone={stateTone} /></View>
            </SectionCard>
          </CatalogSection>

          <CatalogSection description="Patrones compartidos para información, formularios, listas y flujos conversacionales." title="06 · Patrones compuestos">
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}>
              <View style={{ gap: theme.spacing[4], minWidth: 280, width: cardWidth }}>
                <ListCard actionLabel="Ver todo" items={[{ id: "1", status: "done", statusNamespace: "tasks", subtitle: "Hoy · 16:00", title: "Revisar mise en place", trailing: <Text variant="bodySmall">Ana</Text> }, { id: "2", status: "blocked", statusNamespace: "tasks", subtitle: "Mañana · 09:00", title: "Confirmar proveedores", trailing: <Badge label="Urgente" variant="danger" /> }]} onActionPress={() => notify("Ver todo presionado")} onItemPress={(item) => notify(`Item ${item.id} presionado`)} subtitle="Datos de ejemplo completos" title="Lista de tareas" />
                <ProgressCard completed={7} metrics={[{ label: "Pendientes", tone: "warning", value: "3" }, { label: "Equipo", value: "5" }]} status="in_progress" subtitle="Actualización local" title="Producción del evento" total={10} />
                <SummaryCard metrics={[{ label: "Eventos", tone: "primary", value: "24" }, { label: "Costo", tone: "success", value: "$12.4k" }, { label: "Alertas", tone: "danger", value: "3" }]} subtitle="Últimos 30 días" title="Resumen operativo" trailing={<Badge label="Demo" variant="info" />} />
                <AlertCard actionLabel="Revisar" description="Este mensaje conserva el diagnóstico fuera de la UI." dismissible message="Información adicional" onAction={() => notify("Alerta revisada")} onDismiss={() => notify("Alerta cerrada")} title="Hay cambios pendientes" tone="warning" />
              </View>
              <View style={{ gap: theme.spacing[4], minWidth: 280, width: cardWidth }}>
                <FormCard error="Ejemplo de error general del formulario." onCancel={() => notify("Cancelado")} onSubmit={() => { setLoadingForm(true); setTimeout(() => setLoadingForm(false), 800); }} submitting={loadingForm} submitLabel="Guardar ejemplo" subtitle="Los botones cubren cancelación, guardado y loading." title="Formulario compuesto"><TextField label="Nombre" value="Formulario con datos" onChangeText={() => undefined} /></FormCard>
                <ActionPreviewCard action="Actualizar el estado de la tarea" changes={[{ after: "Completada", before: "En progreso", label: "Estado" }, { after: "Ana López", before: "Sin asignar", label: "Responsable" }]} destructive description="Revisa estos cambios antes de confirmar." impact="Afecta a 1 tarea" metadata={[{ label: "Origen", value: "Catálogo local" }]} title="Vista previa de acción" type="Tarea" />
                <ConfirmationCard confirmLabel="Confirmar ejemplo" destructive details={[{ label: "Registro", value: "Evento de septiembre" }, { label: "Acción", value: "Cancelar" }]} loading={loadingForm} onCancel={() => notify("Confirmación cancelada")} onConfirm={() => notify("Confirmación simulada")} title="¿Confirmar acción?" />
                <ClarificationCard description="Selecciona una respuesta para continuar." onSelect={(option) => notify(`Seleccionado: ${option.label}`)} onSubmit={(option) => notify(`Enviado: ${option?.label ?? "ninguno"}`)} options={[{ description: "Usa el equipo asignado al evento.", id: "assigned", label: "Equipo asignado", value: "assigned" }, { description: "Elige una persona específica.", id: "person", label: "Persona específica", value: "person" }]} selected="assigned" submitLabel="Continuar" title="¿A quién asigno?" />
                <StreamingStatus description="Procesando una acción de ejemplo." steps={[{ id: "resolve", label: "Resolver entidad", status: "done" }, { id: "prepare", label: "Preparar cambios", status: "active" }, { id: "persist", label: "Guardar", status: "pending" }]} title="Procesando" />
                <SuggestionChips onSelect={(suggestion) => notify(`Sugerencia: ${suggestion.label}`)} suggestions={[{ id: "1", label: "Ver tareas de hoy" }, { id: "2", label: "Crear evento" }, { id: "3", label: "Revisar inventario" }]} />
              </View>
            </View>
          </CatalogSection>

          <CatalogSection description="Controles que ya existen y se pueden inspeccionar sin afectar la navegación de producción." title="07 · Preferencias">
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: theme.spacing[4] }}><BaseCard padding="lg" style={{ flex: 1, minWidth: 280 }}><ThemeToggle /></BaseCard><BaseCard padding="lg" style={{ flex: 1, minWidth: 280 }}><LanguageSelector /></BaseCard></View>
          </CatalogSection>
        </View>
      </ScrollView>
    </View>
  );
}
