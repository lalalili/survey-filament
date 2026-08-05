// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, describe, expect, it } from 'vitest';
import SettingsModal from '../../resources/js/builder/dialogs/SettingsModal.vue';
import { useSurveyBuilderStore } from '../../resources/js/builder/stores/useSurveyBuilderStore';

function mountPersonalizationSettings() {
  const pinia = createPinia();
  setActivePinia(pinia);
  const store = useSurveyBuilderStore();
  store.schema = {
    id: 10009,
    title: '銷售滿意度問卷',
    status: 'draft',
    version: 1,
    pages: [],
    settings: {
      category: 'SSI',
      personalization: {
        audience_list_id: 2,
        name_column: 'name',
        email_column: 'email',
        external_id_column: 'customer_id',
        result_context_columns: {
          dealer: 'dlr',
          location: 'dept',
          vehicle_plate: 'regono',
          delivery_date: 'delivery_date',
        },
      },
    },
  };
  store.audienceLists = [{
    id: 2,
    name: '新車銷售滿意度調查名單',
    schema_profile: 'SSI',
    columns: [
      { key: 'name', label: '客戶姓名', type: 'string' },
      { key: 'email', label: 'Email', type: 'string' },
      { key: 'customer_id', label: '客戶編號', type: 'string' },
      { key: 'dlr', label: '經銷商名稱', type: 'string' },
      { key: 'dept', label: '銷售部門', type: 'string' },
      { key: 'regono', label: '車牌號碼', type: 'string' },
      { key: 'delivery_date', label: '交車日期', type: 'date' },
    ],
  }];

  const wrapper = mount(SettingsModal, {
    attachTo: document.body,
    props: {
      modelValue: true,
      uploadImageUrl: '/images',
      csrfToken: 'token',
    },
    global: { plugins: [pinia] },
  });

  return { wrapper, store };
}

afterEach(() => {
  document.body.innerHTML = '';
});

describe('SettingsModal personalization mappings', () => {
  it('does not expose category editing in basic settings', () => {
    const { wrapper } = mountPersonalizationSettings();

    const labels = Array.from(document.body.querySelectorAll<HTMLElement>('.sb-set-field-label'))
      .map((label) => label.textContent?.trim());

    expect(labels).not.toContain('分類');

    wrapper.unmount();
  });

  it('uses one labelled field-value-note layout for recipient and result mappings', async () => {
    const { wrapper } = mountPersonalizationSettings();
    Array.from(document.body.querySelectorAll<HTMLButtonElement>('.sb-settings-nav-item'))
      .find((item) => item.textContent?.trim() === '個性化名單')!
      .click();
    await wrapper.vm.$nextTick();

    const rows = document.body.querySelectorAll('.sb-set-mapping-row');
    expect(rows).toHaveLength(7);
    expect(document.body.querySelectorAll('.sb-set-mapping-head')).toHaveLength(2);

    for (const select of document.body.querySelectorAll<HTMLSelectElement>('.sb-set-mapping-value')) {
      expect(select.id).toBeTruthy();
      expect(document.body.querySelector(`label[for="${select.id}"]`)).not.toBeNull();
      expect(document.getElementById(select.getAttribute('aria-describedby') ?? '')).not.toBeNull();
    }

    expect((document.getElementById('personalization-name-column') as HTMLSelectElement).value).toBe('name');
    expect((document.getElementById('result-context-dealer') as HTMLSelectElement).value).toBe('dlr');

    wrapper.unmount();
  });

  it('preserves mapping updates and limits delivery date to date columns', async () => {
    const { wrapper, store } = mountPersonalizationSettings();
    Array.from(document.body.querySelectorAll<HTMLButtonElement>('.sb-settings-nav-item'))
      .find((item) => item.textContent?.trim() === '個性化名單')!
      .click();
    await wrapper.vm.$nextTick();

    const nameColumn = document.getElementById('personalization-name-column') as HTMLSelectElement;
    const dealerColumn = document.getElementById('result-context-dealer') as HTMLSelectElement;
    nameColumn.value = 'customer_id';
    nameColumn.dispatchEvent(new Event('change', { bubbles: true }));
    dealerColumn.value = 'dept';
    dealerColumn.dispatchEvent(new Event('change', { bubbles: true }));
    await wrapper.vm.$nextTick();

    expect(store.schema?.settings?.personalization?.name_column).toBe('customer_id');
    expect(store.schema?.settings?.personalization?.result_context_columns?.dealer).toBe('dept');

    const deliveryOptions = Array.from(document.querySelectorAll<HTMLOptionElement>('#result-context-delivery_date option'))
      .map((option) => option.value);
    expect(deliveryOptions).toEqual(['', 'delivery_date']);

    wrapper.unmount();
  });
});
