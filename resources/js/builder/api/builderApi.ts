import type { BuilderEndpoints, BuilderPayload, SurveyBuilderSchema } from '../types/schema';

interface RequestOptions {
  csrfToken: string;
}

export class ValidationError extends Error {
  constructor(
    message: string,
    public readonly errors: Record<string, string[]>,
  ) {
    super(message);
    this.name = 'ValidationError';
  }
}

function normalizeAutosaveEndpoint(endpoint: string): string {
  return endpoint.replace(/\/builder\/?$/, '/builder-schema');
}

async function requestJson<T>(url: string, init: RequestInit = {}): Promise<T> {
  const requestUrl = init.method?.toUpperCase() === 'PUT' ? normalizeAutosaveEndpoint(url) : url;
  const response = await fetch(requestUrl, {
    ...init,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(init.headers ?? {}),
    },
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message = typeof data.message === 'string' ? data.message : 'Request failed.';
    if (response.status === 422 && data.errors !== null && typeof data.errors === 'object') {
      throw new ValidationError(message, data.errors as Record<string, string[]>);
    }
    throw new Error(message);
  }

  return data as T;
}

export function createBuilderApi(endpoints: BuilderEndpoints, options: RequestOptions) {
  return {
    load() {
      return requestJson<BuilderPayload>(endpoints.show);
    },
    save(schema: SurveyBuilderSchema) {
      return requestJson<BuilderPayload & { saved_at: string }>(normalizeAutosaveEndpoint(endpoints.update), {
        method: 'PUT',
        headers: {
          'X-CSRF-TOKEN': options.csrfToken,
        },
        body: JSON.stringify({ schema }),
      });
    },
    publish() {
      return requestJson<BuilderPayload & { published_at: string | null }>(endpoints.publish, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': options.csrfToken,
        },
      });
    },
  };
}
