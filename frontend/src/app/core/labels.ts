import { AccountType, Frequency, PocketMoneyKind, RequestKind, RequestStatus } from './api/models';

export const ACCOUNT_TYPE_LABELS: Record<AccountType, string> = {
  checking: 'Compte courant',
  savings: 'Épargne',
  cash: 'Espèces',
};

export const FREQUENCY_LABELS: Record<Frequency, string> = {
  weekly: 'chaque semaine',
  monthly: 'chaque mois',
  quarterly: 'chaque trimestre',
  yearly: 'chaque année',
};

export const POCKET_MONEY_KIND_LABELS: Record<PocketMoneyKind, string> = {
  allowance: 'Argent de poche',
  expense: 'Dépense',
  advance: 'Avance',
  repayment: 'Remboursement',
  adjustment: 'Ajout des parents',
};

export const REQUEST_KIND_LABELS: Record<RequestKind, string> = {
  advance: 'Avance',
  purchase: 'Achat',
};

export const REQUEST_STATUS_LABELS: Record<RequestStatus, string> = {
  pending: 'En attente',
  approved: 'Acceptée',
  rejected: 'Refusée',
  cancelled: 'Retirée',
};
