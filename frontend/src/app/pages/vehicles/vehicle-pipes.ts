import { Pipe, PipeTransform } from '@angular/core';

const km = new Intl.NumberFormat('fr-FR');
const decimal = new Intl.NumberFormat('fr-FR', {
  minimumFractionDigits: 1,
  maximumFractionDigits: 2,
});

/** 45200 → "45 200 km" */
@Pipe({ name: 'km' })
export class KmPipe implements PipeTransform {
  transform(value: number): string {
    return `${km.format(value)} km`;
  }
}

/** Hundredths: 583, "L" → "5,83 L/100 km"; a volume without per100: 4012, "L" → "40,12 L" */
@Pipe({ name: 'consumption' })
export class ConsumptionPipe implements PipeTransform {
  transform(hundredths: number, unit: string, per100 = true): string {
    return `${decimal.format(hundredths / 100)} ${unit}${per100 ? '/100 km' : ''}`;
  }
}
