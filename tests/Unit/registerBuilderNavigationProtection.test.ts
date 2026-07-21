// @vitest-environment jsdom

import type { App } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerBuilderNavigationProtection } from '../../resources/js/builder/registerBuilderNavigationProtection';

const destinationUrl = 'http://localhost/admin/surveys';

function navigateEvent(): CustomEvent<{ url: URL }> {
  return new CustomEvent('livewire:navigate', {
    cancelable: true,
    detail: { url: new URL(destinationUrl) },
  });
}

describe('registerBuilderNavigationProtection', () => {
  beforeEach(() => vi.restoreAllMocks());
  afterEach(() => document.dispatchEvent(new CustomEvent('livewire:navigating')));

  it('allows navigation without confirmation after changes are saved', () => {
    const confirmLeave = vi.fn();
    const event = navigateEvent();

    registerBuilderNavigationProtection({
      app: { unmount: vi.fn() } as unknown as App,
      hasUnsavedChanges: () => false,
      confirmLeave,
      navigate: vi.fn(),
    });

    document.dispatchEvent(event);

    expect(event.defaultPrevented).toBe(false);
    expect(confirmLeave).not.toHaveBeenCalled();
  });

  it('keeps the builder open when leaving with unsaved changes is cancelled', () => {
    const navigate = vi.fn();
    const event = navigateEvent();

    registerBuilderNavigationProtection({
      app: { unmount: vi.fn() } as unknown as App,
      hasUnsavedChanges: () => true,
      confirmLeave: () => false,
      navigate,
    });

    document.dispatchEvent(event);

    expect(event.defaultPrevented).toBe(true);
    expect(navigate).not.toHaveBeenCalled();
  });

  it('restarts confirmed navigation without asking twice', () => {
    const confirmLeave = vi.fn(() => true);
    const navigate = vi.fn(() => document.dispatchEvent(navigateEvent()));
    const event = navigateEvent();

    registerBuilderNavigationProtection({
      app: { unmount: vi.fn() } as unknown as App,
      hasUnsavedChanges: () => true,
      confirmLeave,
      navigate,
    });

    document.dispatchEvent(event);

    expect(event.defaultPrevented).toBe(true);
    expect(confirmLeave).toHaveBeenCalledOnce();
    expect(navigate).toHaveBeenCalledWith(destinationUrl);
  });

  it('unmounts the builder and removes its guard before Livewire swaps the page', () => {
    const unmount = vi.fn();
    const confirmLeave = vi.fn(() => false);

    registerBuilderNavigationProtection({
      app: { unmount } as unknown as App,
      hasUnsavedChanges: () => true,
      confirmLeave,
      navigate: vi.fn(),
    });

    document.dispatchEvent(new CustomEvent('livewire:navigating'));
    document.dispatchEvent(navigateEvent());

    expect(unmount).toHaveBeenCalledOnce();
    expect(confirmLeave).not.toHaveBeenCalled();
  });
});
