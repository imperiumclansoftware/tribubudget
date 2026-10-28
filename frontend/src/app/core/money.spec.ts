import { describe, expect, it } from 'vitest';
import { centsToInput, parseEuros, shiftMonth } from './money';

describe('money', () => {
  it('reads amounts typed the French or the English way', () => {
    expect(parseEuros('12')).toBe(1200);
    expect(parseEuros('12,5')).toBe(1250);
    expect(parseEuros('1 234.56 €')).toBe(123456);
    expect(parseEuros('0,07')).toBe(7);
  });

  it('rejects what is not an amount', () => {
    expect(parseEuros('')).toBeNull();
    expect(parseEuros('douze')).toBeNull();
    expect(parseEuros('1,234')).toBeNull();
  });

  it('writes amounts back for editing', () => {
    expect(centsToInput(1250)).toBe('12,50');
    expect(centsToInput(1200)).toBe('12');
  });

  it('moves across years', () => {
    expect(shiftMonth('2026-12', 1)).toBe('2027-01');
    expect(shiftMonth('2026-01', -1)).toBe('2025-12');
  });
});
