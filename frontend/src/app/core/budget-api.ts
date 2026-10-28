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
  Transfer,
  Frequency,
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

  accounts(): Observable<Account[]> {
    return this.http.get<Account[]>('api/accounts');
  }

  saveAccount(
    id: number | null,
    payload: {
      name: string;
      type: AccountType;
      initialBalance: number;
      minimumBalance: number | null;
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
    filters: { accountId?: number; categoryId?: number } = {},
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

  // Requests

  requests(): Observable<MoneyRequest[]> {
    return this.http.get<MoneyRequest[]>('api/requests');
  }

  ask(payload: { kind: RequestKind; amount: number; reason: string }): Observable<MoneyRequest> {
    return this.http.post<MoneyRequest>('api/requests', payload);
  }

  approve(id: number, answer: string | null, accountId: number | null): Observable<MoneyRequest> {
    return this.http.post<MoneyRequest>(`api/requests/${id}/approve`, { answer, accountId });
  }

  reject(id: number, answer: string | null): Observable<MoneyRequest> {
    return this.http.post<MoneyRequest>(`api/requests/${id}/reject`, { answer });
  }

  cancel(id: number): Observable<MoneyRequest> {
    return this.http.post<MoneyRequest>(`api/requests/${id}/cancel`, {});
  }
}
