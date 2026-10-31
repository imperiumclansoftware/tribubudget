import {
  ChangeDetectionStrategy,
  Component,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
  untracked,
} from '@angular/core';
import { rxResource, toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { firstValueFrom, map, of } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { Vehicle, VehicleDetail, VehicleEnergy, VehicleVersion } from '../../core/api/models';
import { BudgetApi } from '../../core/budget-api';
import { VEHICLE_ENERGY_LABELS, energyUnit } from '../../core/labels';
import { centsToInput, parseEuros } from '../../core/money';

/** "renault" matches "RENAULT" of the catalog */
function findIn(list: string[] | undefined, value: string): string | undefined {
  const wanted = value.trim().toLowerCase();
  return wanted ? list?.find((item) => item.toLowerCase() === wanted) : undefined;
}

/**
 * Adds or describes a vehicle. Make and model come from the catalog of the cars sold new in France
 * (ADEME) when they are in it, typed by hand otherwise; a version of the catalog gives the energy
 * and the approved consumption.
 */
@Component({
  selector: 'app-vehicle-form',
  imports: [ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './vehicle-form.html',
})
export class VehicleForm {
  private readonly api = inject(BudgetApi);

  /** The vehicle to describe; absent to add one */
  readonly vehicle = input<Vehicle | null>(null);
  readonly saved = output<VehicleDetail>();
  readonly cancelled = output<void>();

  protected readonly energies = Object.entries(VEHICLE_ENERGY_LABELS) as [VehicleEnergy, string][];
  protected readonly energyLabels = VEHICLE_ENERGY_LABELS;
  protected readonly error = signal<string | null>(null);
  protected readonly pending = signal(false);

  protected readonly form = inject(NonNullableFormBuilder).group({
    name: ['', Validators.required],
    plate: [''],
    make: [''],
    model: [''],
    version: [''],
    energy: ['petrol' as VehicleEnergy],
    firstRegistration: [''],
    officialConsumption: [''],
    odometer: [''],
    fuelBudget: [''],
  });
  private readonly make = toSignal(this.form.controls.make.valueChanges, { initialValue: '' });
  private readonly model = toSignal(this.form.controls.model.valueChanges, { initialValue: '' });
  private readonly energy = toSignal(this.form.controls.energy.valueChanges, {
    initialValue: this.form.controls.energy.value,
  });
  private readonly version = toSignal(this.form.controls.version.valueChanges, {
    initialValue: '',
  });
  protected readonly unit = computed(() => energyUnit(this.energy()));

  // Catalog: the models once a make of the catalog is chosen, the versions once a model is
  private readonly makes = rxResource({
    stream: () => this.api.vehicleCatalog().pipe(map((c) => c.makes ?? [])),
  });
  protected readonly makeOptions = computed(() => this.makes.value() ?? []);
  private readonly catalogMake = computed(() => findIn(this.makes.value(), this.make()));
  private readonly models = rxResource({
    params: () => this.catalogMake(),
    stream: ({ params: make }) =>
      make ? this.api.vehicleCatalog(make).pipe(map((c) => c.models ?? [])) : of([]),
  });
  protected readonly modelOptions = computed(() => this.models.value() ?? []);
  private readonly catalogModel = computed(() => findIn(this.models.value(), this.model()));
  private readonly versions = rxResource({
    params: () => ({ make: this.catalogMake(), model: this.catalogModel() }),
    stream: ({ params: { make, model } }) =>
      make && model
        ? this.api.vehicleCatalog(make, model).pipe(map((c) => c.versions ?? []))
        : of([] as VehicleVersion[]),
  });
  protected readonly versionOptions = computed(() => this.versions.value() ?? []);
  /** Position of the vehicle's version among the catalog's, by name and energy; -1 for none */
  protected readonly chosenVersion = computed(() =>
    this.versionOptions().findIndex(
      (v) => v.label === this.version() && v.energy === this.energy(),
    ),
  );
  protected readonly catalogDown = computed(
    () => !this.makes.isLoading() && (this.makes.error() || !this.makeOptions().length),
  );

  constructor() {
    effect(() => {
      const vehicle = this.vehicle();
      untracked(() => {
        this.form.reset({
          name: vehicle?.name ?? '',
          plate: vehicle?.plate ?? '',
          make: vehicle?.make ?? '',
          model: vehicle?.model ?? '',
          version: vehicle?.version ?? '',
          energy: vehicle?.energy ?? 'petrol',
          firstRegistration: vehicle?.firstRegistration ?? '',
          officialConsumption:
            vehicle?.officialConsumption == null ? '' : centsToInput(vehicle.officialConsumption),
          odometer: vehicle?.odometer == null ? '' : String(vehicle.odometer),
          fuelBudget: vehicle?.fuel?.budget == null ? '' : centsToInput(vehicle.fuel.budget),
        });
        this.error.set(null);
      });
    });
  }

  /** A version of the catalog: its energy and approved consumption, and the make and model as named there */
  protected chooseVersion(index: number): void {
    const version = this.versionOptions()[index];
    if (!version) {
      this.form.controls.version.setValue('');
      return;
    }
    this.form.patchValue({
      version: version.label,
      make: this.catalogMake() ?? this.form.controls.make.value,
      model: this.catalogModel() ?? this.form.controls.model.value,
      energy: version.energy,
      officialConsumption: version.consumption === null ? '' : centsToInput(version.consumption),
    });
    if (!this.form.controls.name.value.trim()) {
      const model = this.catalogModel() ?? '';
      this.form.controls.name.setValue(model.charAt(0) + model.slice(1).toLowerCase());
    }
  }

  protected async save(): Promise<void> {
    const value = this.form.getRawValue();
    const optional = (text: string) => text.trim() || null;
    const consumption = value.officialConsumption.trim()
      ? parseEuros(value.officialConsumption)
      : null;
    const fuelBudget = value.fuelBudget.trim() ? parseEuros(value.fuelBudget) : null;
    const odometer = value.odometer.trim() ? Number(value.odometer.replace(/\s/g, '')) : null;
    if (this.form.invalid) {
      this.error.set('Donnez un nom au véhicule.');
      return;
    }
    if (
      (value.officialConsumption.trim() && (consumption === null || consumption <= 0)) ||
      (value.fuelBudget.trim() && (fuelBudget === null || fuelBudget <= 0)) ||
      (odometer !== null && (!Number.isInteger(odometer) || odometer < 0))
    ) {
      this.error.set('La consommation, le kilométrage et le budget sont des nombres positifs.');
      return;
    }
    this.pending.set(true);
    this.error.set(null);
    try {
      this.saved.emit(
        await firstValueFrom(
          this.api.saveVehicle(this.vehicle()?.id ?? null, {
            name: value.name,
            energy: value.energy,
            plate: optional(value.plate),
            make: optional(value.make),
            model: optional(value.model),
            version: optional(value.version),
            firstRegistration: optional(value.firstRegistration),
            officialConsumption: consumption,
            odometer,
            fuelBudget,
          }),
        ),
      );
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
