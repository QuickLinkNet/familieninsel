import type { ReactNode } from 'react';
import './dashboard-overlay.css';

interface DashboardOverlayProps {
  isOpen: boolean;
  onToggle: () => void;
  children: ReactNode;
}

export function DashboardOverlay({ isOpen, onToggle, children }: DashboardOverlayProps) {
  return (
    <div className={`dashboard-overlay ${isOpen ? 'dashboard-overlay--open' : 'dashboard-overlay--closed'}`}>
      <button
        type="button"
        className="dashboard-overlay__handle"
        onClick={onToggle}
        aria-expanded={isOpen}
      >
        {isOpen ? 'Karte ansehen ▼' : 'Aufgaben & Insel ▲'}
      </button>
      {isOpen && <div className="dashboard-overlay__content">{children}</div>}
    </div>
  );
}
