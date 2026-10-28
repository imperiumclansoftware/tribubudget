import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  effect,
  input,
  output,
  viewChild,
} from '@angular/core';
import { Icon } from './icon';

/**
 * Modal dialog (native <dialog>): shown while `open` is true; Escape, the close button or a
 * click on the backdrop emit `closed`.
 */
@Component({
  selector: 'app-modal',
  imports: [Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <dialog
      #dialog
      [attr.aria-labelledby]="titleId"
      (cancel)="$event.preventDefault(); closed.emit()"
      (click)="onBackdrop($event)"
    >
      <div class="panel">
        <header>
          <h2 [id]="titleId">{{ heading() }}</h2>
          <button type="button" class="btn btn-icon" (click)="closed.emit()" aria-label="Fermer">
            <app-icon name="x" />
          </button>
        </header>
        <ng-content />
      </div>
    </dialog>
  `,
  styles: `
    /* Phones: a sheet sliding from the bottom, within reach of the thumb */
    dialog {
      width: 100%;
      max-width: 100%;
      max-height: 92dvh;
      margin: auto 0 0;
      padding: 0;
      border: 1px solid var(--border-color);
      border-bottom: 0;
      border-radius: var(--radius-lg) var(--radius-lg) 0 0;
      background: var(--bg-surface);
      color: var(--text-primary);
      box-shadow: var(--shadow-floating);
    }
    dialog::backdrop {
      background: rgb(15 23 42 / 50%);
    }
    .panel {
      padding: 8px 16px calc(20px + env(safe-area-inset-bottom));
    }
    .panel::before {
      content: '';
      display: block;
      width: 40px;
      height: 4px;
      margin: 0 auto 8px;
      border-radius: 999px;
      background: var(--border-color);
    }
    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 12px;
    }
    @media (prefers-reduced-motion: no-preference) {
      dialog[open] {
        animation: rise 0.2s ease-out;
      }
    }
    @keyframes rise {
      from {
        transform: translateY(24px);
        opacity: 0;
      }
    }

    /* Tablets: a centered dialog */
    @media (min-width: 600px) {
      dialog {
        width: min(32rem, calc(100vw - 32px));
        max-height: calc(100dvh - 32px);
        margin: auto;
        border-bottom: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
      }
      .panel {
        padding: 16px 20px 20px;
      }
      .panel::before {
        display: none;
      }
    }
  `,
})
export class Modal {
  private static nextId = 0;

  readonly heading = input.required<string>();
  readonly open = input(false);
  readonly closed = output<void>();

  protected readonly titleId = `modal-title-${Modal.nextId++}`;
  private readonly dialog = viewChild.required<ElementRef<HTMLDialogElement>>('dialog');

  constructor() {
    effect(() => {
      const dialog = this.dialog().nativeElement;
      if (this.open() && !dialog.open) {
        dialog.showModal();
      } else if (!this.open() && dialog.open) {
        dialog.close();
      }
    });
  }

  protected onBackdrop(event: MouseEvent): void {
    if (event.target === this.dialog().nativeElement) {
      this.closed.emit();
    }
  }
}
