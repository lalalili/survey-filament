import { describe, expect, it } from 'vitest';
import type { AudienceListSummary, SurveySettings } from '../../resources/js/builder/types/schema';
import { hydratePersonalizationSettings } from '../../resources/js/builder/utils/systemContextFields';

const standardColumns = [
  { key: 'dlr', label: '經銷商', type: 'string' },
  { key: 'dept', label: '據點', type: 'string' },
  { key: 'regono', label: '車牌', type: 'string' },
  { key: 'delivery_date', label: '交車日', type: 'date' },
];

function audienceList(id: number, profile: string, columns = standardColumns): AudienceListSummary {
  return { id, name: `名單 ${id}`, schema_profile: profile, columns };
}

describe('system context field hydration', () => {
  it('selects the only list matching the normalized survey category', () => {
    const settings: SurveySettings = { category: ' ssi ' };

    expect(hydratePersonalizationSettings(settings, [
      audienceList(1, 'CSI'),
      audienceList(2, ' SSI '),
    ])).toBe(true);
    expect(settings.personalization).toMatchObject({
      audience_list_id: 2,
      required: true,
      result_context_columns: {
        dealer: 'dlr',
        location: 'dept',
        vehicle_plate: 'regono',
        delivery_date: 'delivery_date',
      },
    });
  });

  it('does not guess when multiple lists match the category', () => {
    const settings: SurveySettings = { category: 'SSI' };

    expect(hydratePersonalizationSettings(settings, [
      audienceList(1, 'SSI'),
      audienceList(2, 'ssi'),
    ])).toBe(false);
    expect(settings.personalization).toBeUndefined();
  });

  it('preserves valid manually selected context mappings', () => {
    const settings: SurveySettings = {
      category: 'SSI',
      personalization: {
        audience_list_id: 2,
        result_context_columns: {
          dealer: 'dealer_code',
          location: 'dept',
          vehicle_plate: 'regono',
          delivery_date: 'delivered_on',
        },
      },
    };
    const list = audienceList(2, 'SSI', [
      ...standardColumns,
      { key: 'dealer_code', label: '人工經銷商', type: 'string' },
      { key: 'delivered_on', label: '人工交車日', type: 'date' },
    ]);

    expect(hydratePersonalizationSettings(settings, [list])).toBe(false);
    expect(settings.personalization?.result_context_columns).toMatchObject({
      dealer: 'dealer_code',
      delivery_date: 'delivered_on',
    });
  });

  it('repairs missing, nonexistent, and incorrectly typed mappings', () => {
    const settings: SurveySettings = {
      category: 'SSI',
      personalization: {
        audience_list_id: 2,
        result_context_columns: {
          dealer: 'missing_dealer',
          location: null,
          vehicle_plate: 'regono',
          delivery_date: 'delivery_text',
        },
      },
    };
    const list = audienceList(2, 'SSI', [
      ...standardColumns,
      { key: 'delivery_text', label: '文字交車日', type: 'string' },
    ]);

    expect(hydratePersonalizationSettings(settings, [list])).toBe(true);
    expect(settings.personalization?.result_context_columns).toEqual({
      dealer: 'dlr',
      location: 'dept',
      vehicle_plate: 'regono',
      delivery_date: 'delivery_date',
    });
  });

  it('does not infer a non-date delivery column', () => {
    const settings: SurveySettings = {
      category: 'SSI',
      personalization: { audience_list_id: 2 },
    };
    const list = audienceList(2, 'SSI', [
      ...standardColumns.filter((column) => column.key !== 'delivery_date'),
      { key: 'timedelivered', label: '交車時間', type: 'string' },
    ]);

    expect(hydratePersonalizationSettings(settings, [list])).toBe(true);
    expect(settings.personalization?.result_context_columns?.delivery_date).toBeNull();
  });
});
