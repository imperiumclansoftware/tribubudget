const euros = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });
const compactEuros = new Intl.NumberFormat('fr-FR', {
  style: 'currency',
  currency: 'EUR',
  notation: 'compact',
  maximumFractionDigits: 1,
});

/** 123456 → "1 234,56 €" */
export function formatCents(cents: number): string {
  return euros.format(cents / 100);
}

/** For the chart axes: 250000 → "2,5 k€" */
export function formatCentsCompact(cents: number): string {
  return compactEuros.format(cents / 100);
}

/**
 * What the user typed, in euros ("12", "12,5", "1 234.56 €"), in cents;
 * null if it is not an amount.
 */
export function parseEuros(text: string): number | null {
  const normalized = text.replace(/[\s  €]/g, '').replace(',', '.');
  if (!/^-?\d+(\.\d{1,2})?$/.test(normalized)) {
    return null;
  }
  return Math.round(Number(normalized) * 100);
}

/** Cents as typed in an input: 1250 → "12,50", 1200 → "12" */
export function centsToInput(cents: number): string {
  return cents % 100 === 0 ? String(cents / 100) : (cents / 100).toFixed(2).replace('.', ',');
}

/** Today in the browser's time zone, as "YYYY-MM-DD" */
export function todayIso(): string {
  return isoDate(new Date());
}

export function isoDate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function currentMonth(): string {
  return todayIso().slice(0, 7);
}

/** "2026-10" + 1 → "2026-11" */
export function shiftMonth(month: string, delta: number): string {
  const [year, m] = month.split('-').map(Number);
  const date = new Date(year, m - 1 + delta, 1);
  return isoDate(date).slice(0, 7);
}

const monthNames = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
const shortMonthNames = new Intl.DateTimeFormat('fr-FR', { month: 'short' });
const dayNames = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' });
const longDayNames = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
});

function parseMonth(month: string): Date {
  const [year, m] = month.split('-').map(Number);
  return new Date(year, m - 1, 1);
}

function parseDay(date: string): Date {
  const [year, m, d] = date.split('-').map(Number);
  return new Date(year, m - 1, d);
}

/** "2026-10" → "octobre 2026" */
export function formatMonth(month: string): string {
  return monthNames.format(parseMonth(month));
}

/** "2026-10" → "oct." */
export function formatShortMonth(month: string): string {
  return shortMonthNames.format(parseMonth(month));
}

/** "2026-10-28" → "28 oct." */
export function formatDay(date: string): string {
  return dayNames.format(parseDay(date));
}

/** "2026-10-28" → "mercredi 28 octobre" */
export function formatLongDay(date: string): string {
  return longDayNames.format(parseDay(date));
}
