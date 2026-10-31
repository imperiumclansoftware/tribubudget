import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  input,
  linkedSignal,
  signal,
} from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable, firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { Maintenance, Refuel, VehicleDetail } from '../../core/api/models';
import { BudgetApi } from '../../core/budget-api';
import { VEHICLE_ENERGY_LABELS, energyUnit } from '../../core/labels';
import { centsToInput, formatLongDay, parseEuros, todayIso } from '../../core/money';
import { BudgetMeter } from '../../shared/budget-meter';
import { DayPipe, EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';
import { ConsumptionPipe, KmPipe } from './vehicle-pipes';
import { VehicleForm } from './vehicle-form';

/** What maintenance usually is, offered when planning one */
const MAINTENANCE_LABELS = [
  'Révision',
  'Vidange',
  'Pneus',
  'Plaquettes de frein',
  'Contrôle technique',
  'Courroie de distribution',
  'Batterie',
  'Climatisation',
];

/** Kilometres typed with or without spaces; null when empty, NaN when not a number */
function parseKm(text: string): number | null {
  const compact = text.replace(/\s/g, '');
  return compact ? (/^\d+$/.test(compact) ? Number(compact) : NaN) : null;
}

/**
 * A vehicle: its fill-ups (expenses of its fuel category, with its monthly budget) and its
 * maintenance, planned with a budget of its own and recorded with its actual cost once done.
 */
@Component({
  selector: 'app-vehicle-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    Modal,
    Icon,
    EurosPipe,
    DayPipe,
    BudgetMeter,
    VehicleForm,
    KmPipe,
    ConsumptionPipe,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './vehicle.page.html',
  styleUrl: './vehicles.scss',
})
export class VehiclePage {
  private readonly api = inject(BudgetApi);
  private readonly router = inject(Router);
  private readonly fb = inject(NonNullableFormBuilder);

  /** Route parameter */
  readonly vehicleId = input.required<string>();

  private readonly resource = rxResource({
    params: () => Number(this.vehicleId()),
    stream: ({ params }) => this.api.vehicle(params),
  });
  /** Replaced by the answer of each change */
  protected readonly vehicle = linkedSignal(() => this.resource.value());
  protected readonly loadError = computed(() =>
    this.resource.error() ? apiErrorMessage(this.resource.error()) : null,
  );
  /** The household's accounts: the vehicles are paid by the household */
  protected readonly accounts = rxResource({ stream: () => this.api.accounts() });

  protected readonly unit = computed(() => energyUnit(this.vehicle()?.energy ?? 'petrol'));
  protected readonly due = computed(() =>
    (this.vehicle()?.maintenances ?? []).filter((m) => !m.done),
  );
  protected readonly done = computed(() =>
    (this.vehicle()?.maintenances ?? []).filter((m) => m.done),
  );
  protected readonly energies = VEHICLE_ENERGY_LABELS;
  protected readonly maintenanceLabels = MAINTENANCE_LABELS;
  protected readonly formatLongDay = formatLongDay;
  protected readonly today = todayIso();
  protected readonly error = signal<string | null>(null);
  protected readonly pending = signal(false);
  protected readonly dialog = signal<'edit' | 'refuel' | 'maintenance' | 'done' | null>(null);

  /** Price of one litre (or kWh), in cents */
  protected unitPrice(refuel: Refuel): number {
    return Math.round((refuel.amount * 100) / refuel.volume);
  }

  // Fill-ups

  protected readonly editingRefuel = signal<Refuel | null>(null);
  protected readonly refuelForm = this.fb.group({
    accountId: [0, Validators.min(1)],
    amount: ['', Validators.required],
    volume: ['', Validators.required],
    odometer: [''],
    date: [todayIso(), Validators.required],
  });

  protected openRefuel(refuel: Refuel | null = null): void {
    this.refuelForm.reset(
      refuel
        ? {
            accountId: refuel.accountId,
            amount: centsToInput(refuel.amount),
            volume: centsToInput(refuel.volume),
            odometer: refuel.odometer === null ? '' : String(refuel.odometer),
            date: refuel.date,
          }
        : {
            accountId: this.accounts.value()?.[0]?.id ?? 0,
            amount: '',
            volume: '',
            odometer: '',
            date: todayIso(),
          },
    );
    this.editingRefuel.set(refuel);
    this.open('refuel');
  }

  protected async saveRefuel(): Promise<void> {
    const value = this.refuelForm.getRawValue();
    const amount = parseEuros(value.amount);
    const volume = parseEuros(value.volume);
    const odometer = parseKm(value.odometer);
    if (
      this.refuelForm.invalid ||
      amount === null ||
      amount <= 0 ||
      volume === null ||
      volume <= 0 ||
      Number.isNaN(odometer)
    ) {
      this.error.set(
        'Indiquez le compte, le montant, la quantité et la date ; le kilométrage en chiffres.',
      );
      return;
    }
    await this.save(
      this.api.saveRefuel(this.id(), this.editingRefuel()?.id ?? null, {
        accountId: value.accountId,
        amount,
        volume,
        odometer,
        date: value.date,
      }),
    );
  }

  protected async removeRefuel(): Promise<void> {
    const refuel = this.editingRefuel();
    if (refuel && confirm('Supprimer ce plein et sa dépense ?')) {
      await this.save(this.api.deleteRefuel(refuel.id));
    }
  }

  // Maintenance

  protected readonly editingMaintenance = signal<Maintenance | null>(null);
  protected readonly maintenanceForm = this.fb.group({
    label: ['', Validators.required],
    plannedOn: [todayIso(), Validators.required],
    budget: [''],
    plannedOdometer: [''],
  });

  protected openMaintenance(maintenance: Maintenance | null = null): void {
    this.maintenanceForm.reset(
      maintenance
        ? {
            label: maintenance.label,
            plannedOn: maintenance.plannedOn,
            budget: centsToInput(maintenance.budget),
            plannedOdometer:
              maintenance.plannedOdometer === null ? '' : String(maintenance.plannedOdometer),
          }
        : { label: '', plannedOn: todayIso(), budget: '', plannedOdometer: '' },
    );
    this.editingMaintenance.set(maintenance);
    this.open('maintenance');
  }

  protected async saveMaintenance(): Promise<void> {
    const value = this.maintenanceForm.getRawValue();
    const budget = value.budget.trim() ? parseEuros(value.budget) : 0;
    const plannedOdometer = parseKm(value.plannedOdometer);
    if (
      this.maintenanceForm.invalid ||
      budget === null ||
      budget < 0 ||
      Number.isNaN(plannedOdometer)
    ) {
      this.error.set('Indiquez l’entretien, sa date et son budget ; le kilométrage en chiffres.');
      return;
    }
    await this.save(
      this.api.saveMaintenance(this.id(), this.editingMaintenance()?.id ?? null, {
        label: value.label,
        plannedOn: value.plannedOn,
        budget,
        plannedOdometer,
      }),
    );
  }

  protected async removeMaintenance(): Promise<void> {
    const maintenance = this.editingMaintenance();
    if (maintenance && confirm(`Supprimer l’entretien « ${maintenance.label} » ?`)) {
      await this.save(this.api.deleteMaintenance(maintenance.id));
    }
  }

  protected readonly doneForm = this.fb.group({
    accountId: [0, Validators.min(1)],
    amount: ['', Validators.required],
    date: [todayIso(), Validators.required],
    odometer: [''],
  });

  protected openDone(maintenance: Maintenance): void {
    this.doneForm.reset({
      accountId: this.accounts.value()?.[0]?.id ?? 0,
      amount: maintenance.budget ? centsToInput(maintenance.budget) : '',
      date: todayIso(),
      odometer: '',
    });
    this.editingMaintenance.set(maintenance);
    this.open('done');
  }

  protected async saveDone(): Promise<void> {
    const maintenance = this.editingMaintenance();
    const value = this.doneForm.getRawValue();
    const amount = parseEuros(value.amount);
    const odometer = parseKm(value.odometer);
    if (
      !maintenance ||
      this.doneForm.invalid ||
      amount === null ||
      amount <= 0 ||
      Number.isNaN(odometer)
    ) {
      this.error.set(
        'Indiquez le compte, ce qu’il a coûté et la date ; le kilométrage en chiffres.',
      );
      return;
    }
    await this.save(
      this.api.completeMaintenance(maintenance.id, {
        accountId: value.accountId,
        amount,
        date: value.date,
        odometer,
      }),
    );
  }

  protected async reopen(maintenance: Maintenance): Promise<void> {
    if (confirm(`« ${maintenance.label} » n’est pas fait ? Sa dépense sera supprimée.`)) {
      await this.save(this.api.reopenMaintenance(maintenance.id));
    }
  }

  // The vehicle itself

  protected edited(vehicle: VehicleDetail): void {
    this.vehicle.set(vehicle);
    this.dialog.set(null);
  }

  protected async remove(): Promise<void> {
    const vehicle = this.vehicle();
    if (
      vehicle &&
      confirm(
        `Supprimer « ${vehicle.name} » ? Ses pleins et ses entretiens sont effacés ; leurs dépenses restent dans les opérations.`,
      )
    ) {
      try {
        await firstValueFrom(this.api.deleteVehicle(vehicle.id));
        void this.router.navigate(['/vehicules']);
      } catch (e) {
        this.error.set(apiErrorMessage(e));
      }
    }
  }

  protected open(dialog: 'edit' | 'refuel' | 'maintenance' | 'done'): void {
    this.error.set(null);
    this.dialog.set(dialog);
  }

  protected close(): void {
    this.dialog.set(null);
    this.error.set(null);
  }

  private id(): number {
    return Number(this.vehicleId());
  }

  private async save(request: Observable<VehicleDetail>): Promise<void> {
    this.pending.set(true);
    this.error.set(null);
    try {
      this.vehicle.set(await firstValueFrom(request));
      this.dialog.set(null);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
