import {
  AccountType,
  Frequency,
  PocketMoneyKind,
  RequestKind,
  RequestStatus,
  VehicleEnergy,
} from './api/models';

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
  reimbursement: 'Remboursement',
};

export const REQUEST_STATUS_LABELS: Record<RequestStatus, string> = {
  pending: 'En attente',
  approved: 'Acceptée',
  rejected: 'Refusée',
  cancelled: 'Retirée',
};

export const VEHICLE_ENERGY_LABELS: Record<VehicleEnergy, string> = {
  petrol: 'Essence',
  diesel: 'Diesel',
  hybrid: 'Hybride',
  plugin_hybrid: 'Hybride rechargeable',
  electric: 'Électrique',
  lpg: 'GPL',
  ethanol: 'Superéthanol (E85)',
  other: 'Autre',
};

/** What a fill-up is measured in: kWh for an electric vehicle, litres otherwise */
export function energyUnit(energy: VehicleEnergy): string {
  return energy === 'electric' ? 'kWh' : 'L';
}
