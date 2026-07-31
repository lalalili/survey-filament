import type { BuilderEndpoints } from '../types/schema';

/**
 * 所有 endpoint 都由 `EditSurveyBuilder::getViewData()` 以 `route()` 產生後寫進
 * 掛載點的 `data-endpoint-*`，前端不再從 `window.location` 反推路徑——route prefix
 * 一改，反推就會指向不存在的 URL。屬性缺漏視為掛載點寫錯，直接拋錯而不猜 URL。
 */
function requiredEndpoint(value: string | undefined, attribute: string): string {
  if (!value) {
    throw new Error(`survey-builder：掛載點缺少 ${attribute} 屬性。`);
  }

  return value;
}

export function readBuilderEndpoints(dataset: DOMStringMap): BuilderEndpoints {
  return {
    show: requiredEndpoint(dataset.endpointShow, 'data-endpoint-show'),
    update: requiredEndpoint(dataset.endpointUpdate, 'data-endpoint-update'),
    publish: requiredEndpoint(dataset.endpointPublish, 'data-endpoint-publish'),
    activities: requiredEndpoint(dataset.endpointActivities, 'data-endpoint-activities'),
    restorePublished: requiredEndpoint(dataset.endpointRestorePublished, 'data-endpoint-restore-published'),
    uploadImage: requiredEndpoint(dataset.endpointUploadImage, 'data-endpoint-upload-image'),
    cascadeTemplate: requiredEndpoint(dataset.endpointCascadeTemplate, 'data-endpoint-cascade-template'),
    cascadeImport: requiredEndpoint(dataset.endpointCascadeImport, 'data-endpoint-cascade-import'),
    // Google Drive 綁定為選用功能，未設定時對應 API 直接回傳未連線。
    googleDriveConnect: dataset.endpointGdConnect || undefined,
    googleDriveStatus: dataset.endpointGdStatus || undefined,
    googleDriveDisconnect: dataset.endpointGdDisconnect || undefined,
  };
}
