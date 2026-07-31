import { nextTick, onBeforeUnmount, watch, type Ref, type WatchSource } from 'vue';

const FOCUSABLE_SELECTOR = [
  'button:not([disabled])',
  '[href]',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

interface UseDialogFocusOptions {
  isOpen: WatchSource<boolean>;
  dialog: Readonly<Ref<HTMLElement | null>>;
  close: () => void;
}

export function useDialogFocus({ isOpen, dialog, close }: UseDialogFocusOptions): void {
  let opener: HTMLElement | null = null;
  let activeDialog: HTMLElement | null = null;
  let wasOpen = false;

  function focusableElements(): HTMLElement[] {
    return dialog.value
      ? Array.from(dialog.value.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR))
        .filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true')
      : [];
  }

  function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      event.preventDefault();
      close();

      return;
    }

    if (event.key !== 'Tab') {
      return;
    }

    const focusable = focusableElements();
    const first = focusable[0];
    const last = focusable.at(-1);

    if (!first || !last) {
      event.preventDefault();

      return;
    }

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function removeListener(): void {
    activeDialog?.removeEventListener('keydown', handleKeydown);
    activeDialog = null;
  }

  watch(isOpen, async (isOpen) => {
    if (isOpen) {
      opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      wasOpen = true;
      await nextTick();
      activeDialog = dialog.value;
      activeDialog?.addEventListener('keydown', handleKeydown);
      const initialFocus = activeDialog?.querySelector<HTMLElement>('[data-dialog-initial-focus]');
      initialFocus?.focus();
      if (!initialFocus) {
        focusableElements()[0]?.focus();
      }

      return;
    }

    if (wasOpen) {
      removeListener();
      opener?.focus();
      opener = null;
      wasOpen = false;
    }
  }, { flush: 'post', immediate: true });

  onBeforeUnmount(() => {
    removeListener();

    if (wasOpen) {
      opener?.focus();
    }
  });
}
