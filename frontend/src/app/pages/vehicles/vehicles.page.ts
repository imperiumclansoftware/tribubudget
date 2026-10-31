import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { VehicleDetail } from '../../core/api/models';
import { BudgetApi } from '../../core/budget-api';
import { VEHICLE_ENERGY_LABELS, energyUnit } from '../../core/labels';
import { formatLongDay, todayIso } from '../../core/money';
import { BudgetMeter } from '../../shared/budget-meter';
import { EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';
import { ConsumptionPipe, KmPipe } from './vehicle-pipes';
import { VehicleForm } from './vehicle-form';

/** The family's vehicles: fuel of the month against its budget, consumption, next maintenance. */
@Component({
  selector: 'app-vehicles-page',
  imports: [RouterLink, Modal, Icon, EurosPipe, BudgetMeter, VehicleForm, KmPipe, ConsumptionPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './vehicles.page.html',
  styleUrl: './vehicles.scss',
})
export class VehiclesPage {
  private readonly api = inject(BudgetApi);
  private readonly router = inject(Router);

  protected readonly vehicles = rxResource({ stream: () => this.api.vehicles() });
  protected readonly loadError = computed(() =>
    this.vehicles.error() ? apiErrorMessage(this.vehicles.error()) : null,
  );
  protected readonly energies = VEHICLE_ENERGY_LABELS;
  protected readonly energyUnit = energyUnit;
  protected readonly formatLongDay = formatLongDay;
  protected readonly today = todayIso();
  protected readonly adding = signal(false);

  protected added(vehicle: VehicleDetail): void {
    this.adding.set(false);
    void this.router.navigate(['/vehicules', vehicle.id]);
  }
}
