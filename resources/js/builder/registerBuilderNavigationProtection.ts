import type { App } from 'vue';

interface LivewireNavigateEventDetail {
  url: URL;
}

interface BuilderNavigationProtectionOptions {
  app: App;
  hasUnsavedChanges: () => boolean;
  confirmLeave: () => boolean;
  navigate: (url: string) => void;
}

export function registerBuilderNavigationProtection({
  app,
  hasUnsavedChanges,
  confirmLeave,
  navigate,
}: BuilderNavigationProtectionOptions): void {
  let confirmedNavigationUrl: string | null = null;

  const guardNavigation = (event: Event): void => {
    const navigateEvent = event as CustomEvent<LivewireNavigateEventDetail>;
    const destinationUrl = navigateEvent.detail.url.href;

    if (confirmedNavigationUrl === destinationUrl) {
      confirmedNavigationUrl = null;

      return;
    }

    if (!hasUnsavedChanges()) {
      return;
    }

    event.preventDefault();

    if (!confirmLeave()) {
      return;
    }

    confirmedNavigationUrl = destinationUrl;
    navigate(destinationUrl);
  };

  document.addEventListener('livewire:navigate', guardNavigation);
  document.addEventListener('livewire:navigating', () => {
    document.removeEventListener('livewire:navigate', guardNavigation);
    app.unmount();
  }, { once: true });
}
