import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import {
  Account,
  AccountType,
  Allowance,
  Category,
  CategoryKind,
  Child,
  Dashboard,
  FamilyRole,
  Member,
  MoneyRequest,
  Operation,
  PocketMoney,
  RecurringOperation,
  Projects,
  Teen,
  Transfer,
  Frequency,
  VehicleDetail,
  Vehicle,
  VehicleEnergy,
  VehicleVersion,
  RequestKind,
} from './api/models';

export interface OperationPayload {
  accountId: number;
  categoryId: number | null;
  type: CategoryKind;
  amount: number;
  label: string;
  date: string;
}

export interface RecurringOperationPayload extends Omit<OperationPayload, 'date'> {
  frequency: Frequency;
  nextOn: string;
  endsOn: string | null;
  active: boolean;
}

export interface VehiclePayload {
  name: string;
  energy: VehicleEnergy;
  plate: string | null;
  make: string | null;
  model: string | null;
  version: string | null;
  firstRegistration: string | null;
  officialConsumption: number | null;
  odometer: number | null;
  fuelBudget: number | null;
}

export interface CategoryPayload {
  name: string;
  kind: CategoryKind;
  color: string;
  monthlyBudget: number | null;
}

/** Calls of the API, one method per endpoint. */
@Injectable({ providedIn: 'root' })
export class BudgetApi {
  private readonly http = inject(HttpClient);

  dashboard(month: string): Observable<Dashboard> {
    return this.http.get<Dashboard>('api/dashboard', { params: { month } });
  }

  // Accounts

  /** The household's accounts; with `teens`, the teenagers' too */
  accounts(teens = false): Observable<Account[]> {
    return this.http.get<Account[]>('api/accounts', teens ? { params: { teens: 1 } } : {});
  }

  saveAccount(
    id: number | null,
    payload: {
      name: string;
      type: AccountType;
      initialBalance: number;
      minimumBalance: number | null;
      /** The teenager holding it; null for the household */
      ownerId: number | null;
    },
  ): Observable<Account> {
    return id === null
      ? this.http.post<Account>('api/accounts', payload)
      : this.http.put<Account>(`api/accounts/${id}`, payload);
  }

  deleteAccount(id: number): Observable<void> {
    return this.http.delete<void>(`api/accounts/${id}`);
  }

  // Categories and budgets

  categories(): Observable<Category[]> {
    return this.http.get<Category[]>('api/categories');
  }

  saveCategory(id: number | null, payload: CategoryPayload): Observable<Category> {
    return id === null
      ? this.http.post<Category>('api/categories', payload)
      : this.http.put<Category>(`api/categories/${id}`, payload);
  }

  deleteCategory(id: number): Observable<void> {
    return this.http.delete<void>(`api/categories/${id}`);
  }

  // Operations

