import './pin-input.css';

interface PinKeypadProps {
  onDigit: (digit: string) => void;
  onBackspace: () => void;
  onReset: () => void;
  disabled?: boolean;
}

const DIGIT_ROWS = [
  ['1', '2', '3'],
  ['4', '5', '6'],
  ['7', '8', '9'],
];

export function PinKeypad({ onDigit, onBackspace, onReset, disabled = false }: PinKeypadProps) {
  return (
    <div className="pin-keypad">
      {DIGIT_ROWS.flat().map((digit) => (
        <button
          key={digit}
          type="button"
          className="pin-keypad__key"
          onClick={() => onDigit(digit)}
          disabled={disabled}
        >
          {digit}
        </button>
      ))}
      <button
        type="button"
        className="pin-keypad__key pin-keypad__key--action"
        onClick={onBackspace}
        disabled={disabled}
        aria-label="Letzte Ziffer löschen"
      >
        ⌫
      </button>
      <button type="button" className="pin-keypad__key" onClick={() => onDigit('0')} disabled={disabled}>
        0
      </button>
      <button
        type="button"
        className="pin-keypad__key pin-keypad__key--action"
        onClick={onReset}
        disabled={disabled}
        aria-label="Eingabe zurücksetzen"
      >
        ↺
      </button>
    </div>
  );
}
