/** Shapes of the API responses. Amounts are in cents. */

export type FamilyRole = 'parent' | 'teen' | 'child';
export type AccountType = 'checking' | 'savings' | 'cash';
export type CategoryKind = 'expense' | 'income';
export type Frequency = 'weekly' | 'monthly' | 'quarterly' | 'yearly';
export type AllowanceFrequency = Extract<Frequency, 'weekly' | 'monthly'>;
export type PocketMoneyKind = 'allowance' | 'expense' | 'advance' | 'repayment' | 'adjustment';
export type RequestKind = 'advance' | 'purchase' | 'reimbursement';
export type RequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export interface Member {
  id: number;
  username: string;
  displayName: string;
  role: FamilyRole;
}

export interface Me extends Member {
  family: { id: number; name: string };
  /** For a parent: requests of the children and teenagers waiting for an answer */
  pendingRequests: number;
}

export interface AuthResponse {
  token: string;
}

export interface Account {
  id: number;
  name: string;
  type: AccountType;
  initialBalance: number;
  /** Savings accounts: amount kept aside, not allocated to the projects */
  minimumBalance: number | null;
  balance: number;
  /** Initial balance plus the operations ticked off, to compare with the bank statement */
  reconciledBalance?: number;
  /** The teenager holding the account; null for the household's */
  owner: { id: number; displayName: string } | null;
}

export interface SavingsProject {
  id: number;
  name: string;
  target: number;
  /** 1 is funded first */
  priority: number;
  /** Share of the account's money this project receives */
  allocated: number;
  /** The share covers the target: it can be carried out now */
  fundable: boolean;
}

export interface SavingsAccount extends Account {
  /** Balance above the minimum, shared among the projects */
  available: number;
  /** Left once every project is funded */
  unallocated: number;
  projects: SavingsProject[];
}

export interface Projects {
  accounts: SavingsAccount[];
  achieved: { id: number; name: string; target: number; accountName: string; achievedAt: string }[];
}

export interface Category {
  id: number;
  name: string;
  kind: CategoryKind;
  color: string;
  monthlyBudget: number | null;
  /** The category of the allowances and advances */
  pocketMoney: boolean;
}

export interface Operation {
  id: number;
  accountId: number;
  categoryId: number | null;
  /** Negative for an expense */
  amount: number;
  label: string;
  date: string;
  createdBy: string | null;
  /** Set when recorded by a recurring operation */
  recurringId: number | null;
  /** Ticked off against the bank statement */
  reconciled: boolean;
  /** Set on the two operations of a transfer; the counterpart is the other account */
  transfer: { id: number; counterpartAccountId: number } | null;
  /** Latest request of a teenager to be paid back for this expense */
  reimbursement: { id: number; status: RequestStatus } | null;
  /** Whether the viewer may change it (or its transfer) */
  editable: boolean;
}

export interface Transfer {
  id: number;
  fromAccountId: number;
  toAccountId: number;
  amount: number;
  label: string;
  date: string;
}

export interface RecurringOperation {
  id: number;
  accountId: number;
  categoryId: number | null;
  /** Negative for an expense */
  amount: number;
  label: string;
  frequency: Frequency;
  nextOn: string;
  endsOn: string | null;
  active: boolean;
  /** Past its end date */
  finished: boolean;
}

export interface Allowance {
  amount: number;
  frequency: AllowanceFrequency;
  nextPaymentOn: string;
  accountId: number | null;
  active: boolean;
}

export interface Child extends Member {
  balance: number;
  advanceOwed: number;
  allowance: Allowance | null;
}

/** A teenager and their accounts, outside the household budget */
export interface Teen extends Member {
  /** Sum of their accounts */
  balance: number;
  advanceOwed: number;
  allowance: Allowance | null;
  accounts: Account[];
}

export interface PocketMoneyEntry {
  id: number;
  kind: PocketMoneyKind;
  amount: number;
  label: string;
  date: string;
  createdBy: string | null;
  canDelete: boolean;
}

export interface PocketMoney extends Child {
  entries: PocketMoneyEntry[];
}

export interface MoneyRequest {
  id: number;
  child: Member;
  kind: RequestKind;
  amount: number;
  reason: string;
  /** Reimbursements: the teenager's expense */
  operationId: number | null;
  status: RequestStatus;
  createdAt: string;
  decidedBy: string | null;
  decidedAt: string | null;
  answer: string | null;
}

export interface Dashboard {
  month: string;
  income: number;
  expenses: number;
  accounts: { id: number; name: string; type: AccountType; balance: number }[];
  totalBalance: number;
  categories: { id: number; name: string; color: string; spent: number; budget: number | null }[];
  uncategorized: number;
  history: { month: string; income: number; expenses: number }[];
  children: { id: number; displayName: string; balance: number }[];
  /** What each teenager holds on their accounts */
  teens: { id: number; displayName: string; balance: number }[];
  /** Projects that can be carried out now */
  fundableProjects: { id: number; name: string; target: number; accountName: string }[];
  /** Recurring operations falling within 30 days */
  upcoming: {
    id: number;
    label: string;
    amount: number;
    date: string;
    categoryColor: string | null;
  }[];
  activeAllowances: number;
  pendingRequests: number;
  /** What should be left at the end of the current month (null for another month); expenses positive */
  forecast: {
    /** Already received, plus the recurring income still to come */
    income: number;
    /** Recurring expenses outside the budgeted categories */
    recurring: number;
    /** Budgeted categories: their budget, or what was spent when more */
    budgets: number;
    /** Allowances still to pay, and what was given already */
    pocketMoney: number;
    /** One-off expenses outside the budgets */
    other: number;
    remaining: number;
  } | null;
}

export interface ApiProblem {
  status?: number;
  detail?: string;
  message?: string;
  violations?: { propertyPath: string; title: string }[];
}
