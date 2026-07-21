import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

describe('number dialog styles', () => {
  it('gives form controls enough height to display their full text', () => {
    const stylesheet = readFileSync(
      resolve(process.cwd(), 'resources/js/builder/styles/builder.css'),
      'utf8',
    );

    expect(stylesheet).toMatch(
      /\.sb-number-dialog-grid input,[\s\S]*?\.sb-number-dialog-grid select \{[\s\S]*?height: 34px; line-height: normal;/,
    );
    expect(stylesheet).toContain('.sb-number-dialog-grid select { padding: 0 32px 0 10px; }');
  });
});