  operations(
    month: string,
    /** Without an account: the household's, or with `ownerId` a teenager's */
    filters: { accountId?: number; categoryId?: number; ownerId?: number } = {},
  ): Observable<Operation[]> {
    let params = new HttpParams().set('month', month);
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined) {
        params = params.set(key, value);
      }
    }
    return this.http.get<Operation[]>('api/operations', { params });
  }

  saveOperation(id: number | null, payload: OperationPayload): Observable<Operation> {
    return id === null
      ? this.http.post<Operation>('api/operations', payload)
      : this.http.put<Operation>(`api/operations/${id}`, payload);
  }

  deleteOperation(id: number): Observable<void> {
    return this.http.delete<void>(`api/operations/${id}`);
  }

  /** Ticks an operation off against the bank statement, or unticks it */
  reconcile(id: number, reconciled: boolean): Observable<Operation> {
    return this.http.put<Operation>(`api/operations/${id}/reconciled`, { reconciled });
  }

  // Transfers between accounts

  saveTransfer(
    id: number | null,
    payload: Omit<Transfer, 'id' | 'label'> & { label: string },
  ): Observable<Transfer> {
    return id === null
      ? this.http.post<Transfer>('api/transfers', payload)
      : this.http.put<Transfer>(`api/transfers/${id}`, payload);
  }

  deleteTransfer(id: number): Observable<void> {
    return this.http.delete<void>(`api/transfers/${id}`);
  }

  // Recurring operations

  recurringOperations(): Observable<RecurringOperation[]> {
    return this.http.get<RecurringOperation[]>('api/recurring-operations');
  }

  saveRecurringOperation(
    id: number | null,
    payload: RecurringOperationPayload,
  ): Observable<RecurringOperation> {
    return id === null
      ? this.http.post<RecurringOperation>('api/recurring-operations', payload)
      : this.http.put<RecurringOperation>(`api/recurring-operations/${id}`, payload);
  }

  deleteRecurringOperation(id: number): Observable<void> {
    return this.http.delete<void>(`api/recurring-operations/${id}`);
  }

  // Savings projects

  projects(): Observable<Projects> {
    return this.http.get<Projects>('api/projects');
  }

  saveProject(
    id: number | null,
    payload: { accountId: number; name: string; target: number },
  ): Observable<void> {
    return id === null
      ? this.http.post<void>('api/projects', payload)
      : this.http.put<void>(`api/projects/${id}`, payload);
  }

  /** New order of the projects of a savings account, the first one funded first */
  prioritizeProjects(accountId: number, projectIds: number[]): Observable<void> {
    return this.http.put<void>('api/projects/priorities', { accountId, projectIds });
  }

  achieveProject(
    id: number,
    recordWithdrawal: boolean,
    categoryId: number | null,
  ): Observable<void> {
    return this.http.post<void>(`api/projects/${id}/achieve`, { recordWithdrawal, categoryId });
  }

  deleteProject(id: number): Observable<void> {
    return this.http.delete<void>(`api/projects/${id}`);
  }

  // Members

  members(): Observable<Member[]> {
    return this.http.get<Member[]>('api/members');
  }

  addMember(payload: {
    displayName: string;
    username: string;
    role: FamilyRole;
    password: string;
  }): Observable<Member> {
    return this.http.post<Member>('api/members', payload);
  }

  renameMember(id: number, displayName: string): Observable<Member> {
    return this.http.patch<Member>(`api/members/${id}`, { displayName });
  }

  resetPassword(id: number, password: string): Observable<void> {
    return this.http.put<void>(`api/members/${id}/password`, { password });
  }

  deleteMember(id: number): Observable<void> {
    return this.http.delete<void>(`api/members/${id}`);
  }

  // Pocket money

  children(): Observable<Child[]> {
    return this.http.get<Child[]>('api/children');
  }

  pocketMoney(childId: number): Observable<PocketMoney> {
    return this.http.get<PocketMoney>(`api/children/${childId}`);
  }

  spend(
    childId: number,
    payload: { amount: number; label: string; date: string },
  ): Observable<PocketMoney> {
    return this.http.post<PocketMoney>(`api/children/${childId}/expenses`, payload);
  }

  adjust(childId: number, payload: { amount: number; label: string }): Observable<PocketMoney> {
    return this.http.post<PocketMoney>(`api/children/${childId}/adjustments`, payload);
  }

  setAllowance(childId: number, allowance: Allowance): Observable<PocketMoney> {
    return this.http.put<PocketMoney>(`api/children/${childId}/allowance`, allowance);
  }

  removeAllowance(childId: number): Observable<PocketMoney> {
    return this.http.delete<PocketMoney>(`api/children/${childId}/allowance`);
  }

  deleteEntry(entryId: number): Observable<PocketMoney> {
    return this.http.delete<PocketMoney>(`api/pocket-money/${entryId}`);
  }

  // Teenagers

  teens(): Observable<Teen[]> {
    return this.http.get<Teen[]>('api/teens');
  }

  teen(teenId: number): Observable<Teen> {
    return this.http.get<Teen>(`api/teens/${teenId}`);
  }

  setTeenAllowance(teenId: number, allowance: Allowance): Observable<Teen> {
    return this.http.put<Teen>(`api/teens/${teenId}/allowance`, allowance);
  }

  removeTeenAllowance(teenId: number): Observable<Teen> {
    return this.http.delete<Teen>(`api/teens/${teenId}/allowance`);
  }

  // Requests

  requests(): Observable<MoneyRequest[]> {
    return this.http.get<MoneyRequest[]>('api/requests');
  }

  ask(payload: {
    kind: Exclude<RequestKind, 'reimbursement'>;
    amount: number;
    reason: string;
  }): Observable<MoneyRequest> {
    return this.http.post<MoneyRequest>('api/requests', payload);
  }

  /** A teenager asks to be paid back for one of their expenses */
  askReimbursement(operationId: number): Observable<MoneyRequest> {
    return this.http.post<MoneyRequest>('api/requests/reimbursement', { operationId });
  }

  /** `accountId`: household account it is taken from; `categoryId`: for a reimbursement */
  approve(
    id: number,
    answer: string | null,
    accountId: number | null,
    categoryId: number | null = null,
  ): Observable<MoneyRequest> {
    return this.http.post<MoneyRequest>(`api/requests/${id}/approve`, {
      answer,
      accountId,
      categoryId,
    });
  }

  reject(id: number, answer: string | null): Observable<MoneyRequest> {
    return this.http.post<MoneyRequest>(`api/requests/${id}/reject`, { answer });
  }

  cancel(id: number): Observable<MoneyRequest> {
    return this.http.post<MoneyRequest>(`api/requests/${id}/cancel`, {});
  }

  // Vehicles

  vehicles(): Observable<Vehicle[]> {
    return this.http.get<Vehicle[]>('api/vehicles');
  }

  vehicle(id: number): Observable<VehicleDetail> {
    return this.http.get<VehicleDetail>(`api/vehicles/${id}`);
  }

  saveVehicle(id: number | null, payload: VehiclePayload): Observable<VehicleDetail> {
    return id === null
      ? this.http.post<VehicleDetail>('api/vehicles', payload)
      : this.http.put<VehicleDetail>(`api/vehicles/${id}`, payload);
  }

  deleteVehicle(id: number): Observable<void> {
    return this.http.delete<void>(`api/vehicles/${id}`);
  }

  /** A new fill-up of the vehicle, or a change to the fill-up `refuelId` */
  saveRefuel(
    vehicleId: number,
    refuelId: number | null,
    payload: {
      accountId: number;
      amount: number;
      volume: number;
      odometer: number | null;
      date: string;
    },
  ): Observable<VehicleDetail> {
    return refuelId === null
      ? this.http.post<VehicleDetail>(`api/vehicles/${vehicleId}/refuels`, payload)
      : this.http.put<VehicleDetail>(`api/refuels/${refuelId}`, payload);
  }

  deleteRefuel(id: number): Observable<VehicleDetail> {
    return this.http.delete<VehicleDetail>(`api/refuels/${id}`);
  }

  saveMaintenance(
    vehicleId: number,
    maintenanceId: number | null,
    payload: { label: string; plannedOn: string; budget: number; plannedOdometer: number | null },
  ): Observable<VehicleDetail> {
    return maintenanceId === null
      ? this.http.post<VehicleDetail>(`api/vehicles/${vehicleId}/maintenances`, payload)
      : this.http.put<VehicleDetail>(`api/maintenances/${maintenanceId}`, payload);
  }

  deleteMaintenance(id: number): Observable<VehicleDetail> {
    return this.http.delete<VehicleDetail>(`api/maintenances/${id}`);
  }

  /** Records its actual cost */
  completeMaintenance(
    id: number,
    payload: { accountId: number; amount: number; date: string; odometer: number | null },
  ): Observable<VehicleDetail> {
    return this.http.put<VehicleDetail>(`api/maintenances/${id}/done`, payload);
  }

  /** Not done after all: its expense is deleted */
  reopenMaintenance(id: number): Observable<VehicleDetail> {
    return this.http.delete<VehicleDetail>(`api/maintenances/${id}/done`);
  }

  /** Cars sold new in France (ADEME): makes, the models of a make, the versions of a model */
  vehicleCatalog(
    make?: string,
    model?: string,
  ): Observable<{
    makes?: string[];
    models?: string[];
    versions?: VehicleVersion[];
  }> {
    let params = new HttpParams();
    if (make) {
      params = params.set('make', make);
    }
    if (make && model) {
      params = params.set('model', model);
    }
    return this.http.get<{ makes?: string[]; models?: string[]; versions?: VehicleVersion[] }>(
      'api/vehicle-catalog',
      { params },
    );
  }
}
