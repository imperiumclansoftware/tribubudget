import { inject } from '@angular/core';
import { Routes } from '@angular/router';
import {
  authGuard,
  childGuard,
  guestGuard,
  installedGuard,
  parentGuard,
  setupGuard,
  teenGuard,
} from './core/auth/auth.guards';
import { AuthService } from './core/auth/auth.service';

export const routes: Routes = [
  {
    path: 'installation',
    canActivate: [setupGuard],
    title: 'Installation · TribuBudget',
    loadComponent: () => import('./pages/auth/setup.page').then((m) => m.SetupPage),
  },
  {
    path: 'connexion',
    canActivate: [installedGuard, guestGuard],
    title: 'Connexion · TribuBudget',
    loadComponent: () => import('./pages/auth/login.page').then((m) => m.LoginPage),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/shell/shell').then((m) => m.Shell),
    children: [
      // Home: the dashboard for a parent, their accounts for a teenager, the piggy bank for a child
      {
        path: '',
        pathMatch: 'full',
        redirectTo: () => inject(AuthService).home().slice(1),
      },
      // Parents
      {
        path: 'tableau-de-bord',
        canActivate: [parentGuard],
        title: 'Tableau de bord · TribuBudget',
        loadComponent: () =>
          import('./pages/dashboard/dashboard.page').then((m) => m.DashboardPage),
      },
      {
        path: 'operations',
        canActivate: [parentGuard],
        title: 'Opérations · TribuBudget',
        loadComponent: () =>
          import('./pages/operations/operations.page').then((m) => m.OperationsPage),
      },
      {
        path: 'operations/recurrentes',
        canActivate: [parentGuard],
        title: 'Opérations récurrentes · TribuBudget',
        loadComponent: () =>
          import('./pages/operations/recurring.page').then((m) => m.RecurringPage),
      },
      {
        path: 'budgets',
        canActivate: [parentGuard],
        title: 'Budgets · TribuBudget',
        loadComponent: () => import('./pages/budgets/budgets.page').then((m) => m.BudgetsPage),
      },
      {
        path: 'projets',
        canActivate: [parentGuard],
        title: 'Projets · TribuBudget',
        loadComponent: () => import('./pages/projects/projects.page').then((m) => m.ProjectsPage),
      },
      {
        path: 'vehicules',
        canActivate: [parentGuard],
        title: 'Véhicules · TribuBudget',
        loadComponent: () => import('./pages/vehicles/vehicles.page').then((m) => m.VehiclesPage),
      },
      {
        path: 'vehicules/:vehicleId',
        canActivate: [parentGuard],
        title: 'Véhicule · TribuBudget',
        loadComponent: () => import('./pages/vehicles/vehicle.page').then((m) => m.VehiclePage),
      },
      {
        path: 'famille',
        canActivate: [parentGuard],
        title: 'Famille · TribuBudget',
        loadComponent: () => import('./pages/family/family.page').then((m) => m.FamilyPage),
      },
      {
        path: 'famille/ado/:teenId',
        canActivate: [parentGuard],
        title: 'Comptes d’un adolescent · TribuBudget',
        loadComponent: () =>
          import('./pages/teen-accounts/teen-accounts.page').then((m) => m.TeenAccountsPage),
      },
      {
        path: 'famille/:childId',
        canActivate: [parentGuard],
        title: 'Tirelire d’un enfant · TribuBudget',
        loadComponent: () =>
          import('./pages/pocket-money/pocket-money.page').then((m) => m.PocketMoneyPage),
      },
      {
        path: 'comptes',
        canActivate: [parentGuard],
        title: 'Comptes et catégories · TribuBudget',
        loadComponent: () => import('./pages/settings/settings.page').then((m) => m.SettingsPage),
      },
      // Children
      {
        path: 'ma-tirelire',
        canActivate: [childGuard],
        title: 'Ma tirelire · TribuBudget',
        loadComponent: () =>
          import('./pages/pocket-money/pocket-money.page').then((m) => m.PocketMoneyPage),
      },
      // Teenagers
      {
        path: 'mes-comptes',
        canActivate: [teenGuard],
        title: 'Mes comptes · TribuBudget',
        loadComponent: () =>
          import('./pages/teen-accounts/teen-accounts.page').then((m) => m.TeenAccountsPage),
      },
      // Everyone
      {
        path: 'demandes',
        title: 'Demandes · TribuBudget',
        loadComponent: () => import('./pages/requests/requests.page').then((m) => m.RequestsPage),
      },
      {
        path: 'mon-compte',
        title: 'Mon compte · TribuBudget',
        loadComponent: () => import('./pages/account/account.page').then((m) => m.AccountPage),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
