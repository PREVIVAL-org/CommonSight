/**
 * Short status messages as a separate live region (U-05, T-09, Architecture 9.5); hiding them is controlled by
 * application/toast-expiry.
 */
import { X } from 'lucide-react';
import type { Toast } from '../../state/app-state';
import { useActions, useAppState, useTexts } from '../hooks';

function ToastItem({ toast }: { toast: Toast }) {
  const t = useTexts();
  const actions = useActions();
  return (
    <div className="toast" data-tone={toast.tone}>
      <span>{'message' in toast ? t.msg(toast.message) : t.ui(toast.key, toast.params)}</span>
      <button
        type="button"
        className="icon-button"
        aria-label={t.ui('toast.close')}
        onClick={() => actions.dismissToast(toast.id)}
      >
        <X size={14} aria-hidden="true" />
      </button>
    </div>
  );
}

export function Toasts() {
  const t = useTexts();
  const toasts = useAppState((state) => state.ui.toasts);
  return (
    <div className="toasts" role="status" aria-live="polite" aria-label={t.ui('toast.region')}>
      {toasts.map((toast) => (
        <ToastItem key={toast.id} toast={toast} />
      ))}
    </div>
  );
}
