// @vitest-environment jsdom

import { describe, expect, it } from 'vitest';
import { readBuilderEndpoints } from '../../resources/js/builder/utils/builderEndpoints';

function mountPoint(attributes: Record<string, string>): HTMLElement {
  const element = document.createElement('div');

  Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value));

  return element;
}

const requiredAttributes = {
  'data-endpoint-show': '/admin/surveys/7/builder-data',
  'data-endpoint-update': '/admin/surveys/7/builder-schema',
  'data-endpoint-publish': '/admin/surveys/7/builder-publish',
  'data-endpoint-activities': '/admin/surveys/7/builder-activities',
  'data-endpoint-restore-published': '/admin/surveys/7/builder-restore-published',
  'data-endpoint-upload-image': '/admin/surveys/7/builder-image',
  'data-endpoint-cascade-template': '/admin/surveys/7/builder-cascade-template',
  'data-endpoint-cascade-import': '/admin/surveys/7/builder-cascade-import',
};

describe('readBuilderEndpoints', () => {
  it('uses the server-rendered URLs verbatim', () => {
    const endpoints = readBuilderEndpoints(mountPoint(requiredAttributes).dataset);

    expect(endpoints).toMatchObject({
      show: '/admin/surveys/7/builder-data',
      update: '/admin/surveys/7/builder-schema',
      publish: '/admin/surveys/7/builder-publish',
      activities: '/admin/surveys/7/builder-activities',
      restorePublished: '/admin/surveys/7/builder-restore-published',
      uploadImage: '/admin/surveys/7/builder-image',
      cascadeTemplate: '/admin/surveys/7/builder-cascade-template',
      cascadeImport: '/admin/surveys/7/builder-cascade-import',
    });
  });

  it('keeps a non-default route prefix intact instead of deriving it from the page URL', () => {
    const prefixed = Object.fromEntries(
      Object.entries(requiredAttributes).map(([name, value]) => [name, `/backoffice/questionnaires${value.replace('/admin/surveys', '')}`]),
    );

    const endpoints = readBuilderEndpoints(mountPoint(prefixed).dataset);

    expect(endpoints.show).toBe('/backoffice/questionnaires/7/builder-data');
    expect(endpoints.update).toBe('/backoffice/questionnaires/7/builder-schema');
  });

  it('treats Google Drive endpoints as optional', () => {
    const withoutDrive = readBuilderEndpoints(mountPoint(requiredAttributes).dataset);

    expect(withoutDrive.googleDriveConnect).toBeUndefined();
    expect(withoutDrive.googleDriveStatus).toBeUndefined();
    expect(withoutDrive.googleDriveDisconnect).toBeUndefined();

    const withDrive = readBuilderEndpoints(mountPoint({
      ...requiredAttributes,
      'data-endpoint-gd-connect': '/admin/surveys/7/google-drive/connect',
      'data-endpoint-gd-status': '',
    }).dataset);

    expect(withDrive.googleDriveConnect).toBe('/admin/surveys/7/google-drive/connect');
    expect(withDrive.googleDriveStatus).toBeUndefined();
  });

  it('fails loudly when a required endpoint attribute is missing', () => {
    const { 'data-endpoint-update': _missing, ...rest } = requiredAttributes;

    expect(() => readBuilderEndpoints(mountPoint(rest).dataset))
      .toThrow('survey-builder：掛載點缺少 data-endpoint-update 屬性。');
  });
});
