import './pin-input.css';

interface PinDotsProps {
  length: number;
  filledCount: number;
  error?: boolean;
}

export function PinDots({ length, filledCount, error = false }: PinDotsProps) {
  return (
    <div className={`pin-dots${error ? ' pin-dots--error' : ''}`}>
      {Array.from({ length }, (_, index) => (
        <span
          key={index}
          className={`pin-dots__dot${index < filledCount ? ' pin-dots__dot--filled' : ''}`}
        />
      ))}
    </div>
  );
}
