import { Pipe, PipeTransform } from '@angular/core';
import { formatCents, formatDay, formatMonth } from '../core/money';

/** Cents → "12,50 €"; with `signed`, a "+" before incomes. */
@Pipe({ name: 'euros' })
export class EurosPipe implements PipeTransform {
  transform(cents: number, signed = false): string {
    // `|| 0`: a negated zero (-0) is shown as 0,00 €, not -0,00 €
    const text = formatCents(cents || 0);
    return signed && cents > 0 ? `+${text}` : text;
  }
}

/** "2026-10-28" → "28 oct." */
@Pipe({ name: 'day' })
export class DayPipe implements PipeTransform {
  transform(date: string): string {
    return formatDay(date);
  }
}

/** "2026-10" → "octobre 2026" */
@Pipe({ name: 'month' })
export class MonthPipe implements PipeTransform {
  transform(month: string): string {
    return formatMonth(month);
  }
}
