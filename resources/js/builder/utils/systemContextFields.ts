import type { AudienceListColumn, AudienceListSummary, SurveyElement, SurveySettings } from '../types/schema';

export const SYSTEM_CONTEXT_FIELD_KEYS = [
  'system_context_dealer',
  'system_context_location',
  'system_context_vehicle_plate',
  'system_context_delivery_date',
] as const;

const systemContextFieldKeys = new Set<string>(SYSTEM_CONTEXT_FIELD_KEYS);

export type ResultContextKey = 'dealer' | 'location' | 'vehicle_plate' | 'delivery_date';

export type AudienceColumnOption = {
  value: string;
  label: string;
  type: string | null;
};

export type ResultContextColumns = NonNullable<NonNullable<SurveySettings['personalization']>['result_context_columns']>;

const resultContextCandidates: Record<ResultContextKey, string[]> = {
  dealer: ['dlr'],
  location: ['dept'],
  vehicle_plate: ['regono'],
  delivery_date: ['delivery_date', 'timedelivered'],
};

function normalizedProfile(profile: string | null | undefined): string | null {
  const normalized = profile?.trim().toUpperCase() ?? '';

  return normalized === '' ? null : normalized;
}

export function normalizeAudienceColumnOption(column: string | AudienceListColumn): AudienceColumnOption | null {
  if (typeof column === 'string') {
    const value = column.trim();

    return value === '' ? null : { value, label: value, type: null };
  }

  const rawValue = column.key ?? column.value ?? column.name ?? column.label;
  const value = rawValue === undefined || rawValue === null ? '' : String(rawValue).trim();

  if (value === '') {
    return null;
  }

  const rawLabel = column.label === undefined || column.label === null ? '' : String(column.label).trim();
  const label = rawLabel === '' ? value : rawLabel;

  return {
    value,
    label: label === value ? label : `${label} (${value})`,
    type: column.type?.trim().toLowerCase() ?? null,
  };
}

export function audienceColumnOptions(list: AudienceListSummary | null): AudienceColumnOption[] {
  return (list?.columns ?? [])
    .map((column) => normalizeAudienceColumnOption(column))
    .filter((column): column is AudienceColumnOption => column !== null);
}

export function inferResultContextColumns(list: AudienceListSummary | null): ResultContextColumns {
  const columns = audienceColumnOptions(list);
  const byKey = new Map(columns.map((column) => [column.value.toLowerCase(), column]));
  const findColumn = (candidates: string[], requireDate = false): string | null => {
    for (const candidate of candidates) {
      const column = byKey.get(candidate);

      if (column && (!requireDate || column.type === 'date')) {
        return column.value;
      }
    }

    return null;
  };

  return {
    dealer: findColumn(resultContextCandidates.dealer),
    location: findColumn(resultContextCandidates.location),
    vehicle_plate: findColumn(resultContextCandidates.vehicle_plate),
    delivery_date: findColumn(resultContextCandidates.delivery_date, true),
  };
}

function validResultContextColumn(
  key: ResultContextKey,
  value: string | null | undefined,
  columns: AudienceColumnOption[],
): boolean {
  if (!value) {
    return false;
  }

  const column = columns.find((candidate) => candidate.value === value);

  return Boolean(column && (key !== 'delivery_date' || column.type === 'date'));
}

export function hydratePersonalizationSettings(
  settings: SurveySettings,
  lists: AudienceListSummary[],
): boolean {
  const personalization = settings.personalization ?? {};
  let selectedList = personalization.audience_list_id === undefined
    || personalization.audience_list_id === null
    || personalization.audience_list_id === ''
    ? null
    : lists.find((list) => String(list.id) === String(personalization.audience_list_id)) ?? null;
  let changed = false;

  if (!selectedList && !personalization.audience_list_id) {
    const category = normalizedProfile(settings.category);
    const matchingLists = category
      ? lists.filter((list) => normalizedProfile(list.schema_profile) === category)
      : [];

    if (matchingLists.length === 1) {
      selectedList = matchingLists[0];
      personalization.audience_list_id = selectedList.id;
      personalization.required = true;
      changed = true;
    }
  }

  if (!selectedList) {
    return changed;
  }

  const columns = audienceColumnOptions(selectedList);
  const inferred = inferResultContextColumns(selectedList);
  const resultContextColumns = { ...(personalization.result_context_columns ?? {}) };

  (Object.keys(resultContextCandidates) as ResultContextKey[]).forEach((key) => {
    if (!validResultContextColumn(key, resultContextColumns[key], columns)) {
      if (!Object.hasOwn(resultContextColumns, key) || (resultContextColumns[key] ?? null) !== inferred[key]) {
        resultContextColumns[key] = inferred[key];
        changed = true;
      }
    }
  });

  if (changed) {
    personalization.result_context_columns = resultContextColumns;
    settings.personalization = personalization;
  }

  return changed;
}

export function isSystemContextField(element: SurveyElement): boolean {
  return typeof element.field_key === 'string' && systemContextFieldKeys.has(element.field_key);
}

export function visibleSurveyElements(elements: SurveyElement[]): SurveyElement[] {
  return elements.filter((element) => !isSystemContextField(element));
}
