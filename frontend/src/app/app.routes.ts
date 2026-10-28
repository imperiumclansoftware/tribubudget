import { inject } from '@angular/core';
import { Routes } from '@angular/router';
import {
  authGuard,
  childGuard,
  guestGuard,
  installedGuard,
  parentGuard,
  setupGuard,
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
      // Home: the dashboard for a parent, the piggy bank for a child
      {
        path: '',
        pathMatch: 'full',
        redirectTo: () => (inject(AuthService).isParent() ? 'tableau-de-bord' : 'ma-tirelire'),
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
        path: 'famille',
        canActivate: [parentGuard],
        title: 'Famille · TribuBudget',
        loadComponent: () => import('./pages/family/family.page').then((m) => m.FamilyPage),
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
