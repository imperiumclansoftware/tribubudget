import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { Icon, IconName } from '../../shared/icon';

interface NavItem {
  path: string;
  label: string;
  /** On phones, when the label is too long for the bottom bar */
  short?: string;
  /** Only in the side navigation of desktops: on phones, the top bar leads there */
  desktopOnly?: boolean;
  icon: IconName;
  badge?: number;
}

/**
 * Layout of the signed-in pages (theme of themecss/): a top bar, the page, and a fixed bottom
 * navigation suited to the member, from phones to tablets.
 */
@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './shell.html',
  styleUrl: './shell.scss',
})
export class Shell {
  protected readonly auth = inject(AuthService);

  protected readonly nav = computed<NavItem[]>(() => {
    const user = this.auth.user();
    if (user?.role === 'teen') {
      return [
        { path: '/mes-comptes', label: 'Mes comptes', short: 'Comptes', icon: 'wallet' },
        { path: '/demandes', label: 'Mes demandes', short: 'Demandes', icon: 'inbox' },
        { path: '/mon-compte', label: 'Mon compte', icon: 'key' },
      ];
    }
    if (user?.role !== 'parent') {
      return [
        { path: '/ma-tirelire', label: 'Ma tirelire', icon: 'piggy' },
        { path: '/demandes', label: 'Mes demandes', short: 'Demandes', icon: 'inbox' },
        { path: '/mon-compte', label: 'Mon code', icon: 'key' },
      ];
    }
    // On phones, the accounts and the parent's own account are in the top bar
    return [
      { path: '/tableau-de-bord', label: 'Tableau de bord', short: 'Accueil', icon: 'dashboard' },
      { path: '/operations', label: 'Opérations', short: 'Opérations', icon: 'list' },
      { path: '/budgets', label: 'Budgets', icon: 'target' },
      { path: '/projets', label: 'Projets', icon: 'flag' },
      { path: '/famille', label: 'Famille', icon: 'users' },
      { path: '/demandes', label: 'Demandes', icon: 'inbox', badge: user.pendingRequests },
      { path: '/comptes', label: 'Comptes', icon: 'wallet', desktopOnly: true },
    ];
  });
}
