import type { SurveyCalculation } from '../types/schema';

export type BuilderVariableToken = {
  label: string;
  token: string;
  description?: string;
};

export function calculationToken(calculation: Pick<SurveyCalculation, 'key'>): string {
  return `{{ calc.${calculation.key || 'total_score'} }}`;
}

export function calculationTokenCode(calculation: Pick<SurveyCalculation, 'key'>): string {
  return `calc.${calculation.key || 'total_score'}`;
}

export function calculationVariableToken(calculation: Pick<SurveyCalculation, 'key' | 'label'>): BuilderVariableToken {
  const code = calculationTokenCode(calculation);

  return {
    label: calculation.label || calculation.key || '總分',
    token: calculationToken(calculation),
    description: `送出後顯示「${calculation.label || calculation.key || code}」的計算結果`,
  };
}

export function variableTokenChipHtml(variable: BuilderVariableToken): string {
  const code = variable.token.replace(/^\{\{\s*/, '').replace(/\s*\}\}$/, '');
  const label = escapeHtml(variable.label || code);
  const token = escapeHtml(variable.token);
  const escapedCode = escapeHtml(code);

  return `<span class="survey-variable-token" data-variable-token="${token}" data-variable-label="${label}" contenteditable="false">${label}<code>${escapedCode}</code></span>`;
}

export function normalizeVariableTokenChips(message: string): string {
  return message.replace(/<span\b[^>]*\bdata-variable-token=(["'])(.*?)\1[^>]*>.*?<\/span>/gis, (fullMatch, _quote: string, token: string) => {
    const normalized = decodeHtmlEntities(token).trim();

    return /^\{\{\s*calc\.[A-Za-z0-9_-]+\s*\}\}$/.test(normalized) ? normalized : fullMatch;
  });
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function decodeHtmlEntities(value: string): string {
  const textarea = document.createElement('textarea');
  textarea.innerHTML = value;

  return textarea.value;
}
